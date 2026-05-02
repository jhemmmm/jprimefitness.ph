<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\KioskPayment;
use App\Models\SystemActivity;
use App\Models\User;
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

    public function test_online_walk_in_success_marks_referenced_payment_paid(): void
    {
        $payment = KioskPayment::create([
            'reference' => 'kio_test_paid_by_attendance',
            'name' => 'Juan Dela Cruz',
            'phone' => '+639171234567',
            'amount_centavos' => 17500,
            'status' => KioskPayment::STATUS_PENDING,
            'qr_data' => 'gcash://demo',
            'expires_at' => now()->addMinute(),
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

        $payment->refresh();
        $this->assertSame(KioskPayment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $row = Attendance::where('name', 'Juan Dela Cruz')->firstOrFail();
        $notes = json_decode((string) $row->notes, true);
        $this->assertEquals(175.0, $notes['amount']);

        $this->getJson('/api/kiosk/payments/'.$payment->reference, [
            'X-Kiosk-Token' => 'test-kiosk-token',
        ])->assertOk()->assertExactJson(['status' => 'paid']);
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
        $member = $this->createMember('Jheamuel Panuelos');

        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_in',
            'qr_payload' => 'JPRIME:MEMBER:'.$member->id,
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
        $member = $this->createMember('Anna Cruz');

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
            'qr_payload' => 'JPRIME:MEMBER:'.$member->id,
            'occurred_at' => '2026-05-03T10:00:00+08:00',
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('attendance_id', $open->id);

        $this->assertNotNull($open->fresh()->checked_out_at);
    }

    public function test_member_time_out_without_open_session_returns_failure(): void
    {
        $member = $this->createMember('Ben Lopez');

        $response = $this->postJson('/api/kiosk/attendance', [
            'type' => 'member',
            'status' => 'success',
            'action' => 'time_out',
            'qr_payload' => 'JPRIME:MEMBER:'.$member->id,
        ], ['X-Kiosk-Token' => 'test-kiosk-token']);

        $response->assertOk()->assertJsonPath('ok', false);
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
}
