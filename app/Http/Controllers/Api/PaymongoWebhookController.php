<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KioskPayment;
use App\Models\MemberSubscription;
use App\Services\MemberActivationService;
use App\Services\PaymongoPaymentService;
use App\Services\PosSaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymongoWebhookController extends Controller
{
    public function __construct(
        private PaymongoPaymentService $paymongoPaymentService,
        private MemberActivationService $memberActivationService,
        private PosSaleService $posSaleService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature');

        if (! $this->paymongoPaymentService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('PayMongo webhook signature rejected', [
                'ip' => $request->ip(),
                'has_signature' => $signature !== null,
            ]);

            // 200 so PayMongo (and stray internet traffic) does not retry.
            return response()->json(['ok' => false, 'reason' => 'invalid_signature']);
        }

        $body = (array) json_decode($payload, true);
        $event = $this->paymongoPaymentService->parseWebhookEvent($body);

        if (! $event['supported']) {
            return response()->json(['ok' => true, 'ignored' => $event['type']]);
        }

        if (! empty($event['metadata']['subscription_id'])) {
            return $this->activateSubscription((int) $event['metadata']['subscription_id'], $event['event_id']);
        }

        if ($event['payment_intent_id']) {
            return $this->markKioskPaidByIntent($event['payment_intent_id'], $event['event_id']);
        }

        Log::warning('PayMongo webhook missing recognizable metadata', ['body' => $body]);

        return response()->json(['ok' => true, 'ignored' => 'no_metadata_match']);
    }

    private function activateSubscription(int $subscriptionId, ?string $eventId): JsonResponse
    {
        $subscription = MemberSubscription::with(['member', 'ratePlan'])->find($subscriptionId);

        if (! $subscription) {
            return response()->json(['ok' => true, 'ignored' => 'subscription_not_found']);
        }

        $this->memberActivationService->activate($subscription, [
            'source' => 'paymongo_webhook',
            'paymongo_event_id' => $eventId,
        ]);

        return response()->json(['ok' => true]);
    }

    private function markKioskPaidByIntent(string $paymentIntentId, ?string $eventId): JsonResponse
    {
        // Locked Eloquent update, not a query-builder one: it stays idempotent across replays
        // and fires the model events, so the paid status reaches the kiosk's node via sync.
        $payment = DB::transaction(function () use ($paymentIntentId): ?KioskPayment {
            $payment = KioskPayment::where('paymongo_payment_intent_id', $paymentIntentId)->lockForUpdate()->first();

            if (! $payment || $payment->status === KioskPayment::STATUS_PAID) {
                return null;
            }

            $payment->update([
                'status' => KioskPayment::STATUS_PAID,
                'paid_at' => Carbon::now(),
            ]);

            // Record the sale now: the kiosk may already have left the QR screen (idle timeout,
            // crash, network), and money PayMongo collected must still reach the transaction history.
            $this->posSaleService->recordAutomatedKioskWalkInSale($payment);

            return $payment;
        });

        if ($payment) {
            Log::info('Kiosk payment marked paid via PayMongo', [
                'payment_intent_id' => $paymentIntentId,
                'reference' => $payment->reference,
                'paymongo_event_id' => $eventId,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
