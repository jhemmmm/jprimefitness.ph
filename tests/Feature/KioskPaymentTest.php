<?php

namespace Tests\Feature;

use App\Models\KioskPayment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KioskPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kiosk.token' => 'test-kiosk-token',
            'services.kiosk.walk_in_amount' => 150,
            'services.kiosk.payment_timeout_seconds' => 60,
        ]);
    }

    public function test_create_payment_returns_reference_qr_and_expiry(): void
    {
        Carbon::setTestNow('2026-05-03 12:00:00');

        $response = $this->postJson('/api/kiosk/payments', [
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertCreated()
            ->assertJsonStructure(['reference', 'qr_data_url', 'expires_at']);

        $reference = $response->json('reference');
        $this->assertStringStartsWith('kio_20260503_', $reference);

        $this->assertDatabaseHas('kiosk_payments', [
            'reference' => $reference,
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'status' => KioskPayment::STATUS_PENDING,
            'amount_centavos' => 15000,
        ]);

        Carbon::setTestNow();
    }

    public function test_show_pending_payment_returns_pending(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_test_pending',
            'name' => 'A',
            'phone' => '09170000001',
            'amount_centavos' => 15000,
            'status' => KioskPayment::STATUS_PENDING,
            'qr_data' => 'gcash://demo',
            'expires_at' => Carbon::now()->addMinute(),
        ]);

        $this->getJson('/api/kiosk/payments/'.$payment->reference, [
            'X-Kiosk-Token' => 'test-kiosk-token',
        ])
            ->assertOk()
            ->assertExactJson(['status' => 'pending']);
    }

    public function test_show_paid_payment_returns_paid(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_test_paid',
            'name' => 'A',
            'phone' => '09170000001',
            'amount_centavos' => 15000,
            'status' => KioskPayment::STATUS_PAID,
            'qr_data' => 'gcash://demo',
            'expires_at' => Carbon::now()->addMinute(),
            'paid_at' => Carbon::now(),
        ]);

        $this->getJson('/api/kiosk/payments/'.$payment->reference, [
            'X-Kiosk-Token' => 'test-kiosk-token',
        ])
            ->assertOk()
            ->assertExactJson(['status' => 'paid']);
    }

    public function test_show_lazily_expires_payment_after_window(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_test_expire',
            'name' => 'A',
            'phone' => '09170000001',
            'amount_centavos' => 15000,
            'status' => KioskPayment::STATUS_PENDING,
            'qr_data' => 'gcash://demo',
            'expires_at' => Carbon::now()->subSecond(),
        ]);

        $this->getJson('/api/kiosk/payments/'.$payment->reference, [
            'X-Kiosk-Token' => 'test-kiosk-token',
        ])
            ->assertOk()
            ->assertExactJson(['status' => 'expired']);

        $this->assertSame(KioskPayment::STATUS_EXPIRED, $payment->fresh()->status);
    }

    public function test_show_unknown_reference_returns_404_with_expired_status(): void
    {
        $this->getJson('/api/kiosk/payments/kio_does_not_exist', [
            'X-Kiosk-Token' => 'test-kiosk-token',
        ])
            ->assertNotFound()
            ->assertExactJson(['status' => 'expired']);
    }

    public function test_payments_endpoints_require_kiosk_token(): void
    {
        $this->postJson('/api/kiosk/payments', [
            'name' => 'X',
            'phone' => '09171234567',
        ])->assertUnauthorized();

        $this->getJson('/api/kiosk/payments/anything')
            ->assertUnauthorized();
    }
}
