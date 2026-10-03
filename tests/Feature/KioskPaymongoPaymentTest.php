<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KioskPayment;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Services\PaymongoPaymentService;
use App\Services\PosSaleService;
use App\Services\Sync\SyncEventApplier;
use Illuminate\Support\Facades\DB;
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

    public function test_online_request_that_falls_back_to_cash_gets_the_counter_window(): void
    {
        config(['services.paymongo.secret' => null]);
        Carbon::setTestNow('2026-05-05 09:00:00');

        $response = $this->postJson('/api/kiosk/payments', [
            'name' => 'Juan',
            'phone' => '09171234567',
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])->assertCreated();

        $payment = KioskPayment::where('reference', $response->json('reference'))->firstOrFail();
        $this->assertSame('2026-05-05 23:59:59', $payment->expires_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_paymongo_webhook_emits_a_sync_event_so_the_kiosk_node_learns_it_was_paid(): void
    {
        config(['sync.role' => 'live']);

        KioskPayment::create([
            'reference' => 'kio_webhook_sync',
            'name' => 'Juan',
            'phone' => '09171234567',
            'amount' => 150,
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => 'pi_webhook_sync',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->sendSignedWebhook($this->paidEvent('pi_webhook_sync', 'evt_test_sync'))->assertOk();

        $this->assertDatabaseHas('sync_outbox', ['entity_type' => 'kiosk_payment', 'entity_id' => 'kio_webhook_sync', 'op' => 'update']);
    }

    public function test_paymongo_webhook_records_the_sale_even_if_the_kiosk_never_checks_in(): void
    {
        KioskPayment::create([
            'reference' => 'kio_webhook_sale',
            'name' => 'Leo',
            'phone' => '09171234567',
            'amount' => 100,
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => 'pi_webhook_sale',
            'expires_at' => Carbon::now()->addMinutes(2),
        ]);
        $body = $this->paidEvent('pi_webhook_sale', 'evt_sale');

        $this->sendSignedWebhook($body)->assertOk();
        $this->sendSignedWebhook($body)->assertOk(); // replay

        $sale = SaleTransaction::where('type', SaleTransaction::TYPE_WALK_IN)->sole();
        $this->assertSame(SaleTransaction::PAYMENT_METHOD_ONLINE_PAYMENT, $sale->payment_method);
        $this->assertSame('kio_webhook_sale', data_get($sale->details, 'kiosk_payment_reference'));

        // the kiosk arriving late still records attendance, without a second sale
        $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in', 'status' => 'success', 'name' => 'Leo', 'phone' => '09171234567',
            'payment_method' => 'online', 'payment_status' => 'paid', 'payment_reference' => 'kio_webhook_sale',
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])->assertCreated();

        $this->assertSame(1, SaleTransaction::count());
    }

    public function test_the_same_kiosk_sale_created_on_both_nodes_merges_into_one_row(): void
    {
        config(['sync.role' => 'live']);
        $payment = KioskPayment::create([
            'reference' => 'kio_two_nodes',
            'name' => 'Leo',
            'phone' => '09171234567',
            'amount' => 100,
            'status' => KioskPayment::STATUS_PAID,
            'paid_at' => Carbon::now(),
            'paymongo_payment_intent_id' => 'pi_two_nodes',
            'expires_at' => Carbon::now()->addMinutes(2),
        ]);

        // each node creates the sale on its own (live: webhook, local: attendance post); simulate
        // the second node by recording, forgetting the row, and recording again
        $first = app(PosSaleService::class)->recordAutomatedKioskWalkInSale($payment);
        $firstPayload = json_decode(DB::table('sync_outbox')->where('entity_type', 'sale_transaction')->value('payload'), true);
        DB::table('sale_transactions')->delete();
        $second = app(PosSaleService::class)->recordAutomatedKioskWalkInSale($payment);

        $this->assertSame($first->uuid, $second->uuid);

        // so the other node's copy arriving via sync upserts into the existing row
        $ack = app(SyncEventApplier::class)->applyOne([
            'event_id' => 'evt-other-node', 'entity_type' => 'sale_transaction', 'entity_id' => $first->uuid,
            'op' => 'create', 'payload' => $firstPayload, 'origin_node' => 'local-test', 'occurred_at' => (string) Carbon::now(),
        ]);

        $this->assertSame('ok', $ack['status']);
        $this->assertSame(1, SaleTransaction::count());
    }

    public function test_late_night_counter_walk_in_still_gets_thirty_minutes(): void
    {
        config(['services.paymongo.secret' => null]);
        Carbon::setTestNow('2026-05-05 23:59:30');

        $response = $this->postJson('/api/kiosk/payments', [
            'name' => 'Juan', 'phone' => '09171234567', 'method' => 'cash',
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])->assertCreated();

        $payment = KioskPayment::where('reference', $response->json('reference'))->firstOrFail();
        $this->assertSame('2026-05-06 00:29:30', $payment->expires_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
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
    private function paidEvent(string $paymentIntentId, string $eventId): array
    {
        return ['data' => ['id' => $eventId, 'attributes' => ['type' => 'payment.paid', 'data' => ['attributes' => ['payment_intent_id' => $paymentIntentId]]]]];
    }

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
