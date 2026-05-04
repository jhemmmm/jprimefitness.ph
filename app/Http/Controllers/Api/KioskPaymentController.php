<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KioskPayment;
use App\Models\RatePlan;
use App\Services\PaymongoPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class KioskPaymentController extends Controller
{
    public function __construct(private PaymongoPaymentService $paymongoPaymentService) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:32'],
            'method' => ['nullable', 'string', 'in:online,cash'],
        ]);

        $amount = $this->resolveWalkInAmount();
        $timeoutSec = (int) config('services.kiosk.payment_timeout_seconds', 120);
        $method = $data['method'] ?? 'online';

        $payment = KioskPayment::create([
            'reference' => 'kio_'.now()->format('Ymd').'_'.Str::lower(Str::random(10)),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'amount' => $amount,
            'status' => KioskPayment::STATUS_PENDING,
            'qr_data' => null,
            'expires_at' => Carbon::now()->addSeconds($timeoutSec),
            'paid_at' => null,
        ]);

        Log::info('Created kiosk payment', ['reference' => $payment->reference, 'method' => $method]);

        $qrImageUrl = $this->maybeAttachQrph($payment, $method);

        return response()->json([
            'reference' => $payment->reference,
            'method' => $payment->isOnline() ? 'online' : 'cash',
            'qr_data_url' => $payment->qr_data,
            'qr_image_url' => $qrImageUrl,
            'amount' => $payment->amount,
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ], 201);
    }

    private function resolveWalkInAmount(): float
    {
        $plan = RatePlan::query()
            ->where('is_active', true)
            ->where('is_walk_in_only', true)
            ->orderBy('duration_days')
            ->orderBy('id')
            ->first();

        if (! $plan) {
            throw new \RuntimeException('No active walk-in rate plan configured. Add one under Pricing & Rates.');
        }

        return (float) $plan->price;
    }

    private function maybeAttachQrph(KioskPayment $payment, string $method): ?string
    {
        if ($method !== 'online' || ! $this->paymongoPaymentService->isConfigured()) {
            return null;
        }

        try {
            $qrph = $this->paymongoPaymentService->createWalkInQrphPayment($payment);
        } catch (Throwable $e) {
            Log::warning('Kiosk PayMongo QRPh creation failed; falling back to cash', [
                'reference' => $payment->reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $payment->forceFill([
            'qr_data' => $qrph['qr_raw'],
            'paymongo_payment_intent_id' => $qrph['payment_intent_id'],
        ])->save();

        return $qrph['qr_image_url'];
    }

    public function show(string $reference): JsonResponse
    {
        $payment = KioskPayment::where('reference', $reference)->first();

        if (! $payment) {
            return response()->json(['status' => 'expired'], 404);
        }

        if ($payment->isExpired()) {
            // isExpired() returns false once status flips to EXPIRED, so this UPDATE
            // only fires on the first poll past the window.
            $payment->update(['status' => KioskPayment::STATUS_EXPIRED]);
        }

        return response()->json([
            'status' => $this->normalizeStatus($payment->status),
        ]);
    }

    /**
     * Manually mark a kiosk payment as paid. Only valid for the cash flow —
     * online sessions (those with a paymongo_payment_intent_id) are confirmed
     * by the PayMongo webhook, never by this endpoint.
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

        if ($payment->isOnline()) {
            return response()->json([
                'status' => 'pending',
                'message' => 'Online payments are confirmed by PayMongo, not by this endpoint.',
            ], 409);
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
}
