<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\Sync\Receivers\ModelHasPermissionReceiver;
use App\Services\Sync\Receivers\ModelHasRoleReceiver;
use App\Services\Sync\Receivers\RoleHasPermissionReceiver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bridges Spatie permission attach/detach events into the sync outbox.
 *
 * Spatie's `model_has_roles` / `model_has_permissions` pivots are
 * written via raw DB inserts inside the HasRoles / HasPermissions
 * traits, so the SyncsToOutbox Eloquent hook does not see them.
 * Instead we listen for the package's own attach/detach events
 * (gated on `permission.events_enabled`) and emit one outbox row per
 * (model, role|permission) pair through OutboxWriter::writeRaw().
 */
class SpatiePivotOutboxListener
{
    public function __construct(private OutboxWriter $writer) {}

    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->emitRoleEvent($event->model, $event->rolesOrIds, SyncOp::CREATE);
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->emitRoleEvent($event->model, $event->rolesOrIds, SyncOp::DELETE);
    }

    public function handlePermissionAttached(PermissionAttachedEvent $event): void
    {
        if ($event->model instanceof RoleContract) {
            $this->emitRolePermissionEvent($event->model, $event->permissionsOrIds, SyncOp::CREATE);

            return;
        }

        $this->emitPermissionEvent($event->model, $event->permissionsOrIds, SyncOp::CREATE);
    }

    public function handlePermissionDetached(PermissionDetachedEvent $event): void
    {
        if ($event->model instanceof RoleContract) {
            $this->emitRolePermissionEvent($event->model, $event->permissionsOrIds, SyncOp::DELETE);

            return;
        }

        $this->emitPermissionEvent($event->model, $event->permissionsOrIds, SyncOp::DELETE);
    }

    private function emitRolePermissionEvent(Model $role, mixed $permissionsOrIds, string $op): void
    {
        if (OutboxWriter::isMuted()) {
            return;
        }

        foreach ($this->resolvePermissions($permissionsOrIds) as $perm) {
            $this->writer->writeRaw(
                'role_has_permissions',
                RoleHasPermissionReceiver::entityId($role->name, $role->guard_name, $perm['name'], $perm['guard_name']),
                $op,
                [
                    'role_name' => $role->name,
                    'role_guard' => $role->guard_name,
                    'permission_name' => $perm['name'],
                    'permission_guard' => $perm['guard_name'],
                ]
            );
        }
    }

    private function emitRoleEvent(Model $model, mixed $rolesOrIds, string $op): void
    {
        if (OutboxWriter::isMuted()) {
            return;
        }

        $modelUuid = $this->modelUuid($model);
        if ($modelUuid === null) {
            Log::warning('sync.role_event.no_model_uuid', [
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'op' => $op,
            ]);

            return;
        }

        foreach ($this->resolveRoles($rolesOrIds) as $role) {
            $this->writer->writeRaw(
                'model_has_roles',
                ModelHasRoleReceiver::entityId($modelUuid, $model::class, $role['name'], $role['guard_name']),
                $op,
                [
                    'role_name' => $role['name'],
                    'guard_name' => $role['guard_name'],
                    'model_uuid' => $modelUuid,
                    'model_type' => $model::class,
                ]
            );
        }
    }

    private function emitPermissionEvent(Model $model, mixed $permissionsOrIds, string $op): void
    {
        if (OutboxWriter::isMuted()) {
            return;
        }

        $modelUuid = $this->modelUuid($model);
        if ($modelUuid === null) {
            Log::warning('sync.permission_event.no_model_uuid', [
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'op' => $op,
            ]);

            return;
        }

        foreach ($this->resolvePermissions($permissionsOrIds) as $perm) {
            $this->writer->writeRaw(
                'model_has_permissions',
                ModelHasPermissionReceiver::entityId($modelUuid, $model::class, $perm['name'], $perm['guard_name']),
                $op,
                [
                    'permission_name' => $perm['name'],
                    'guard_name' => $perm['guard_name'],
                    'model_uuid' => $modelUuid,
                    'model_type' => $model::class,
                ]
            );
        }
    }

    private function modelUuid(Model $model): ?string
    {
        $uuid = $model->getAttribute('uuid');

        if ($uuid) {
            return (string) $uuid;
        }

        // Fallback: re-fetch from the table in case the model in memory
        // was created before its uuid column was hydrated.
        if (! $model->exists) {
            return null;
        }

        return DB::table($model->getTable())
            ->where($model->getKeyName(), $model->getKey())
            ->value('uuid');
    }

    /**
     * Normalize the mixed payload Spatie hands us into [{name,guard_name}].
     *
     * @return array<int, array{name: string, guard_name: string}>
     */
    private function resolveRoles(mixed $rolesOrIds): array
    {
        $items = $this->collectionFor($rolesOrIds);
        $roleClass = app(PermissionRegistrar::class)->getRoleClass();

        return $items
            ->map(function ($item) use ($roleClass) {
                if ($item instanceof RoleContract) {
                    return ['name' => $item->name, 'guard_name' => $item->guard_name];
                }

                $role = $roleClass::find($item);

                return $role ? ['name' => $role->name, 'guard_name' => $role->guard_name] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, guard_name: string}>
     */
    private function resolvePermissions(mixed $permissionsOrIds): array
    {
        $items = $this->collectionFor($permissionsOrIds);
        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        return $items
            ->map(function ($item) use ($permissionClass) {
                if ($item instanceof PermissionContract) {
                    return ['name' => $item->name, 'guard_name' => $item->guard_name];
                }

                $permission = $permissionClass::find($item);

                return $permission ? ['name' => $permission->name, 'guard_name' => $permission->guard_name] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function collectionFor(mixed $value): Collection
    {
        if ($value instanceof Collection) {
            return $value;
        }

        if (is_array($value)) {
            return collect($value);
        }

        return collect([$value]);
    }
}
