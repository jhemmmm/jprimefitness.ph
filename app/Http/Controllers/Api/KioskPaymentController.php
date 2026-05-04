<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KioskPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KioskPaymentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:32'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $amountPesos = (float) ($data['amount'] ?? config('services.kiosk.walk_in_amount', 150));
        $timeoutSec = (int) config('services.kiosk.payment_timeout_seconds', 60);

        $reference = 'kio_'.now()->format('Ymd').'_'.Str::lower(Str::random(10));

        $payment = KioskPayment::create([
            'reference' => $reference,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'amount_centavos' => (int) round($amountPesos * 100),
            'status' => KioskPayment::STATUS_PENDING,
            'qr_data' => $this->buildGcashQrPayload($reference, $amountPesos),
            'expires_at' => Carbon::now()->addSeconds($timeoutSec),
            'paid_at' => null,
        ]);

        return response()->json([
            'reference' => $payment->reference,
            'qr_data_url' => $payment->qr_data,
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ], 201);
    }

    public function show(string $reference): JsonResponse
    {
        $payment = KioskPayment::where('reference', $reference)->first();

        if (! $payment) {
            return response()->json(['status' => 'expired'], 404);
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => KioskPayment::STATUS_EXPIRED]);
        }

        return response()->json([
            'status' => $this->normalizeStatus($payment->status),
        ]);
    }

    /**
     * Mark a pending kiosk payment as paid.
     *
     * NOTE: this is currently a placeholder for a real GCash/PayMongo verification
     * webhook. It is reachable only with the kiosk token. Once a verified payment
     * provider callback is wired, this endpoint should be removed and only the
     * verified callback may flip status to PAID.
     */
    public function confirm(string $reference): JsonResponse
    {
        $payment = KioskPayment::where('reference', $reference)->first();

        if (! $payment) {
            return response()->json(['status' => 'expired'], 404);
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => KioskPayment::STATUS_EXPIRED]);

            return response()->json(['status' => 'expired'], 409);
        }

        if ($payment->status === KioskPayment::STATUS_PAID) {
            return response()->json(['status' => 'paid']);
        }

        if ($payment->status !== KioskPayment::STATUS_PENDING) {
            return response()->json(['status' => $this->normalizeStatus($payment->status)], 409);
        }

        $payment->update([
            'status' => KioskPayment::STATUS_PAID,
            'paid_at' => Carbon::now(),
        ]);

        return response()->json(['status' => 'paid']);
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            KioskPayment::STATUS_PAID => 'paid',
            KioskPayment::STATUS_EXPIRED, KioskPayment::STATUS_CANCELLED => 'expired',
            default => 'pending',
        };
    }

    private function buildGcashQrPayload(string $reference, float $amountPesos): string
    {
        // Placeholder: real GCash integration would return a merchant-issued QR.
        return sprintf(
            'gcash://pay?merchant=jprimefitness&ref=%s&amount=%s',
            urlencode($reference),
            number_format($amountPesos, 2, '.', '')
        );
    }
}
