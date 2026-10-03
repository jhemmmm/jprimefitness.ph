<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Models\BusinessProfile;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton - locate by uuid first; fall back to the single existing
 * row so we update rather than insert a second.
 */
class BusinessProfileReceiver extends DefaultReceiver
{
    protected function locateExisting(array $payload, string $entityId, string $entityType = ''): ?Model
    {
        return parent::locateExisting($payload, $entityId, $entityType)
            ?? BusinessProfile::query()->orderBy('id')->first();
    }
}
