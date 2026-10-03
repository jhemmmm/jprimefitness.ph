<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Sync\OutboxWriter;
use App\Services\Sync\SyncOp;
use Illuminate\Support\Str;

/**
 * Models that opt into cross-instance sync use this trait. It does two things:
 *
 *   1. Auto-generates a `uuid` on creating() if the row doesn't have one,
 *      so any side that creates a row produces a stable cross-instance key.
 *   2. Hooks created/updated/deleted/restored Eloquent events and writes
 *      a row to sync_outbox via OutboxWriter.
 *
 * Models can override syncableAttributes() to filter which attributes
 * appear in the outbox payload, syncsUuid() to opt out of uuid
 * auto-generation, or syncEntityKey() to use a different natural key.
 */
trait SyncsToOutbox
{
    public static function bootSyncsToOutbox(): void
    {
        static::creating(function ($model) {
            if (! $model->syncsUuid()) {
                return;
            }

            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });

        static::created(fn ($model) => app(OutboxWriter::class)->write($model, SyncOp::CREATE));
        static::updated(fn ($model) => app(OutboxWriter::class)->write($model, SyncOp::UPDATE));
        static::deleted(fn ($model) => app(OutboxWriter::class)->write($model, SyncOp::DELETE));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => app(OutboxWriter::class)->write($model, SyncOp::RESTORE));
        }
    }

    /**
     * Attributes serialized into the outbox payload. Default: every
     * attribute, with JSON-cast columns decoded so the wire format is
     * `{"metadata": {"k":"v"}}` rather than a double-encoded string.
     * Without this, the receiver re-runs the JSON cast on the inbound
     * string and stores `"{\"k\":\"v\"}"` in the column, which reads
     * back as a string and breaks any consumer expecting an array.
     *
     * Override in the model to prune (drop heavy JSON blobs, computed
     * mirror columns, or sensitive fields). When overriding, call
     * `decodeJsonCastAttributes()` if you still want the JSON-decoding
     * behavior on whatever subset you return.
     *
     * @return array<string, mixed>
     */
    public function syncableAttributes(): array
    {
        return $this->decodeJsonCastAttributes($this->getAttributes());
    }

    /**
     * Decode raw JSON strings stored in `$this->attributes` for any
     * column declared with a JSON-storage cast (`array`, `json`,
     * `object`, `collection`, and their `encrypted:*` variants). Other
     * column types pass through untouched.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function decodeJsonCastAttributes(array $attributes): array
    {
        $casts = $this->getCasts();

        foreach ($attributes as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (! isset($casts[$key]) || ! $this->castStoresJson($casts[$key])) {
                continue;
            }

            $decoded = json_decode($value, true);

            // json_decode returns null both for the literal string "null"
            // and on a parse error; only swap when the result actually
            // round-trips to JSON, otherwise we'd corrupt non-JSON data
            // that happens to live in a JSON-cast column.
            if ($decoded === null && strtolower(trim($value)) !== 'null') {
                continue;
            }

            $attributes[$key] = $decoded;
        }

        return $attributes;
    }

    private function castStoresJson(string $cast): bool
    {
        $cast = strtolower(trim($cast));

        if (in_array($cast, ['array', 'json', 'object', 'collection'], true)) {
            return true;
        }

        foreach (['encrypted:array', 'encrypted:json', 'encrypted:object', 'encrypted:collection'] as $prefix) {
            if ($cast === $prefix || str_starts_with($cast, $prefix.':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this model auto-generates a uuid on creating. Override
     * to false for models that have a different natural key column
     * (e.g. KioskPayment.reference, HikvisionEventLog composite).
     */
    public function syncsUuid(): bool
    {
        return true;
    }

    /**
     * The cross-instance stable key used as outbox.entity_id. Default
     * is the uuid (or primary key as fallback). Override for models
     * with a different natural key.
     */
    public function syncEntityKey(): string
    {
        return (string) ($this->getAttribute('uuid') ?: $this->getKey());
    }
}
