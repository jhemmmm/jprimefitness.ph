<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KioskPayment;
use App\Models\RatePlan;
use App\Services\PaymongoPaymentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KioskPaymongoPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const WEBHOOK_SECRET = 'whsk_test_secret';

    private const QR_RAW = '00020101021128620011ph.ppmi.qrph0220000000000123456789010300040206ABCDEF5204540353035165802PH5910JPRIME GYM6006MANILA6304ABCD';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kiosk.token' => 'test-kiosk-token',
            'services.kiosk.payment_timeout_seconds' => 120,
            'services.paymongo.secret' => 'sk_test_dummy',
            'services.paymongo.webhook_secret' => self::WEBHOOK_SECRET,
            'services.paymongo.webhook_tolerance_seconds' => 300,
        ]);

        RatePlan::create([
            'name' => 'Daily Pass',
            'duration_days' => 1,
            'price' => 150,
            'is_active' => true,
            'is_walk_in_only' => true,
        ]);
    }

    public function test_kiosk_store_creates_qrph_payment_intent_and_returns_raw_qr(): void
    {
        Http::fake([
            'api.paymongo.com/v1/payment_intents/pi_test_abc/attach' => Http::response([
                'data' => [
                    'id' => 'pi_test_abc',
                    'attributes' => [
                        'next_action' => [
                            'type' => 'present_qr_code',
                            'code' => [
                                'type' => 'qrph',
                                'raw' => self::QR_RAW,
                                'image_url' => 'https://cdn.paymongo.com/qrph/pi_test_abc.png',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'api.paymongo.com/v1/payment_intents*' => Http::response([
                'data' => [
                    'id' => 'pi_test_abc',
                    'attributes' => [
                        'client_key' => 'pi_test_abc_client_key',
                    ],
                ],
            ], 200),
            'api.paymongo.com/v1/payment_methods' => Http::response([
                'data' => [
                    'id' => 'pm_test_xyz',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/kiosk/payments', [
            'name' => 'Juan',
            'phone' => '09171234567',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertCreated()
            ->assertJsonPath('method', 'online')
            ->assertJsonPath('qr_data_url', self::QR_RAW)
            ->assertJsonPath('qr_image_url', 'https://cdn.paymongo.com/qrph/pi_test_abc.png');

        $reference = $response->json('reference');
        $payment = KioskPayment::where('reference', $reference)->firstOrFail();
        $this->assertSame(self::QR_RAW, $payment->qr_data);
        $this->assertSame('pi_test_abc', $payment->paymongo_payment_intent_id);

        Http::assertSent(function ($request) use ($reference) {
            if (! str_contains($request->url(), '/payment_intents') || str_contains($request->url(), '/attach')) {
                return true;
            }
            $body = json_decode($request->body(), true);
            $attrs = $body['data']['attributes'] ?? [];

            return $attrs['amount'] === 15000
                && $attrs['payment_method_allowed'] === ['qrph']
                && ($attrs['metadata']['kiosk_payment_reference'] ?? null) === $reference;
        });
    }

    public function test_kiosk_store_falls_back_to_cash_when_paymongo_not_configured(): void
    {
        config(['services.paymongo.secret' => null]);

        $response = $this->postJson('/api/kiosk/payments', [
            'name' => 'Juan',
            'phone' => '09171234567',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertCreated()
            ->assertJsonPath('method', 'cash')
            ->assertJsonPath('qr_data_url', null);

        $payment = KioskPayment::where('reference', $response->json('reference'))->firstOrFail();
        $this->assertNull($payment->qr_data);
        $this->assertNull($payment->paymongo_payment_intent_id);
    }

    public function test_kiosk_store_falls_back_to_cash_when_paymongo_call_fails(): void
    {
        Http::fake([
            'api.paymongo.com/*' => Http::response('boom', 500),
        ]);

        $response = $this->postJson('/api/kiosk/payments', [
            'name' => 'Juan',
            'phone' => '09171234567',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertCreated()
            ->assertJsonPath('method', 'cash')
            ->assertJsonPath('qr_data_url', null);
    }

    public function test_paymongo_webhook_marks_kiosk_payment_paid_via_payment_intent_id(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_webhook_test',
            'name' => 'Juan',
            'phone' => '09171234567',
            'amount' => 150,
            'status' => KioskPayment::STATUS_PENDING,
            'qr_data' => self::QR_RAW,
            'paymongo_payment_intent_id' => 'pi_webhook_abc',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $body = [
            'data' => [
                'id' => 'evt_test_1',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'id' => 'pay_xyz',
                        'attributes' => [
                            'payment_intent_id' => 'pi_webhook_abc',
                        ],
                    ],
                ],
            ],
        ];
        $this->sendSignedWebhook($body)->assertOk()->assertJson(['ok' => true]);

        $payment->refresh();
        $this->assertSame(KioskPayment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_paymongo_webhook_replay_is_idempotent(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_webhook_replay',
            'name' => 'Juan',
            'phone' => '09171234567',
            'amount' => 150,
            'status' => KioskPayment::STATUS_PAID,
            'paid_at' => Carbon::now()->subMinutes(2),
            'qr_data' => self::QR_RAW,
            'paymongo_payment_intent_id' => 'pi_replay',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $originalPaidAt = $payment->paid_at;

        $body = [
            'data' => [
                'id' => 'evt_test_replay',
                'attributes' => [
                    'type' => 'payment.paid',
                    'data' => [
                        'attributes' => [
                            'payment_intent_id' => 'pi_replay',
                        ],
                    ],
                ],
            ],
        ];
        $this->sendSignedWebhook($body)->assertOk();

        // paid_at must not have moved.
        $this->assertSame(
            $originalPaidAt->toIso8601String(),
            $payment->fresh()->paid_at?->toIso8601String()
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sendSignedWebhook(array $body): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($body);
        $signatureHeader = app(PaymongoPaymentService::class)->buildSignatureHeader($payload);

        return $this->call(
            'POST',
            '/api/paymongo/webhook',
            [],
            [],
            [],
            [
                'HTTP_PAYMONGO_SIGNATURE' => $signatureHeader,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }
}
