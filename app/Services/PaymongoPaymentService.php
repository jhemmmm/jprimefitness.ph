<?php

namespace App\Services;

use App\Models\MemberSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymongoPaymentService
{
    private const ENDPOINT = 'https://api.paymongo.com/v1/checkout_sessions';

    /**
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckoutSession(MemberSubscription $subscription, User $user): array
    {
        $secret = (string) config('services.paymongo.secret');
        if ($secret === '') {
            throw new RuntimeException('PayMongo secret key not configured.');
        }

        $subscription->loadMissing('ratePlan');
        $plan = $subscription->ratePlan;

        if (! $plan) {
            throw new RuntimeException('Subscription has no rate plan.');
        }

        $amountCentavos = (int) round(((float) $subscription->sold_price) * 100);
        if ($amountCentavos <= 0) {
            throw new RuntimeException('Cannot start checkout for a zero-amount subscription.');
        }

        $payload = [
            'data' => [
                'attributes' => [
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => sprintf('JPRIME Fitness — %s membership', $plan->name),
                    'success_url' => url((string) config('services.paymongo.success_url', '/register/success')),
                    'cancel_url' => url((string) config('services.paymongo.cancel_url', '/register/cancelled')),
                    'reference_number' => 'sub_'.$subscription->id,
                    'customer_email' => $user->email,
                    'billing' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                    ],
                    'line_items' => [
                        [
                            'currency' => 'PHP',
                            'amount' => $amountCentavos,
                            'name' => $plan->name,
                            'quantity' => 1,
                            'description' => sprintf('%d-day membership', (int) $plan->duration_days),
                        ],
                    ],
                    'payment_method_types' => ['gcash', 'paymaya', 'card', 'grab_pay'],
                    'metadata' => [
                        'subscription_id' => (string) $subscription->id,
                        'user_id' => (string) $user->id,
                    ],
                ],
            ],
        ];

        $response = Http::withBasicAuth($secret, '')
            ->acceptJson()
            ->asJson()
            ->post(self::ENDPOINT, $payload);

        if (! $response->successful()) {
            Log::error('PayMongo checkout creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to create PayMongo checkout session.');
        }

        $body = $response->json();
        $id = $body['data']['id'] ?? null;
        $checkoutUrl = $body['data']['attributes']['checkout_url'] ?? null;

        if (! $id || ! $checkoutUrl) {
            throw new RuntimeException('PayMongo response missing checkout URL.');
        }

        return [
            'id' => (string) $id,
            'checkout_url' => (string) $checkoutUrl,
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');
        if ($secret === '' || $signatureHeader === null) {
            return false;
        }

        // PayMongo's signature header format: t=<timestamp>,te=<sig_test>,li=<sig_live>
        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $kv = explode('=', trim($segment), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        $timestamp = $parts['t'] ?? null;
        $candidates = array_filter([$parts['te'] ?? null, $parts['li'] ?? null]);

        if (! $timestamp || $candidates === []) {
            return false;
        }

        // Reject stale signatures so a captured payload cannot be replayed.
        $tolerance = (int) config('services.paymongo.webhook_tolerance_seconds', 300);
        $delta = abs(time() - (int) $timestamp);
        if ($tolerance > 0 && $delta > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($candidates as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
