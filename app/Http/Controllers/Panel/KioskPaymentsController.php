<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\KioskPayment;
use App\Models\SystemActivity;
use App\Services\PosSaleService;
use App\Services\SystemActivityService;
use App\Support\SaleTransactionPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KioskPaymentsController extends Controller
{
    public function __construct(
        private PosSaleService $posSaleService,
        private SystemActivityService $systemActivityService,
    ) {}

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

    public function cancel(Request $request, string $reference): JsonResponse
    {
        // Same transaction + row lock as confirm(): without it the lock is released at the end of
        // the SELECT and a concurrent confirm() can record a sale that this cancel then overwrites.
        DB::transaction(function () use ($request, $reference): void {
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

            // Validated after the state guards so a stale row reports why it cannot be cancelled
            // rather than complaining about a missing reason.
            $data = $request->validate([
                'reason' => ['required', 'string', 'max:2000'],
            ]);

            $actor = auth()->user();

            $payment->update([
                'status' => KioskPayment::STATUS_CANCELLED,
                'cancellation_reason' => $data['reason'],
                'cancelled_by' => $actor?->id,
                'cancelled_at' => now(),
            ]);

            $this->systemActivityService->recordSubjectEvent(
                SystemActivity::SUBJECT_KIOSK_PAYMENT,
                $payment->id,
                'cancelled',
                [
                    'reference' => $payment->reference,
                    'name' => $payment->name,
                    'amount' => round((float) $payment->amount, 2),
                ],
                ['reason' => $data['reason']],
                $actor?->id,
                $actor?->name,
                now(),
            );
        });

        return response()->json(['ok' => true]);
    }
}
