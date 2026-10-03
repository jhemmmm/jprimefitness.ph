<?php

namespace App\Observers;

use App\Models\CashLedgerEntry;
use App\Models\SaleTransaction;
use App\Services\CashDrawerService;

class SaleTransactionObserver
{
    /**
     * Record completed cash sales into the cash drawer ledger.
     */
    public function created(SaleTransaction $sale): void
    {
        if (! config('jprime.cash_drawer')
            || $sale->payment_method !== SaleTransaction::PAYMENT_METHOD_CASH
            || $sale->status !== SaleTransaction::STATUS_COMPLETED) {
            return;
        }

        app(CashDrawerService::class)->recordSourceEntry(
            CashLedgerEntry::TYPE_SALE,
            round((float) $sale->total, 2),
            'sale_transaction',
            $sale->id,
            trim($sale->receiptNumber().' '.($sale->item_name ?? 'Sale')),
            $sale->processed_by,
            $sale->sold_at,
        );
    }

    /**
     * Voiding a cash sale takes the refund out of the drawer as a reversal entry.
     */
    public function updated(SaleTransaction $sale): void
    {
        if (! config('jprime.cash_drawer')
            || ! $sale->wasChanged('status')
            || $sale->status !== SaleTransaction::STATUS_VOIDED
            || $sale->payment_method !== SaleTransaction::PAYMENT_METHOD_CASH) {
            return;
        }

        app(CashDrawerService::class)->recordSourceEntry(
            CashLedgerEntry::TYPE_SALE_VOID,
            -round((float) $sale->total, 2),
            'sale_transaction',
            $sale->id,
            'Void '.$sale->receiptNumber(),
            $sale->voided_by,
            $sale->voided_at,
        );
    }
}
