<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\CashDrawerSession;
use App\Models\KioskPayment;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PanelKioskPaymentsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CashDrawerSession $drawerSession;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');

        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);

        RatePlan::create([
            'name' => 'Daily Pass',
            'duration_days' => 1,
            'price' => 150,
            'is_active' => true,
            'is_walk_in_only' => true,
        ]);

        $this->drawerSession = CashDrawerSession::factory()->create();
    }

    public function test_pending_endpoint_lists_only_pending_cash_payments(): void
    {
        $pendingCash = $this->makePayment('kio_pending_cash', [
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => null,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);
        $this->makePayment('kio_pending_online', [
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => 'pi_test',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);
        $this->makePayment('kio_paid_cash', [
            'status' => KioskPayment::STATUS_PAID,
            'paid_at' => Carbon::now()->subMinute(),
        ]);
        $this->makePayment('kio_expired', [
            'status' => KioskPayment::STATUS_PENDING,
            'expires_at' => Carbon::now()->subSecond(),
        ]);

        $plan = RatePlan::create([
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 1500,
            'is_active' => true,
            'is_walk_in_only' => false,
        ]);
        $member = User::create([
            'name' => 'Pending Patty',
            'email' => 'pending.patty@example.com',
            'phone' => '09170001111',
            'password' => bcrypt('secret'),
            'status' => User::STATUS_INACTIVE,
        ]);
        $member->assignRole('member');
        $pendingMembership = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 1500,
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addDays(29)->toDateString(),
            'status' => MemberSubscription::STATUS_PAUSED,
            'pending_payment_method' => MemberSubscription::PENDING_PAYMENT_ON_SITE,
        ]);

        $response = $this->actingAs($this->staff())
            ->getJson('/panel/sales/pending-payments')
            ->assertOk();

        $payments = collect($response->json('payments'));
        $walkIns = $payments->where('kind', 'walk_in')->values()->all();
        $memberships = $payments->where('kind', 'membership')->values()->all();

        $this->assertCount(1, $walkIns);
        $this->assertSame('walk-in:'.$pendingCash->reference, $walkIns[0]['key']);
        $this->assertCount(1, $memberships);
        $this->assertSame('membership:'.$pendingMembership->id, $memberships[0]['key']);
    }

    public function test_confirm_creates_walk_in_sale_with_discount_block(): void
    {
        $payment = $this->makePayment('kio_confirm_discount', [
            'amount' => 120,
            'base_amount' => 150,
            'discount_type' => KioskPayment::DISCOUNT_STUDENT,
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => null,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $staff = $this->staff();

        $response = $this->actingAs($staff)
            ->postJson('/panel/kiosk-payments/'.$payment->reference.'/confirm')
            ->assertCreated();

        $this->assertSame(120.0, (float) $response->json('total'));
        $this->assertSame(150.0, (float) $response->json('subtotal'));
        $this->assertSame('student', $response->json('discount.type'));
        $this->assertSame(20, $response->json('discount.percent'));
        $this->assertSame(30.0, (float) $response->json('discount.amount'));

        $payment->refresh();
        $this->assertSame(KioskPayment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->consumed_at);

        $sale = SaleTransaction::query()->latest('id')->first();
        $this->assertSame(SaleTransaction::TYPE_WALK_IN, $sale->type);
        $this->assertSame(SaleTransaction::PAYMENT_METHOD_CASH, $sale->payment_method);
        $this->assertSame($staff->id, $sale->processed_by);
        $this->assertSame(120.0, (float) $sale->total);
        $this->assertSame('student', data_get($sale->details, 'discount.type'));
        $this->assertSame(150.0, (float) data_get($sale->details, 'subtotal'));
    }

    public function test_confirm_without_discount_omits_discount_block(): void
    {
        $payment = $this->makePayment('kio_confirm_plain', [
            'amount' => 150,
            'base_amount' => 150,
            'discount_type' => null,
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => null,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $response = $this->actingAs($this->staff())
            ->postJson('/panel/kiosk-payments/'.$payment->reference.'/confirm')
            ->assertCreated();

        $this->assertNull($response->json('discount'));
        $this->assertSame(150.0, (float) $response->json('total'));
        $this->assertSame(150.0, (float) $response->json('subtotal'));
    }

    public function test_cash_confirmation_rolls_back_when_drawer_is_closed(): void
    {
        $payment = $this->makePayment('kio_closed_drawer', [
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => null,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $this->drawerSession->forceFill([
            'is_open' => null,
            'closed_at' => now(),
        ])->save();

        $this->actingAs($this->staff())
            ->postJson('/panel/kiosk-payments/'.$payment->reference.'/confirm')
            ->assertConflict()
            ->assertJsonPath('message', 'Open the cash drawer before recording a sale.');

        $payment->refresh();

        $this->assertSame(KioskPayment::STATUS_PENDING, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->consumed_at);
        $this->assertDatabaseCount('sale_transactions', 0);
        $this->assertDatabaseCount('cash_ledger_entries', 0);
    }

    public function test_pending_membership_confirmation_rolls_back_when_drawer_is_closed(): void
    {
        Mail::fake();

        $plan = RatePlan::create([
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 1500,
            'is_active' => true,
            'is_walk_in_only' => false,
        ]);
        $member = User::factory()->create(['status' => User::STATUS_INACTIVE]);
        $member->assignRole('member');
        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'sold_price' => 1500,
            'start_date' => now()->toDateString(),
            'status' => MemberSubscription::STATUS_PAUSED,
            'pending_payment_method' => MemberSubscription::PENDING_PAYMENT_ON_SITE,
        ]);

        $this->drawerSession->forceFill([
            'is_open' => null,
            'closed_at' => now(),
        ])->save();

        $this->actingAs($this->staff())
            ->postJson("/panel/sales/pending-memberships/{$subscription->id}/confirm", [
                'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
                'payment_reference' => 'GCASH-MEM-001',
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'Open the cash drawer before recording a sale.');

        $this->assertSame(User::STATUS_INACTIVE, $member->fresh()->status);
        $this->assertSame(MemberSubscription::STATUS_PAUSED, $subscription->fresh()->status);
        $this->assertSame(MemberSubscription::PENDING_PAYMENT_ON_SITE, $subscription->fresh()->pending_payment_method);
        $this->assertDatabaseCount('sale_transactions', 0);
    }

    public function test_confirm_rejects_online_payment(): void
    {
        $payment = $this->makePayment('kio_online', [
            'paymongo_payment_intent_id' => 'pi_x',
            'status' => KioskPayment::STATUS_PENDING,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $this->actingAs($this->staff())
            ->postJson('/panel/kiosk-payments/'.$payment->reference.'/confirm')
            ->assertStatus(422);

        $this->assertSame(KioskPayment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(0, SaleTransaction::query()->count());
    }

    public function test_confirm_rejects_already_paid_payment(): void
    {
        $payment = $this->makePayment('kio_already_paid', [
            'status' => KioskPayment::STATUS_PAID,
            'paid_at' => Carbon::now()->subMinute(),
            'paymongo_payment_intent_id' => null,
        ]);

        $this->actingAs($this->staff())
            ->postJson('/panel/kiosk-payments/'.$payment->reference.'/confirm')
            ->assertStatus(409);

        $this->assertSame(0, SaleTransaction::query()->count());
    }

    public function test_cancel_marks_pending_payment_cancelled_and_creates_no_sale(): void
    {
        $payment = $this->makePayment('kio_to_cancel', [
            'status' => KioskPayment::STATUS_PENDING,
            'paymongo_payment_intent_id' => null,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $this->actingAs($this->staff())
            ->postJson('/panel/kiosk-payments/'.$payment->reference.'/cancel')
            ->assertOk();

        $this->assertSame(KioskPayment::STATUS_CANCELLED, $payment->fresh()->status);
        $this->assertSame(0, SaleTransaction::query()->count());
    }

    public function test_create_payment_persists_base_amount_and_uses_cash_timeout_for_cash(): void
    {
        config([
            'services.kiosk.token' => 'test-kiosk-token',
            'services.kiosk.payment_timeout_seconds' => 120,
            'services.kiosk.cash_payment_timeout_seconds' => 1800,
        ]);
        Carbon::setTestNow('2026-05-05 09:00:00');

        $this->postJson('/api/kiosk/payments', [
            'name' => 'Cash Carlos',
            'phone' => '+639170000010',
            'method' => 'cash',
            'discount_type' => 'senior',
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])->assertCreated();

        $payment = KioskPayment::query()->where('name', 'Cash Carlos')->firstOrFail();
        $this->assertSame(150.0, (float) $payment->base_amount);
        $this->assertSame(120.0, (float) $payment->amount);
        $this->assertTrue($payment->expires_at->equalTo(Carbon::now()->addSeconds(1800)));

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePayment(string $reference, array $overrides = []): KioskPayment
    {
        return KioskPayment::create(array_merge([
            'reference' => $reference,
            'name' => 'Walk-In Wesley',
            'phone' => '09171234567',
            'amount' => 150,
            'base_amount' => 150,
            'status' => KioskPayment::STATUS_PENDING,
            'expires_at' => Carbon::now()->addMinutes(30),
        ], $overrides));
    }

    private function staff(): User
    {
        $user = User::factory()->create(['name' => 'Staff Ana']);
        $user->assignRole('staff');

        return $user;
    }
}
