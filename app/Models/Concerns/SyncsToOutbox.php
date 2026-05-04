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
     * attribute. Override in the model to prune (drop heavy JSON
     * blobs, computed mirror columns, or sensitive fields).
     *
     * @return array<string, mixed>
     */
    public function syncableAttributes(): array
    {
        return $this->getAttributes();
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
