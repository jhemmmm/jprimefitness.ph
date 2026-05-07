<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Services\Sync\AckStatus;
use App\Services\Sync\OutboxWriter;
use App\Services\Sync\SyncOp;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Sync receiver for Spatie's `roles` table. Uses uuid as the cross-
 * instance stable key, falling back to (name, guard_name) so rows that
 * pre-date the uuid column converge to live's uuid on first apply.
 */
class RoleReceiver extends DefaultReceiver
{
    public function apply(array $event): string
    {
        return OutboxWriter::mute(function () use ($event) {
            try {
                $payload = $event['payload'] ?? [];
                $name = $payload['name'] ?? null;
                $guard = $payload['guard_name'] ?? config('auth.defaults.guard', 'web');
                $uuid = $payload['uuid'] ?? null;

                if (! $name && ! $uuid) {
                    return AckStatus::SKIPPED;
                }

                $table = config('permission.table_names.roles');

                if ($event['op'] === SyncOp::DELETE) {
                    $query = DB::table($table);
                    if ($uuid) {
                        $query->where('uuid', $uuid);
                    } else {
                        $query->where('name', $name)->where('guard_name', $guard);
                    }
                    $query->delete();
                    app(PermissionRegistrar::class)->forgetCachedPermissions();

                    return AckStatus::OK;
                }

                $existing = $this->locateRow($table, $uuid, $name, $guard);

                $values = [
                    'name' => $name ?? ($existing->name ?? null),
                    'guard_name' => $guard,
                    'updated_at' => $payload['updated_at'] ?? now(),
                ];

                if ($uuid) {
                    $values['uuid'] = $uuid;
                }

                if ($existing) {
                    DB::table($table)->where('id', $existing->id)->update($values);
                } else {
                    $values['created_at'] = $payload['created_at'] ?? now();
                    DB::table($table)->insert($values);
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
        $table = config('permission.table_names.roles');

        $rows = DB::table($table)
            ->where('id', '>', (int) $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $payload = $rows->map(function ($row) {
            $arr = (array) $row;
            unset($arr['id']);
            $arr['entity_id'] = (string) ($row->uuid ?? self::naturalKey((string) $row->name, (string) $row->guard_name));

            return $arr;
        })->all();

        $lastId = $rows->isNotEmpty() ? (int) $rows->last()->id : (int) $afterId;

        return [
            'rows' => $payload,
            'next_after_id' => $lastId,
            'has_more' => $rows->count() === $limit,
        ];
    }

    /**
     * Build the natural-key entity_id used by legacy snapshots that
     * pre-date the uuid column. Kept for backwards compatibility with
     * already-deployed local nodes; new emissions key by uuid.
     */
    public static function entityId(string $name, string $guardName): string
    {
        return self::naturalKey($name, $guardName);
    }

    private static function naturalKey(string $name, string $guardName): string
    {
        return $name.'|'.$guardName;
    }

    private function locateRow(string $table, ?string $uuid, ?string $name, string $guard): ?object
    {
        if ($uuid) {
            $row = DB::table($table)->where('uuid', $uuid)->first();
            if ($row) {
                return $row;
            }
        }

        if ($name) {
            return DB::table($table)
                ->where('name', $name)
                ->where('guard_name', $guard)
                ->first();
        }

        return null;
    }
}
