<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SystemActivity;
use App\Models\BusinessProfile;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\SystemActivityService;
use App\Services\Hikvision\HikvisionAttendanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HikvisionAttendanceSyncCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.biometric.token', 'forward-token');

        BusinessProfile::factory()->create([
            'name' => 'J Prime Fitness',
        ]);
    }

    public function test_helper_forwarded_attendance_callback_toggles_employee_attendance_and_dedupes_duplicates(): void
    {
        $employee = User::factory()->create([
            'name' => 'Coach Kai',
            'email' => 'coach-kai@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
            'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
            'biometric_fingerprint_id' => 1,
            'biometric_enrolled_at' => now(),
        ]);

        $firstPayload = $this->forwardedPayload($profile->hikvision_employee_no, 186, '2026-04-13T23:19:21+08:00');
        $secondPayload = $this->forwardedPayload($profile->hikvision_employee_no, 187, '2026-04-14T07:00:00+08:00');

        $this->postJson('/api/biometric/hikvision/callback', $firstPayload, [
            'X-Biometric-Token' => 'forward-token',
        ])
            ->assertAccepted()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('action', 'check_in');

        $attendance = Attendance::query()->where('user_id', $employee->id)->sole();

        $this->assertSame(Attendance::SOURCE_HIKVISION, $attendance->source);
        $this->assertSame('DS-K1A802AEF', $attendance->source_device_serial);
        $this->assertNull($attendance->checked_out_at);

        $this->postJson('/api/biometric/hikvision/callback', $secondPayload, [
            'X-Biometric-Token' => 'forward-token',
        ])
            ->assertAccepted()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('action', 'check_out');

        $attendance->refresh();

        $this->assertNotNull($attendance->checked_out_at);

        $this->postJson('/api/biometric/hikvision/callback', $secondPayload, [
            'X-Biometric-Token' => 'forward-token',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'duplicate')
            ->assertJsonPath('action', 'duplicate');

        $this->assertDatabaseHas('hikvision_event_logs', [
            'device_serial' => 'DS-K1A802AEF',
            'event_serial_no' => '186',
            'attendance_id' => $attendance->id,
        ]);

        $this->assertSame(
            ['Hikvision DS-K1A802AEF', 'Hikvision DS-K1A802AEF'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_ATTENDANCE)
                ->where('subject_id', $attendance->id)
                ->orderBy('id')
                ->pluck('actor_name')
                ->all()
        );
    }

    public function test_helper_forwarded_attendance_callback_ignores_stale_event_before_open_attendance(): void
    {
        $employee = User::factory()->create([
            'name' => 'Coach Kai',
            'email' => 'coach-kai@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
            'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
            'biometric_fingerprint_id' => 1,
            'biometric_enrolled_at' => now(),
        ]);

        $checkInPayload = $this->forwardedPayload($profile->hikvision_employee_no, 186, '2026-04-13T23:19:21+08:00');
        $stalePayload = $this->forwardedPayload($profile->hikvision_employee_no, 185, '2026-04-13T22:00:00+08:00');
        $checkOutPayload = $this->forwardedPayload($profile->hikvision_employee_no, 187, '2026-04-14T07:00:00+08:00');

        $this->postJson('/api/biometric/hikvision/callback', $checkInPayload, [
            'X-Biometric-Token' => 'forward-token',
        ])
            ->assertAccepted()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('action', 'check_in');

        $attendance = Attendance::query()->where('user_id', $employee->id)->sole();

        $this->postJson('/api/biometric/hikvision/callback', $stalePayload, [
            'X-Biometric-Token' => 'forward-token',
        ])
            ->assertAccepted()
            ->assertJsonPath('status', 'ignored')
            ->assertJsonPath('action', 'ignored')
            ->assertJsonPath('attendance_id', $attendance->id);

        $attendance->refresh();

        $this->assertNull($attendance->checked_out_at);
        $this->assertSame(
            ['checked_in'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_ATTENDANCE)
                ->where('subject_id', $attendance->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertDatabaseHas('hikvision_event_logs', [
            'device_serial' => 'DS-K1A802AEF',
            'event_serial_no' => '185',
            'attendance_id' => $attendance->id,
        ]);

        $this->postJson('/api/biometric/hikvision/callback', $checkOutPayload, [
            'X-Biometric-Token' => 'forward-token',
        ])
            ->assertAccepted()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('action', 'check_out');

        $attendance->refresh();

        $this->assertNotNull($attendance->checked_out_at);
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_helper_forwarded_attendance_callback_rejects_invalid_forward_token(): void
    {
        $this->postJson('/api/biometric/hikvision/callback', $this->forwardedPayload('00000001', 186), [
            'X-Biometric-Token' => 'wrong-token',
        ])->assertUnauthorized();
    }

    public function test_helper_forwarded_attendance_callback_redacts_forward_tokens_from_logs(): void
    {
        Log::spy();

        $this->postJson('/api/biometric/hikvision/callback?token=query-secret', $this->forwardedPayload('00000001', 186), [
            'X-Biometric-Token' => 'forward-token',
        ])->assertAccepted();

        Log::shouldHaveReceived('info')
            ->with(
                'Received Hikvision attendance callback',
                Mockery::on(function (array $context): bool {
                    return ($context['query_params']['token'] ?? null) === '[redacted]'
                        && ($context['headers']['x-biometric-token'][0] ?? null) === '[redacted]'
                        && ! str_contains(json_encode($context) ?: '', 'query-secret')
                        && ! str_contains(json_encode($context) ?: '', 'forward-token');
                })
            )
            ->once();
    }

    public function test_hikvision_event_log_is_rolled_back_when_processing_fails_so_retries_can_succeed(): void
    {
        $employee = User::factory()->create([
            'name' => 'Coach Kai',
            'email' => 'coach-kai@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'hikvision_employee_no' => $this->hikvisionEmployeeNo($employee->id),
            'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
            'biometric_fingerprint_id' => 1,
            'biometric_enrolled_at' => now(),
        ]);

        $payload = $this->forwardedPayload($profile->hikvision_employee_no, 186, '2026-04-13T23:19:21+08:00');

        $systemActivityService = Mockery::mock(SystemActivityService::class);
        $systemActivityService->shouldReceive('recordSubjectEvent')
            ->once()
            ->andThrow(new RuntimeException('System activity write failed.'));
        $systemActivityService->shouldReceive('recordSubjectEvent')
            ->once()
            ->andReturn(new SystemActivity());

        $this->app->instance(SystemActivityService::class, $systemActivityService);

        $hikvisionAttendanceService = $this->app->make(HikvisionAttendanceService::class);

        try {
            $hikvisionAttendanceService->ingest($payload);
            $this->fail('Expected the first attendance ingest attempt to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('System activity write failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('hikvision_event_logs', 0);
        $this->assertDatabaseCount('attendances', 0);

        $result = $hikvisionAttendanceService->ingest($payload);

        $this->assertSame('processed', $result['status']);
        $this->assertSame('check_in', $result['action']);
        $this->assertDatabaseCount('hikvision_event_logs', 1);
        $this->assertDatabaseCount('attendances', 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function forwardedPayload(
        string $employeeNo,
        int $serialNo,
        string $occurredAt = '2026-04-13T23:19:21+08:00',
    ): array {
        return [
            'device_ip' => '192.168.250.201',
            'device_name' => 'DS-K1A802AEF',
            'device_serial' => 'DS-K1A802AEF',
            'employee_no' => $employeeNo,
            'event_serial_no' => $serialNo,
            'event_type' => 'AccessControllerEvent',
            'event_sub_type' => 38,
            'occurred_at' => $occurredAt,
            'raw' => [
                'eventType' => 'AccessControllerEvent',
                'dateTime' => $occurredAt,
                'AccessControllerEvent' => [
                    'deviceName' => 'DS-K1A802AEF',
                    'majorEventType' => 5,
                    'subEventType' => 38,
                    'employeeNoString' => $employeeNo,
                    'serialNo' => $serialNo,
                ],
            ],
        ];
    }

    private function hikvisionEmployeeNo(int $employeeId): string
    {
        return str_pad((string) $employeeId, 8, '0', STR_PAD_LEFT);
    }
}
