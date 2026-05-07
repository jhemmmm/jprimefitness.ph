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
 * Sync receiver for Spatie's `role_has_permissions` pivot. Wire format
 * uses (role_name, role_guard, permission_name, permission_guard) and
 * resolves to local ids on apply.
 */
class RoleHasPermissionReceiver extends DefaultReceiver
{
    public function apply(array $event): string
    {
        return OutboxWriter::mute(function () use ($event) {
            try {
                $payload = $event['payload'] ?? [];
                $roleName = $payload['role_name'] ?? null;
                $permissionName = $payload['permission_name'] ?? null;
                $roleGuard = $payload['role_guard'] ?? config('auth.defaults.guard', 'web');
                $permissionGuard = $payload['permission_guard'] ?? config('auth.defaults.guard', 'web');

                if (! $roleName || ! $permissionName) {
                    return AckStatus::SKIPPED;
                }

                $roleId = $this->resolveRoleId($roleName, $roleGuard);
                $permissionId = $this->resolvePermissionId($permissionName, $permissionGuard);

                if ($roleId === null || $permissionId === null) {
                    Log::warning('sync.role_has_permissions.unresolved', [
                        'missing_role' => $roleId === null,
                        'missing_permission' => $permissionId === null,
                        'role_name' => $roleName,
                        'role_guard' => $roleGuard,
                        'permission_name' => $permissionName,
                        'permission_guard' => $permissionGuard,
                    ]);

                    return AckStatus::SKIPPED;
                }

                $columns = config('permission.column_names');
                $rolePivotKey = $columns['role_pivot_key'] ?? 'role_id';
                $permissionPivotKey = $columns['permission_pivot_key'] ?? 'permission_id';
                $table = config('permission.table_names.role_has_permissions');

                $where = [
                    $rolePivotKey => $roleId,
                    $permissionPivotKey => $permissionId,
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
        $rolePivotKey = $columns['role_pivot_key'] ?? 'role_id';
        $permissionPivotKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $pivotTable = config('permission.table_names.role_has_permissions');
        $rolesTable = config('permission.table_names.roles');
        $permissionsTable = config('permission.table_names.permissions');

        $rows = DB::table($pivotTable.' as p')
            ->join($rolesTable.' as r', 'r.id', '=', 'p.'.$rolePivotKey)
            ->join($permissionsTable.' as perm', 'perm.id', '=', 'p.'.$permissionPivotKey)
            ->select([
                'r.name as role_name',
                'r.guard_name as role_guard',
                'perm.name as permission_name',
                'perm.guard_name as permission_guard',
            ])
            ->orderBy('r.id')
            ->orderBy('perm.id')
            ->get();

        $output = $rows->map(fn ($row) => [
            'entity_id' => self::entityId($row->role_name, $row->role_guard, $row->permission_name, $row->permission_guard),
            'role_name' => $row->role_name,
            'role_guard' => $row->role_guard,
            'permission_name' => $row->permission_name,
            'permission_guard' => $row->permission_guard,
        ])->all();

        return [
            'rows' => $output,
            'next_after_id' => 0,
            'has_more' => false,
        ];
    }

    public static function entityId(string $roleName, string $roleGuard, string $permissionName, string $permissionGuard): string
    {
        return $roleName.'|'.$roleGuard.'|'.$permissionName.'|'.$permissionGuard;
    }

    private function resolveRoleId(string $name, string $guardName): ?int
    {
        return DB::table(config('permission.table_names.roles'))
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->value('id');
    }

    private function resolvePermissionId(string $name, string $guardName): ?int
    {
        return DB::table(config('permission.table_names.permissions'))
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->value('id');
    }
}
