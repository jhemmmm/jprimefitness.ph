<?php

namespace App\Support;

use App\Models\SaleTransaction;

class SaleTransactionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function panelArray(SaleTransaction $transaction): array
    {
        $details = $transaction->details ?? [];
        $discount = data_get($details, 'discount');
        $subtotal = data_get($details, 'subtotal');

        return [
            'id' => $transaction->id,
            'receipt_number' => $transaction->receiptNumber(),
            'member_id' => $transaction->member_id,
            'type' => $transaction->type,
            'total' => round((float) $transaction->total, 2),
            'subtotal' => $subtotal !== null ? round((float) $subtotal, 2) : round((float) $transaction->total, 2),
            'discount' => $discount ? [
                'type' => (string) data_get($discount, 'type'),
                'percent' => (int) data_get($discount, 'percent'),
                'amount' => round((float) data_get($discount, 'amount'), 2),
            ] : null,
            'payment_method' => $transaction->payment_method,
            'payment_method_label' => SaleTransaction::paymentMethodLabel($transaction->payment_method),
            'amount_received' => $transaction->amountReceived(),
            'change_amount' => $transaction->changeAmount(),
            'payment_reference' => $transaction->paymentReference(),
            'processed_by' => $transaction->processedBy?->name,
            'sold_at' => $transaction->sold_at?->toISOString(),
            'customer_name' => $transaction->customer_name ?: $transaction->member?->name,
            'item_name' => $transaction->item_name,
            'details' => $details,
            'receipt_url' => route('panel.sales.receipt', $transaction),
            'membership_qr_url' => $transaction->type === SaleTransaction::TYPE_MEMBERSHIP
                && filled(data_get($details, 'subscription_id'))
                    ? route('panel.sales.membership-qr', $transaction)
                    : null,
            'source_url' => match ($transaction->type) {
                SaleTransaction::TYPE_MEMBERSHIP, SaleTransaction::TYPE_PT_PACKAGE => $transaction->member_id
                    ? route('panel.members.show', $transaction->member_id)
                    : null,
                default => null,
            },
        ];
    }
}
