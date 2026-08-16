<?php

declare(strict_types=1);

namespace App\Services\Sync\Receivers;

use App\Models\SaleTransaction;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class MemberPtPackageReceiver extends DefaultReceiver
{
    /**
     * Resolve the sender's stable sale UUID to this node's local primary key.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function beforeWrite(array $payload, ?Model $existing): array
    {
        $hasSaleTransactionUuid = array_key_exists('sale_transaction_uuid', $payload);
        $saleTransactionUuid = $payload['sale_transaction_uuid'] ?? null;

        unset($payload['sale_transaction_uuid'], $payload['sale_transaction_id']);

        $payload = parent::beforeWrite($payload, $existing);

        if (! $hasSaleTransactionUuid) {
            return $payload;
        }

        if ($saleTransactionUuid === null || $saleTransactionUuid === '') {
            $payload['sale_transaction_id'] = null;

            return $payload;
        }

        $saleTransactionId = SaleTransaction::query()
            ->where('uuid', $saleTransactionUuid)
            ->value('id');

        if ($saleTransactionId === null) {
            throw new RuntimeException('The linked PT package sale has not been synchronized yet.');
        }

        $payload['sale_transaction_id'] = (int) $saleTransactionId;

        return $payload;
    }

    /**
     * Replace node-local sale IDs with stable UUIDs in bootstrap snapshots.
     *
     * @return array{rows: array<int, array<string, mixed>>, next_after_id: int|string, has_more: bool}
     */
    public function snapshot(string $entityType, int|string $afterId, int $limit): array
    {
        $page = parent::snapshot($entityType, $afterId, $limit);
        $saleTransactionIds = collect($page['rows'])
            ->pluck('sale_transaction_id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $saleTransactionUuids = SaleTransaction::query()
            ->whereIn('id', $saleTransactionIds)
            ->pluck('uuid', 'id');

        $page['rows'] = collect($page['rows'])
            ->map(function (array $row) use ($saleTransactionUuids): array {
                $saleTransactionId = $row['sale_transaction_id'] ?? null;
                $row['sale_transaction_uuid'] = $saleTransactionId
                    ? $saleTransactionUuids->get((int) $saleTransactionId)
                    : null;

                unset($row['sale_transaction_id']);

                return $row;
            })
            ->all();

        return $page;
    }
}
