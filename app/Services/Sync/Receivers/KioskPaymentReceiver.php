<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Models\KioskPayment;
use Illuminate\Database\Eloquent\Model;

class KioskPaymentReceiver extends DefaultReceiver
{
    protected function locateExisting(array $payload, string $entityId, string $entityType = ''): ?Model
    {
        $reference = $payload['reference'] ?? $entityId;

        if (! $reference) {
            return null;
        }

        return KioskPayment::query()->where('reference', $reference)->first();
    }

    /** Payments only move forward (pending, then expired or cancelled, or paid), whatever either node's clock says. */
    private const STATUS_RANK = [
        KioskPayment::STATUS_PENDING => 0,
        KioskPayment::STATUS_EXPIRED => 1,
        KioskPayment::STATUS_CANCELLED => 1,
        KioskPayment::STATUS_PAID => 2,
    ];

    /**
     * PayMongo marks rows paid on live while the kiosk's node may lazily expire the same row
     * before the pull lands, so status progress outranks updated_at; ties fall back to the clock.
     */
    protected function incomingIsStale(Model $existing, array $payload): bool
    {
        $existingRank = self::STATUS_RANK[$existing->getAttribute('status')] ?? 0;
        $incomingRank = self::STATUS_RANK[$payload['status'] ?? null] ?? 0;

        if ($existingRank !== $incomingRank) {
            return $incomingRank < $existingRank;
        }

        return parent::incomingIsStale($existing, $payload);
    }
}
