<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\KioskPayment;
use App\Services\PosSaleService;
use App\Support\SaleTransactionPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KioskPaymentsController extends Controller
{
    public function __construct(
        private PosSaleService $posSaleService,
    ) {}

    public function pending(): JsonResponse
    {
        $now = Carbon::now();

        $payments = KioskPayment::query()
            ->select(['id', 'reference', 'name', 'phone', 'amount', 'base_amount', 'discount_type', 'created_at', 'expires_at'])
            ->whereNull('paymongo_payment_intent_id')
            ->where('status', KioskPayment::STATUS_PENDING)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderBy('created_at')
            ->get()
            ->map(fn (KioskPayment $payment) => $this->serialize($payment))
            ->all();

        return response()->json(['payments' => $payments]);
    }

    public function confirm(string $reference): JsonResponse
    {
        $transaction = DB::transaction(function () use ($reference) {
            $payment = KioskPayment::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages([
                    'reference' => ['Kiosk payment not found.'],
                ]);
            }

            if ($payment->isOnline()) {
                throw ValidationException::withMessages([
                    'method' => ['Online payments are confirmed by PayMongo, not by panel staff.'],
                ]);
            }

            if ($payment->status === KioskPayment::STATUS_PAID) {
                abort(409, 'This payment has already been confirmed.');
            }

            if ($payment->status !== KioskPayment::STATUS_PENDING) {
                abort(409, 'This payment is no longer pending.');
            }

            if ($payment->isExpired()) {
                $payment->update(['status' => KioskPayment::STATUS_EXPIRED]);
                abort(409, 'This payment has expired.');
            }

            $now = Carbon::now();

            $payment->update([
                'status' => KioskPayment::STATUS_PAID,
                'paid_at' => $now,
                'consumed_at' => $now,
            ]);

            return $this->posSaleService->recordKioskWalkInSale(
                $payment->fresh(),
                auth()->user(),
            );
        });

        $transaction->load(['member:id,name', 'processedBy:id,name']);

        return response()->json(SaleTransactionPresenter::panelArray($transaction), 201);
    }

    public function cancel(string $reference): JsonResponse
    {
        $payment = KioskPayment::query()
            ->where('reference', $reference)
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'reference' => ['Kiosk payment not found.'],
            ]);
        }

        if ($payment->isOnline()) {
            throw ValidationException::withMessages([
                'method' => ['Online payments cannot be cancelled from the panel.'],
            ]);
        }

        if ($payment->status === KioskPayment::STATUS_PAID) {
            abort(409, 'This payment has already been confirmed.');
        }

        if ($payment->status !== KioskPayment::STATUS_PENDING) {
            abort(409, 'This payment is no longer pending.');
        }

        $payment->update(['status' => KioskPayment::STATUS_CANCELLED]);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(KioskPayment $payment): array
    {
        return [
            'reference' => $payment->reference,
            'name' => $payment->name,
            'phone' => $payment->phone,
            'base_amount' => $payment->base_amount !== null
                ? round((float) $payment->base_amount, 2)
                : round((float) $payment->amount, 2),
            'amount' => round((float) $payment->amount, 2),
            'discount_type' => $payment->discount_type,
            'discount_percent' => $payment->discount_type !== null ? KioskPayment::DISCOUNT_PERCENT : 0,
            'created_at' => $payment->created_at?->toISOString(),
            'expires_at' => $payment->expires_at?->toISOString(),
        ];
    }
}
