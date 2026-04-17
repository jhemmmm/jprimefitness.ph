<?php

namespace Tests\Feature;

use App\Jobs\RunEmployeeBiometricEnrollment;
use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\EmployeeBiometricSession;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Hikvision\HikvisionBiometricService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeBiometricIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('hikvision.enabled', true);
        config()->set('hikvision.helper_base_url', 'http://helper.test');
        config()->set('hikvision.helper_timeout', 60);
        config()->set('hikvision.helper_forward_token', 'expected-token');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['manager', 'staff', 'employee', 'member', 'admin', 'coach'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $permission = Permission::findOrCreate('manage employees');

        Role::findByName('manager')->givePermissionTo($permission);
        Role::findByName('admin')->givePermissionTo($permission);

        $this->setBusinessProfile();
    }

    public function test_employee_creation_returns_employee_profile_payload_and_persists_employee_profile(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $response = $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'phone' => '09170000001',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'employee_profile' => [
                    'daily_rate' => 650,
                    'pay_frequency' => 'semi_monthly',
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_profile.biometric_status', EmployeeProfile::STATUS_NOT_ENROLLED)
            ->assertJsonPath('employee_profile.daily_rate', 650)
            ->assertJsonPath('employee_profile.pay_frequency', 'semi_monthly')
            ->assertJsonMissingPath('daily_rate')
            ->assertJsonMissingPath('pay_frequency');

        $employeeId = $response->json('id');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employeeId,
            'hikvision_employee_no' => $this->hikvisionEmployeeNo($employeeId),
            'biometric_status' => EmployeeProfile::STATUS_NOT_ENROLLED,
            'daily_rate' => '650.00',
            'pay_frequency' => 'semi_monthly',
        ]);
    }

    public function test_employee_update_creates_missing_employee_profile(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Staff Sol');
        $employee->employeeProfile()?->delete();
        $employee->unsetRelation('employeeProfile');
        $staffRole = Role::findByName('staff');

        $this->assertDatabaseMissing('employee_profiles', [
            'user_id' => $employee->id,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}", [
                'name' => 'Staff Sol Updated',
                'email' => $employee->email,
                'phone' => '09179990000',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'employee_profile' => [
                    'daily_rate' => 700,
                    'pay_frequency' => 'monthly',
                ],
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('employee_profile.hikvision_employee_no', $this->hikvisionEmployeeNo($employee->id))
            ->assertJsonPath('employee_profile.daily_rate', 700)
            ->assertJsonPath('employee_profile.pay_frequency', 'monthly')
            ->assertJsonMissingPath('daily_rate')
            ->assertJsonMissingPath('pay_frequency');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
            'daily_rate' => '700.00',
            'pay_frequency' => 'monthly',
        ]);
    }

    public function test_successful_biometric_enrollment_updates_employee_profile_and_records_audit_events(): void
    {
        Bus::fake();

        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Kai');
        $this->fakeSuccessfulBiometricDependencies();

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/biometric/enroll")
            ->assertAccepted()
            ->assertJsonPath('session.status', EmployeeBiometricSession::STATUS_PENDING)
            ->assertJsonPath('employee_profile.biometric_status', EmployeeProfile::STATUS_ENROLLING)
            ->assertJsonPath('employee_profile.biometric_fingerprint_id', null);

        $sessionId = $response->json('session.id');

        $this->assertNotNull($sessionId);

        app(HikvisionBiometricService::class)->processEnrollment(
            EmployeeBiometricSession::query()->findOrFail($sessionId)
        );

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
            'biometric_fingerprint_id' => 1,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'subject_type' => AuditEvent::SUBJECT_EMPLOYEE,
            'subject_id' => $employee->id,
            'event' => 'biometric_enrollment_started',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'subject_type' => AuditEvent::SUBJECT_EMPLOYEE,
            'subject_id' => $employee->id,
            'event' => 'biometric_enrolled',
        ]);
    }

    public function test_starting_enrollment_while_an_active_session_exists_returns_the_existing_session(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Kai');
        $profile = EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
                'biometric_status' => EmployeeProfile::STATUS_ENROLLING,
            ],
        );
        $session = EmployeeBiometricSession::query()->create([
            'uuid' => (string) str()->uuid(),
            'employee_profile_id' => $profile->id,
            'status' => EmployeeBiometricSession::STATUS_PENDING,
            'fingerprint_id' => 1,
            'started_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/biometric/enroll")
            ->assertAccepted()
            ->assertJsonPath('session.status', EmployeeBiometricSession::STATUS_PENDING);

        $this->assertSame($session->id, $response->json('session.id'));
        $this->assertSame(1, EmployeeBiometricSession::query()->count());
    }

    public function test_biometric_enrollment_dispatches_on_the_background_connection(): void
    {
        Bus::fake();

        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Kai');

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/biometric/enroll")
            ->assertAccepted()
            ->assertJsonPath('session.status', EmployeeBiometricSession::STATUS_PENDING);

        $sessionId = $response->json('session.id');

        Bus::assertDispatched(RunEmployeeBiometricEnrollment::class, function (RunEmployeeBiometricEnrollment $job) use ($sessionId): bool {
            return $job->sessionId === $sessionId
                && $job->connection === 'background';
        });
    }

    public function test_failed_biometric_enrollment_reports_manual_terminal_fallback_employee_number(): void
    {
        Bus::fake();

        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Kai');

        Http::preventStrayRequests();
        Http::fake([
            'http://helper.test/persons/upsert' => Http::response([
                'status' => 'success',
                'message' => 'Employee synced.',
            ], 200),
            'http://helper.test/fingerprint/enroll' => Http::response([
                'status' => 'failed',
                'message' => 'The biometric enrollment service is unreachable. Please make sure the enrollment service is running.',
            ], 200),
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/biometric/enroll")
            ->assertAccepted()
            ->assertJsonPath('session.status', EmployeeBiometricSession::STATUS_PENDING)
            ->assertJsonPath('employee_profile.biometric_status', EmployeeProfile::STATUS_ENROLLING);

        $sessionId = $response->json('session.id');

        app(HikvisionBiometricService::class)->processEnrollment(
            EmployeeBiometricSession::query()->findOrFail($sessionId)
        );

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'biometric_status' => EmployeeProfile::STATUS_FAILED,
            'biometric_last_error' => 'The biometric enrollment service is unreachable. Please make sure the enrollment service is running.',
        ]);

        $this->assertDatabaseHas('employee_biometric_sessions', [
            'id' => $sessionId,
            'status' => EmployeeBiometricSession::STATUS_FAILED,
            'error_message' => 'The biometric enrollment service is unreachable. Please make sure the enrollment service is running.',
        ]);
    }

    public function test_biometric_cancel_route_is_not_available(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Kai');
        $profile = EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
                'biometric_status' => EmployeeProfile::STATUS_ENROLLING,
            ],
        );
        $session = EmployeeBiometricSession::query()->create([
            'uuid' => (string) str()->uuid(),
            'employee_profile_id' => $profile->id,
            'status' => EmployeeBiometricSession::STATUS_PENDING,
            'fingerprint_id' => 1,
            'started_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/biometric/sessions/{$session->id}/cancel")
            ->assertNotFound();
    }

    public function test_remove_fingerprint_clears_employee_profile_and_calls_hikvision_delete(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Kai');
        $profile = EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
                'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
                'biometric_fingerprint_id' => 1,
                'biometric_enrolled_at' => now(),
            ],
        );

        Http::preventStrayRequests();
        Http::fake([
            'http://helper.test/fingerprint/delete' => Http::response([
                'status' => 'success',
                'message' => 'Fingerprint deleted successfully.',
            ], 200),
        ]);

        $this->actingAs($manager)
            ->deleteJson("/panel/employees/{$employee->id}/biometric/fingerprint")
            ->assertOk()
            ->assertJsonPath('employee_profile.biometric_status', EmployeeProfile::STATUS_NOT_ENROLLED)
            ->assertJsonPath('employee_profile.biometric_fingerprint_id', null);

        $this->assertDatabaseHas('employee_profiles', [
            'id' => $profile->id,
            'biometric_status' => EmployeeProfile::STATUS_NOT_ENROLLED,
            'biometric_fingerprint_id' => null,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'subject_type' => AuditEvent::SUBJECT_EMPLOYEE,
            'subject_id' => $employee->id,
            'event' => 'biometric_removed',
        ]);
    }

    public function test_hikvision_callback_rejects_invalid_token(): void
    {
        config()->set('hikvision.helper_forward_token', 'expected-token');

        $this->postJson('/hikvision/callback?token=wrong-token', $this->forwardedPayload('SERIAL-1', '00000999', 'event-1'))
            ->assertForbidden();
    }

    public function test_hikvision_callback_toggles_employee_attendance_dedupes_duplicates_and_records_audit_events(): void
    {
        config()->set('hikvision.helper_forward_token', 'expected-token');

        $employee = $this->createUserWithRole('staff', 'Coach Kai');
        $profile = EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
                'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
                'biometric_fingerprint_id' => 1,
                'biometric_enrolled_at' => now(),
            ],
        );

        $firstPayload = $this->forwardedPayload('SERIAL-1', $profile->hikvision_employee_no, 'event-1', '2026-04-12T08:00:00+08:00');
        $secondPayload = $this->forwardedPayload('SERIAL-1', $profile->hikvision_employee_no, 'event-2', '2026-04-12T17:00:00+08:00');

        $this->postJson('/hikvision/callback?token=expected-token', $firstPayload)
            ->assertAccepted()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('action', 'check_in')
            ->assertJsonPath('employee_no', $profile->hikvision_employee_no);

        $attendance = Attendance::query()->where('user_id', $employee->id)->sole();

        $this->assertSame(Attendance::SOURCE_HIKVISION, $attendance->source);
        $this->assertSame('SERIAL-1', $attendance->source_device_serial);
        $this->assertNull($attendance->checked_out_at);

        $this->postJson('/hikvision/callback?token=expected-token', $secondPayload)
            ->assertAccepted()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('action', 'check_out')
            ->assertJsonPath('employee_no', $profile->hikvision_employee_no);

        $attendance->refresh();

        $this->assertNotNull($attendance->checked_out_at);
        $this->assertSame(1, Attendance::query()->count());

        $this->postJson('/hikvision/callback?token=expected-token', $secondPayload)
            ->assertOk()
            ->assertJsonPath('status', 'duplicate')
            ->assertJsonPath('action', 'duplicate')
            ->assertJsonPath('employee_no', $profile->hikvision_employee_no);

        $this->assertSame(1, Attendance::query()->count());

        $this->assertDatabaseHas('hikvision_event_logs', [
            'device_serial' => 'SERIAL-1',
            'event_serial_no' => 'event-1',
            'attendance_id' => $attendance->id,
        ]);

        $this->assertDatabaseHas('hikvision_event_logs', [
            'device_serial' => 'SERIAL-1',
            'event_serial_no' => 'event-2',
            'attendance_id' => $attendance->id,
        ]);

        $this->assertSame(
            ['checked_in', 'checked_out'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_ATTENDANCE)
                ->where('subject_id', $attendance->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['Hikvision SERIAL-1', 'Hikvision SERIAL-1'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_ATTENDANCE)
                ->where('subject_id', $attendance->id)
                ->orderBy('id')
                ->pluck('actor_name')
                ->all()
        );
    }

    public function test_hikvision_callback_ignores_non_fingerprint_events(): void
    {
        config()->set('hikvision.helper_forward_token', 'expected-token');

        $employee = $this->createUserWithRole('staff', 'Coach Kai');
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
                'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
            ],
        );

        $payload = $this->forwardedPayload('SERIAL-1', $this->hikvisionEmployeeNo($employee->id), 'event-9');
        $payload['event_sub_type'] = 1;
        $payload['raw']['AccessControllerEvent']['subEventType'] = 1;

        $this->postJson('/hikvision/callback?token=expected-token', $payload)
            ->assertAccepted()
            ->assertJsonPath('status', 'ignored')
            ->assertJsonPath('action', 'ignored')
            ->assertJsonPath('employee_no', $this->hikvisionEmployeeNo($employee->id));

        $this->assertSame(0, Attendance::query()->count());
        $this->assertSame(0, AuditEvent::query()->where('subject_type', AuditEvent::SUBJECT_ATTENDANCE)->count());
    }

    private function fakeSuccessfulBiometricDependencies(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://helper.test/persons/upsert' => Http::response([
                'status' => 'success',
                'message' => 'Employee synced.',
            ], 200),
            'http://helper.test/fingerprint/enroll' => Http::response([
                'status' => 'success',
                'message' => 'Fingerprint captured and saved to device successfully.',
                'savedToDevice' => true,
                'saveStatus' => 'success',
            ], 200),
            'http://helper.test/fingerprint/delete' => Http::response([
                'status' => 'success',
                'message' => 'Fingerprint deleted successfully.',
            ], 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function forwardedPayload(
        string $deviceSerial,
        string $employeeNo,
        string $eventSerial,
        string $occurredAt = '2026-04-12T08:00:00+08:00',
    ): array {
        return [
            'device_ip' => '192.168.250.201',
            'device_name' => $deviceSerial,
            'device_serial' => $deviceSerial,
            'employee_no' => $employeeNo,
            'event_serial_no' => $eventSerial,
            'event_type' => 'AccessControllerEvent',
            'event_sub_type' => 38,
            'occurred_at' => $occurredAt,
            'raw' => [
                'eventType' => 'AccessControllerEvent',
                'dateTime' => $occurredAt,
                'AccessControllerEvent' => [
                    'serialNo' => $eventSerial,
                    'employeeNoString' => $employeeNo,
                    'subEventType' => 38,
                    'majorEventType' => 5,
                    'deviceName' => $deviceSerial,
                ],
            ],
        ];
    }

    private function setBusinessProfile(): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => 'Naga',
            'status' => BusinessProfile::STATUS_OPEN,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function hikvisionEmployeeNo(int $employeeId): string
    {
        return str_pad((string) $employeeId, 8, '0', STR_PAD_LEFT);
    }
}
