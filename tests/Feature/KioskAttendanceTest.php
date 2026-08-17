<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\KioskPayment;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\SystemActivity;
use App\Models\User;
use App\Services\MembershipQrService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class KioskAttendanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('member');

        config(['services.kiosk.token' => 'test-kiosk-token']);
        Carbon::setTestNow('2026-05-03 08:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_walk_in_success_creates_attendance_with_kiosk_source(): void
    {
        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'payment_method' => 'counter',
            'payment_status' => 'pending',
            'payment_reference' => null,
            'occurred_at' => '2026-05-03T08:30:00+08:00',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['attendance_id', 'message']);

        $this->assertDatabaseHas('attendances', [
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Juan Dela Cruz',
            'source' => Attendance::SOURCE_KIOSK,
            'user_id' => null,
        ]);

        $row = Attendance::where('name', 'Juan Dela Cruz')->firstOrFail();
        $notes = json_decode((string) $row->notes, true);
        $this->assertSame('+639171234567', $notes['phone']);
        $this->assertSame('counter', $notes['payment_method']);
        $this->assertSame('pending', $notes['payment_status']);
    }

    public function test_online_walk_in_consumes_already_paid_payment_and_records_sale(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_test_paid_by_attendance',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'amount' => 175,
            'base_amount' => 175,
            'status' => KioskPayment::STATUS_PAID,
            'qr_data' => 'gcash://demo',
            'paymongo_payment_intent_id' => 'pi_test_attendance',
            'expires_at' => now()->addMinute(),
            'paid_at' => now(),
        ]);

        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'payment_reference' => $payment->reference,
            'occurred_at' => '2026-05-03T08:30:00+08:00',
        ], [
            'X-Kiosk-Token' => 'test-kiosk-token',
            'X-Kiosk-Device' => 'KIOSK-01',
        ]);

        $response->assertCreated()->assertJsonPath('ok', true);

        $this->assertDatabaseCount('cash_drawer_sessions', 0);
        $this->assertDatabaseCount('cash_ledger_entries', 0);

        $payment->refresh();
        $this->assertSame(KioskPayment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->consumed_at);

        $row = Attendance::where('name', 'Juan Dela Cruz')->firstOrFail();
        $notes = json_decode((string) $row->notes, true);
        $this->assertEquals(175.0, $notes['amount']);

        $sale = SaleTransaction::where('type', SaleTransaction::TYPE_WALK_IN)->firstOrFail();
        $this->assertEquals(175.0, (float) $sale->total);
        $this->assertSame(SaleTransaction::PAYMENT_METHOD_ONLINE_PAYMENT, $sale->payment_method);
        $this->assertSame($payment->reference, data_get($sale->details, 'kiosk_payment_reference'));
    }

    public function test_online_walk_in_rejects_unpaid_kiosk_payment(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_test_unpaid',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'amount' => 175,
            'status' => KioskPayment::STATUS_PENDING,
            'qr_data' => 'gcash://demo',
            'expires_at' => now()->addMinute(),
        ]);

        $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'payment_reference' => $payment->reference,
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_reference']);

        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('sale_transactions', 0);
        $this->assertNull($payment->fresh()->consumed_at);
    }

    public function test_online_walk_in_rejects_already_consumed_payment(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_test_already_used',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'amount' => 175,
            'status' => KioskPayment::STATUS_PAID,
            'qr_data' => 'gcash://demo',
            'expires_at' => now()->addMinute(),
            'paid_at' => now(),
            'consumed_at' => now(),
        ]);

        $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'payment_reference' => $payment->reference,
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_reference']);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_online_walk_in_records_attendance_without_local_payment_row(): void
    {
        // Online payments live on the production backend; local has no matching
        // kiosk_payments row. Attendance must still record, with no sale ledger.
        $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'payment_reference' => 'kio_lives_on_remote_node',
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('sale_transactions', 0);
    }

    public function test_kiosk_attendance_activity_uses_device_actor_name(): void
    {
        $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'Device Logged Guest',
            'phone' => '+639171234567',
            'payment_method' => 'counter',
            'payment_status' => 'pending',
        ], [
            'X-Kiosk-Token' => 'test-kiosk-token',
            'X-Kiosk-Device' => 'KIOSK-02',
        ])->assertCreated();

        $this->assertDatabaseHas('system_activities', [
            'subject_type' => SystemActivity::SUBJECT_ATTENDANCE,
            'event' => 'checked_in',
            'actor_name' => 'Kiosk KIOSK-02',
        ]);
    }

    public function test_walk_in_failed_does_not_create_attendance(): void
    {
        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'failed',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'payment_method' => 'online',
            'payment_status' => 'timeout',
            'payment_reference' => 'kio_test_ref',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertOk()->assertJsonPath('ok', false);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_member_time_in_succeeds_for_known_member(): void
    {
        $membership = $this->createActiveMembership('Jheamuel Panuelos');
        $member = $membership->member;

        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_in',
            'qr_payload' => $membership->qr_payload,
            'reason' => null,
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('member_name', 'Jheamuel Panuelos');

        $this->assertDatabaseHas('attendances', [
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'source' => Attendance::SOURCE_KIOSK,
            'checked_out_at' => null,
        ]);
    }

    public function test_member_time_out_closes_open_attendance(): void
    {
        $membership = $this->createActiveMembership('Anna Cruz');
        $member = $membership->member;

        $open = Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-05-03 08:00:00',
            'source' => Attendance::SOURCE_KIOSK,
        ]);

        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_out',
            'qr_payload' => $membership->qr_payload,
            'occurred_at' => '2026-05-03T10:00:00+08:00',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('attendance_id', $open->id);

        $this->assertNotNull($open->fresh()->checked_out_at);
    }

    public function test_member_time_out_without_open_session_returns_failure(): void
    {
        $membership = $this->createActiveMembership('Ben Lopez');

        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_out',
            'qr_payload' => $membership->qr_payload,
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'missing_open_attendance');
    }

    public function test_member_unknown_qr_returns_failure(): void
    {
        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_in',
            'qr_payload' => 'NOT-A-JPRIME-QR',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'QR code not recognized.');
    }

    public function test_member_time_in_rejects_expired_membership(): void
    {
        $membership = $this->createActiveMembership('Expired Member', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);

        $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_in',
            'qr_payload' => $membership->qr_payload,
            'occurred_at' => '2026-05-03T08:00:00+08:00',
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'membership_expired');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_member_time_in_rejects_inactive_membership_status(): void
    {
        $membership = $this->createActiveMembership('Paused Member', [
            'status' => MemberSubscription::STATUS_PAUSED,
        ]);

        $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_in',
            'qr_payload' => $membership->qr_payload,
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'membership_inactive');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_member_time_in_rejects_inactive_member_account(): void
    {
        $membership = $this->createActiveMembership('Inactive Member');
        $membership->member->update(['status' => User::STATUS_INACTIVE]);

        $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_in',
            'qr_payload' => $membership->qr_payload,
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'member_inactive');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_member_time_in_rejects_duplicate_open_attendance(): void
    {
        $membership = $this->createActiveMembership('Duplicate Member');
        $member = $membership->member;

        Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-05-03 08:00:00',
            'source' => Attendance::SOURCE_KIOSK,
        ]);

        $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_in',
            'qr_payload' => $membership->qr_payload,
        ], ['X-Kiosk-Token' => 'test-kiosk-token'])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('reason', 'duplicate_time_in');

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_endpoint_rejects_missing_or_wrong_token(): void
    {
        $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'X',
            'phone' => '09171234567',
            'payment_method' => 'counter',
            'payment_status' => 'pending',
        ])->assertUnauthorized();

        $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
            'status' => 'success',
            'name' => 'X',
            'phone' => '09171234567',
            'payment_method' => 'counter',
            'payment_status' => 'pending',
        ], ['X-Kiosk-Token' => 'wrong'])->assertUnauthorized();
    }

    public function test_endpoint_validates_required_fields(): void
    {
        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'walk_in',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'name', 'phone', 'payment_method', 'payment_status']);
    }

    private function createMember(string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => str($name)->slug('-').'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole('member');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createActiveMembership(string $memberName, array $attributes = []): MemberSubscription
    {
        $member = $this->createMember($memberName);
        $ratePlan = RatePlan::create([
            'name' => $memberName.' Plan',
            'duration_days' => 30,
            'description' => $memberName.' membership',
            'price' => 1500,
            'is_active' => true,
        ]);

        $membership = $member->memberSubscriptions()->create(array_merge([
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 1500,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-30',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ], $attributes));

        return app(MembershipQrService::class)->ensurePayload($membership)->fresh(['member', 'ratePlan']);
    }
}
