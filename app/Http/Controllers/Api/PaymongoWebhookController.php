<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberSubscription;
use App\Services\MemberActivationService;
use App\Services\PaymongoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymongoWebhookController extends Controller
{
    public function __construct(
        private PaymongoPaymentService $paymongoPaymentService,
        private MemberActivationService $memberActivationService,
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

            // Return 200 so PayMongo (and stray internet traffic) does not retry.
            return response()->json(['ok' => false, 'reason' => 'invalid_signature']);
        }

        $body = json_decode($payload, true);
        $eventType = $body['data']['attributes']['type'] ?? null;

        if ($eventType !== 'checkout_session.payment.paid') {
            return response()->json(['ok' => true, 'ignored' => $eventType]);
        }

        $metadata = $body['data']['attributes']['data']['attributes']['metadata']
            ?? $body['data']['attributes']['metadata']
            ?? [];
        $subscriptionId = isset($metadata['subscription_id']) ? (int) $metadata['subscription_id'] : null;

        if (! $subscriptionId) {
            Log::warning('PayMongo webhook missing subscription_id metadata', ['body' => $body]);

            return response()->json(['ok' => true, 'ignored' => 'no_subscription_id']);
        }

        $subscription = MemberSubscription::with(['member', 'ratePlan'])->find($subscriptionId);
        if (! $subscription) {
            return response()->json(['ok' => true, 'ignored' => 'subscription_not_found']);
        }

        $this->memberActivationService->activate($subscription, [
            'source' => 'paymongo_webhook',
            'paymongo_event_id' => $body['data']['id'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
