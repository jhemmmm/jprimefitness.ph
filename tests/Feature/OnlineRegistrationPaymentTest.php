<?php

namespace Tests\Feature;

use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\PaymongoPaymentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnlineRegistrationPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paymongo.webhook_secret' => 'whsk_test_secret', 'services.paymongo.webhook_tolerance_seconds' => 300]);
        Mail::fake();
    }

    public function test_online_registration_payment_records_one_sale_across_replays_and_paired_events(): void
    {
        $member = User::factory()->create(['name' => 'Andrea Villanueva', 'status' => User::STATUS_INACTIVE]);
        $member->assignRole('member');
        $plan = RatePlan::create(['name' => 'Monthly', 'duration_days' => 30, 'price' => 1600, 'is_active' => true]);
        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 1600,
            'start_date' => now()->toDateString(),
            'status' => MemberSubscription::STATUS_PAUSED,
            'pending_payment_method' => MemberSubscription::PENDING_PAYMENT_ONLINE,
        ]);

        // PayMongo sends both events for one checkout, and may retry either.
        $this->sendPaid($subscription, 'checkout_session.payment.paid', 'evt_1')->assertOk();
        $this->sendPaid($subscription, 'payment.paid', 'evt_2')->assertOk();
        $this->sendPaid($subscription, 'checkout_session.payment.paid', 'evt_1')->assertOk();

        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $subscription->fresh()->status);

        $sales = SaleTransaction::where('member_id', $member->id)->get();
        $this->assertCount(1, $sales);
        $this->assertSame(SaleTransaction::TYPE_MEMBERSHIP, $sales[0]->type);
        $this->assertSame(SaleTransaction::PAYMENT_METHOD_ONLINE_PAYMENT, $sales[0]->payment_method);
        $this->assertSame('1600.00', $sales[0]->total);
        $this->assertNull($sales[0]->processed_by);
        $this->assertSame($subscription->id, $sales[0]->details['subscription_id']);
    }

    private function sendPaid(MemberSubscription $subscription, string $type, string $eventId): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode(['data' => ['id' => $eventId, 'attributes' => [
            'type' => $type,
            'data' => ['attributes' => ['metadata' => ['subscription_id' => (string) $subscription->id]]],
        ]]]);

        return $this->call('POST', '/api/paymongo/webhook', [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => app(PaymongoPaymentService::class)->buildSignatureHeader($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
