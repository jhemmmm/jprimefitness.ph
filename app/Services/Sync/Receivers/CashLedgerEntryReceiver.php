<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Saves the receipt image that CashLedgerEntry::syncableAttributes() ships with the row.
 */
class CashLedgerEntryReceiver extends DefaultReceiver
{
    protected function preparePayload(array $payload): array
    {
        $file = Arr::pull($payload, 'receipt_file');
        $path = (string) ($payload['receipt_path'] ?? '');

        // the path comes off the wire: only ever write inside the receipts folder
        if ($file && str_starts_with($path, 'cash-receipts/') && ! str_contains($path, '..') && ! Storage::exists($path)) {
            Storage::put($path, base64_decode($file));
        }

        return parent::preparePayload($payload);
    }
}
