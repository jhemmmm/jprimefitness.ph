<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;

class SyncStateRepository
{
    public function get(string $key, ?string $default = null): ?string
    {
        $row = DB::table('sync_state')->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $now = now();

        // Atomic upsert: MySQL reports 0 affected rows for an UPDATE that changes
        // nothing (same value within the same second), so update-then-insert
        // collided on the primary key.
        DB::table('sync_state')->upsert(
            ['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now],
            ['key'],
            ['value', 'updated_at'],
        );
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null ? $default : (int) $value;
    }
}
