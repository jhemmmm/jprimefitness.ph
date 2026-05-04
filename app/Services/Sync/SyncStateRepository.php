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

        $updated = DB::table('sync_state')
            ->where('key', $key)
            ->update(['value' => $value, 'updated_at' => $now]);

        if ($updated === 0) {
            DB::table('sync_state')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null ? $default : (int) $value;
    }
}
