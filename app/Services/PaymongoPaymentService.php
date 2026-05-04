<?php

namespace App\Services;

use App\Models\KioskPayment;
use App\Models\MemberSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymongoPaymentService
{
    public const EVENT_PAYMENT_PAID = 'payment.paid';

    public const EVENT_CHECKOUT_SESSION_PAID = 'checkout_session.payment.paid';

    public const METHOD_QRPH = 'qrph';

    private const ENDPOINT = 'https://api.paymongo.com/v1/checkout_sessions';

    private const PAYMENT_INTENTS_ENDPOINT = 'https://api.paymongo.com/v1/payment_intents';

    private const PAYMENT_METHODS_ENDPOINT = 'https://api.paymongo.com/v1/payment_methods';

    private const SUPPORTED_WEBHOOK_EVENTS = [
        self::EVENT_PAYMENT_PAID,
        self::EVENT_CHECKOUT_SESSION_PAID,
    ];

    public function isConfigured(): bool
    {
        return (string) config('services.paymongo.secret') !== '';
    }

    /**
     * Create a checkout session for a membership subscription.
     *
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckoutSession(MemberSubscription $subscription, User $user): array
    {
        $subscription->loadMissing('ratePlan');
        $plan = $subscription->ratePlan;

        if (! $plan) {
            throw new RuntimeException('Subscription has no rate plan.');
        }

        $amountCentavos = (int) round(((float) $subscription->sold_price) * 100);
        if ($amountCentavos <= 0) {
            throw new RuntimeException('Cannot start checkout for a zero-amount subscription.');
        }

        return $this->postCheckoutSession([
            'description' => sprintf('JPRIME Fitness — %s membership', $plan->name),
            'reference_number' => 'sub_'.$subscription->id,
            'customer_email' => $user->email,
            'billing' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'line_items' => [[
                'currency' => 'PHP',
                'amount' => $amountCentavos,
                'name' => $plan->name,
                'quantity' => 1,
                'description' => sprintf('%d-day membership', (int) $plan->duration_days),
            ]],
            'metadata' => [
                'subscription_id' => (string) $subscription->id,
                'user_id' => (string) $user->id,
            ],
        ]);
    }

    /**
     * Create a QRPh payment for a kiosk walk-in. The webhook flips the
     * KioskPayment to PAID once payment.paid fires for the underlying intent.
     *
     * @return array{payment_intent_id: string, qr_raw: ?string, qr_image_url: ?string}
     */
    public function createWalkInQrphPayment(KioskPayment $payment): array
    {
        $amountCentavos = (int) round(((float) $payment->amount) * 100);

        if ($amountCentavos <= 0) {
            throw new RuntimeException('Cannot start QRPh payment for a zero-amount walk-in.');
        }

        $secret = $this->requireSecret();

        [$paymentIntentId, $clientKey] = $this->createQrphPaymentIntent($secret, $amountCentavos, $payment->reference);
        $paymentMethodId = $this->createQrphPaymentMethod($secret, $payment);
        $attached = $this->attachPaymentMethod($secret, $paymentIntentId, $paymentMethodId, $clientKey);

        return [
            'payment_intent_id' => $paymentIntentId,
        ] + $this->extractQrFromNextAction($attached, $paymentIntentId);
    }

    /**
     * @return array{0: string, 1: string} [paymentIntentId, clientKey]
     */
    private function createQrphPaymentIntent(string $secret, int $amountCentavos, string $kioskReference): array
    {
        $intent = $this->postJson($secret, self::PAYMENT_INTENTS_ENDPOINT, [
            'data' => [
                'attributes' => [
                    'amount' => $amountCentavos,
                    'currency' => 'PHP',
                    'capture_type' => 'automatic',
                    'payment_method_allowed' => [self::METHOD_QRPH],
                    'description' => 'JPRIME Fitness — Walk-in entry',
                    'statement_descriptor' => 'JPRIME FITNESS',
                    'metadata' => [
                        'kiosk_payment_reference' => $kioskReference,
                    ],
                ],
            ],
        ], 'PayMongo payment intent creation failed');

        $paymentIntentId = $intent['data']['id'] ?? null;
        $clientKey = $intent['data']['attributes']['client_key'] ?? null;

        if (! $paymentIntentId || ! $clientKey) {
            throw new RuntimeException('PayMongo payment intent response missing id or client_key.');
        }

        return [(string) $paymentIntentId, (string) $clientKey];
    }

    private function createQrphPaymentMethod(string $secret, KioskPayment $payment): string
    {
        $method = $this->postJson($secret, self::PAYMENT_METHODS_ENDPOINT, [
            'data' => [
                'attributes' => [
                    'type' => self::METHOD_QRPH,
                    'billing' => [
                        'name' => $payment->name,
                        'phone' => $payment->phone,
                        // PayMongo's QRPh endpoint rejects a payment method without an email,
                        // but the walk-in form only collects name + phone — synthesize a
                        // deterministic placeholder tied to the kiosk reference.
                        'email' => $payment->reference.'@kiosk.jprimefitness.ph',
                    ],
                ],
            ],
        ], 'PayMongo qrph payment method creation failed');

        $paymentMethodId = $method['data']['id'] ?? null;
        if (! $paymentMethodId) {
            throw new RuntimeException('PayMongo payment method response missing id.');
        }

        return (string) $paymentMethodId;
    }

    /**
     * @return array<string, mixed>
     */
    private function attachPaymentMethod(string $secret, string $paymentIntentId, string $paymentMethodId, string $clientKey): array
    {
        return $this->postJson(
            $secret,
            self::PAYMENT_INTENTS_ENDPOINT.'/'.$paymentIntentId.'/attach',
            [
                'data' => [
                    'attributes' => [
                        'payment_method' => $paymentMethodId,
                        'client_key' => $clientKey,
                    ],
                ],
            ],
            'PayMongo qrph attach failed',
        );
    }

    /**
     * @param  array<string, mixed>  $attached
     * @return array{qr_raw: ?string, qr_image_url: ?string}
     */
    private function extractQrFromNextAction(array $attached, string $paymentIntentId): array
    {
        $nextAction = $attached['data']['attributes']['next_action'] ?? [];
        $code = $nextAction['code'] ?? $nextAction['qr_code'] ?? [];

        // PayMongo aliases the QR data across SDK versions: the EMV string lives under
        // raw/raw_data/data; the rendered PNG lives under image_url/url.
        $qrRaw = $code['raw'] ?? $code['raw_data'] ?? $code['data'] ?? null;
        $qrImageUrl = $code['image_url'] ?? $code['url'] ?? null;

        if (! $qrRaw && ! $qrImageUrl) {
            Log::error('PayMongo qrph attach returned no QR data', [
                'payment_intent_id' => $paymentIntentId,
                'next_action' => $nextAction,
            ]);
            throw new RuntimeException('PayMongo did not return a QRPh code.');
        }

        return [
            'qr_raw' => $qrRaw !== null ? (string) $qrRaw : null,
            'qr_image_url' => $qrImageUrl !== null ? (string) $qrImageUrl : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes  PayMongo-specific attributes (description,
     *                                            line_items, billing, metadata, …). The
     *                                            common shape (success/cancel URLs, payment
     *                                            method types, display flags) is filled in
     *                                            here.
     * @return array{id: string, checkout_url: string}
     */
    private function postCheckoutSession(array $attributes): array
    {
        $secret = $this->requireSecret();

        $attributes = array_merge([
            'send_email_receipt' => true,
            'show_description' => true,
            'show_line_items' => true,
            'success_url' => url((string) config('services.paymongo.success_url', '/register/success')),
            'cancel_url' => url((string) config('services.paymongo.cancel_url', '/register/cancelled')),
            'payment_method_types' => (array) config('services.paymongo.payment_methods', [self::METHOD_QRPH]),
        ], $attributes);

        $body = $this->postJson(
            $secret,
            self::ENDPOINT,
            ['data' => ['attributes' => $attributes]],
            'PayMongo checkout creation failed',
        );

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

    private function requireSecret(): string
    {
        $secret = (string) config('services.paymongo.secret');
        if ($secret === '') {
            throw new RuntimeException('PayMongo secret key not configured.');
        }

        return $secret;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(string $secret, string $url, array $payload, string $errorMessage): array
    {
        $response = Http::withBasicAuth($secret, '')
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::error($errorMessage, [
                'url' => $url,
                'status' => $response->status(),
                // Truncate to avoid dumping multi-kB QR data: URLs into logs on attach errors.
                'body' => mb_strimwidth((string) $response->body(), 0, 2048, '…'),
            ]);
            throw new RuntimeException($errorMessage.'.');
        }

        return (array) $response->json();
    }

    /**
     * Decode a verified PayMongo webhook body into the bits the controllers care about.
     *
     * @param  array<string, mixed>  $body
     * @return array{event_id: ?string, type: ?string, supported: bool, metadata: array<string, mixed>, payment_intent_id: ?string}
     */
    public function parseWebhookEvent(array $body): array
    {
        $eventId = $body['data']['id'] ?? null;
        $type = $body['data']['attributes']['type'] ?? null;
        $inner = $body['data']['attributes']['data']['attributes'] ?? [];

        $metadata = $inner['metadata']
            ?? $body['data']['attributes']['metadata']
            ?? [];

        $paymentIntentId = $inner['payment_intent_id']
            ?? $inner['payment_intent']['id']
            ?? null;

        return [
            'event_id' => is_string($eventId) ? $eventId : null,
            'type' => is_string($type) ? $type : null,
            'supported' => is_string($type) && in_array($type, self::SUPPORTED_WEBHOOK_EVENTS, true),
            'metadata' => is_array($metadata) ? $metadata : [],
            'payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
        ];
    }

    /**
     * Build the `Paymongo-Signature` header value for a JSON payload.
     * Used by the webhook verifier (test path) and by dev tooling that
     * forges PayMongo events into the local endpoint.
     */
    public function buildSignatureHeader(string $payload, ?int $timestamp = null): string
    {
        $secret = (string) config('services.paymongo.webhook_secret');
        if ($secret === '') {
            throw new RuntimeException('PayMongo webhook secret not configured.');
        }

        $timestamp = (string) ($timestamp ?? time());
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},te={$signature}";
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');
        if ($secret === '' || $signatureHeader === null) {
            return false;
        }

        $parts = $this->parseSignatureHeader($signatureHeader);
        $timestamp = $parts['t'] ?? null;
        $candidates = array_filter([$parts['te'] ?? null, $parts['li'] ?? null]);

        if (! $timestamp || $candidates === []) {
            return false;
        }

        $tolerance = (int) config('services.paymongo.webhook_tolerance_seconds', 300);
        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
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

    /**
     * PayMongo header format: `t=<timestamp>,te=<sig_test>,li=<sig_live>`.
     *
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $header): array
    {
        $parts = [];
        foreach (explode(',', $header) as $segment) {
            $kv = explode('=', trim($segment), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        return $parts;
    }
}
