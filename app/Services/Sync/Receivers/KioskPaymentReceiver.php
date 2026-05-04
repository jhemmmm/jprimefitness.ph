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
}
