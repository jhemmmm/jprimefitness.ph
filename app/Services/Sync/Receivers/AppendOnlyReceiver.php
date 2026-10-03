<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Services\Sync\AckStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Mixin for receivers whose entity is append-only - once a row exists
 * for a given key, replays of the same key are silently skipped instead
 * of treated as updates.
 */
trait AppendOnlyReceiver
{
    protected function applyUpsert(?Model $existing, array $payload, array $event): string
    {
        if ($existing) {
            return AckStatus::SKIPPED;
        }

        return parent::applyUpsert(null, $payload, $event);
    }
}
