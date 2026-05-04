<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists model mutations to the sync_outbox table.
 *
 * The outbox is the durable buffer between this instance and its
 * counterpart. Models annotated with the SyncsToOutbox trait call
 * write() on every create/update/delete/restore.
 *
 * Receivers that apply incoming events from the other side wrap their
 * saves in mute() so the apply doesn't bounce a fresh event back.
 */
class OutboxWriter
{
    private static int $muted = 0;

    /** @var array<class-string, ?string> */
    private static array $entityTypeCache = [];

    /**
     * Run a callback with outbox emission suppressed.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function mute(callable $callback): mixed
    {
        self::$muted++;
        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    public static function isMuted(): bool
    {
        return self::$muted > 0;
    }

    public function write(Model $model, string $op): void
    {
        if (self::isMuted()) {
            return;
        }

        // 'standalone' = single-node deploy: don't accumulate events
        // that would never be drained.
        if (config('sync.role', SyncRole::STANDALONE) === SyncRole::STANDALONE) {
            return;
        }

        if (! in_array(SyncsToOutbox::class, class_uses_recursive($model::class), true)) {
            return;
        }

        $entityType = $this->resolveEntityType($model);

        if ($entityType === null) {
            return;
        }

        DB::table('sync_outbox')->insert([
            'event_id' => (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $model->syncEntityKey(),
            'op' => $op,
            'payload' => json_encode($this->buildPayload($model), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'origin_node' => (string) config('sync.node_id', config('sync.role')),
            'occurred_at' => now(),
            'pushed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Model $model): array
    {
        $attributes = $model->syncableAttributes();

        foreach ((array) config('sync.redacted_attributes', []) as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }

    private function resolveEntityType(Model $model): ?string
    {
        $class = $model::class;

        if (array_key_exists($class, self::$entityTypeCache)) {
            return self::$entityTypeCache[$class];
        }

        foreach ((array) config('sync.entities', []) as $type => $config) {
            if (($config['model'] ?? null) === $class) {
                return self::$entityTypeCache[$class] = $type;
            }
        }

        return self::$entityTypeCache[$class] = null;
    }
}
