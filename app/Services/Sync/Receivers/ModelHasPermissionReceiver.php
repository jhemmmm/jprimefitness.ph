<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Services\Sync\AckStatus;
use App\Services\Sync\OutboxWriter;
use App\Services\Sync\SyncOp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Sync receiver for Spatie's `model_has_permissions` pivot. Mirrors
 * ModelHasRoleReceiver — same wire shape, different pivot table.
 */
class ModelHasPermissionReceiver extends DefaultReceiver
{
    public function apply(array $event): string
    {
        return OutboxWriter::mute(function () use ($event) {
            try {
                $payload = $event['payload'] ?? [];
                $modelUuid = $payload['model_uuid'] ?? null;
                $modelType = $payload['model_type'] ?? null;
                $permissionName = $payload['permission_name'] ?? null;
                $guardName = $payload['guard_name'] ?? config('auth.defaults.guard', 'web');

                if (! $modelUuid || ! $modelType || ! $permissionName) {
                    return AckStatus::SKIPPED;
                }

                $modelId = $this->resolveModelId($modelType, (string) $modelUuid);
                $permissionId = $this->resolvePermissionId((string) $permissionName, (string) $guardName);

                if ($modelId === null || $permissionId === null) {
                    Log::warning('sync.model_has_permissions.unresolved', [
                        'model_uuid' => $modelUuid,
                        'model_type' => $modelType,
                        'permission_name' => $permissionName,
                        'guard_name' => $guardName,
                    ]);

                    return AckStatus::SKIPPED;
                }

                $columns = config('permission.column_names');
                $pivotKey = $columns['permission_pivot_key'] ?? 'permission_id';
                $morphKey = $columns['model_morph_key'] ?? 'model_id';
                $table = config('permission.table_names.model_has_permissions');

                $where = [
                    $pivotKey => $permissionId,
                    $morphKey => $modelId,
                    'model_type' => $modelType,
                ];

                if ($event['op'] === SyncOp::DELETE) {
                    DB::table($table)->where($where)->delete();
                } else {
                    DB::table($table)->updateOrInsert($where, $where);
                }

                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return AckStatus::OK;
            } catch (Throwable $e) {
                report($e);

                return AckStatus::ERROR;
            }
        });
    }

    public function snapshot(string $entityType, int|string $afterId, int $limit): array
    {
        $columns = config('permission.column_names');
        $pivotKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $morphKey = $columns['model_morph_key'] ?? 'model_id';
        $pivotTable = config('permission.table_names.model_has_permissions');
        $permissionsTable = config('permission.table_names.permissions');

        $rows = DB::table($pivotTable.' as p')
            ->join($permissionsTable.' as perm', 'perm.id', '=', 'p.'.$pivotKey)
            ->select([
                'perm.name as permission_name',
                'perm.guard_name as guard_name',
                'p.model_type as model_type',
                'p.'.$morphKey.' as model_id',
            ])
            ->orderBy('perm.id')
            ->orderBy('p.model_type')
            ->orderBy('p.'.$morphKey)
            ->get();

        $output = [];

        foreach ($rows as $row) {
            $modelUuid = $this->lookupModelUuid($row->model_type, (int) $row->model_id);

            if ($modelUuid === null) {
                continue;
            }

            $output[] = [
                'entity_id' => $this->entityId($modelUuid, $row->model_type, $row->permission_name, $row->guard_name),
                'permission_name' => $row->permission_name,
                'guard_name' => $row->guard_name,
                'model_uuid' => $modelUuid,
                'model_type' => $row->model_type,
            ];
        }

        return [
            'rows' => $output,
            'next_after_id' => 0,
            'has_more' => false,
        ];
    }

    public static function entityId(string $modelUuid, string $modelType, string $permissionName, string $guardName): string
    {
        return $modelUuid.'|'.$modelType.'|'.$permissionName.'|'.$guardName;
    }

    private function resolveModelId(string $modelType, string $uuid): ?int
    {
        if (! class_exists($modelType)) {
            return null;
        }

        return DB::table((new $modelType)->getTable())
            ->where('uuid', $uuid)
            ->value('id');
    }

    private function resolvePermissionId(string $name, string $guardName): ?int
    {
        return DB::table(config('permission.table_names.permissions'))
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->value('id');
    }

    private function lookupModelUuid(string $modelType, int $modelId): ?string
    {
        if (! class_exists($modelType)) {
            return null;
        }

        return DB::table((new $modelType)->getTable())
            ->where('id', $modelId)
            ->value('uuid');
    }
}
