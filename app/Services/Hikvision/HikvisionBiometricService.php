<?php

namespace App\Services\Hikvision;

use App\Jobs\RunEmployeeBiometricEnrollment;
use App\Models\AuditEvent;
use App\Models\EmployeeBiometricSession;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AuditHistoryService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

class HikvisionBiometricService
{
    public function __construct(
        private HikvisionHelperClient $hikvisionHelperClient,
        private AuditHistoryService $auditHistoryService,
    ) {}

    public function ensureProfile(User $employee): EmployeeProfile
    {
        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $employee->id],
            [
                'hikvision_employee_no' => $this->defaultEmployeeNo($employee),
                'biometric_status' => EmployeeProfile::STATUS_NOT_ENROLLED,
            ],
        );

        if ($this->shouldNormalizeEmployeeNo($profile->hikvision_employee_no, $employee)) {
            $profile->update([
                'hikvision_employee_no' => $this->defaultEmployeeNo($employee),
            ]);
        }

        return $profile->fresh();
    }

    public function startEnrollment(User $employee, ?User $actor = null): EmployeeBiometricSession
    {
        $profile = $this->ensureProfile($employee);

        $activeSession = EmployeeBiometricSession::query()
            ->where('employee_profile_id', $profile->id)
            ->whereIn('status', [
                EmployeeBiometricSession::STATUS_PENDING,
                EmployeeBiometricSession::STATUS_CAPTURING,
                EmployeeBiometricSession::STATUS_UPLOADING,
            ])
            ->latest('id')
            ->first();

        if ($activeSession) {
            $activeSession = $this->refreshSession($activeSession);

            if ($this->isActiveSession($activeSession)) {
                return $activeSession->fresh(['employeeProfile.user', 'startedBy']);
            }
        }

        $profile->update([
            'hikvision_employee_no' => $profile->hikvision_employee_no ?: $this->defaultEmployeeNo($employee),
            'biometric_status' => EmployeeProfile::STATUS_ENROLLING,
            'biometric_last_error' => null,
        ]);

        $session = EmployeeBiometricSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'employee_profile_id' => $profile->id,
            'status' => EmployeeBiometricSession::STATUS_PENDING,
            'fingerprint_id' => (int) config('hikvision.fingerprint_id', 1),
            'started_by' => $actor?->id,
        ]);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'biometric_enrollment_started',
            $this->employeeSnapshot($employee->fresh()->loadMissing('roles', 'employeeProfile')),
            [],
            $actor?->id,
            $actor?->name,
            now(),
        );

        RunEmployeeBiometricEnrollment::dispatch($session->id)
            ->onConnection('background');

        return $session->fresh(['employeeProfile.user', 'startedBy']);
    }

    public function processEnrollment(EmployeeBiometricSession $session): EmployeeBiometricSession
    {
        $session = $session->fresh(['employeeProfile.user']);

        if (! $session instanceof EmployeeBiometricSession || ! $session->employeeProfile || ! $session->employeeProfile->user) {
            return $session;
        }

        if (! $this->isActiveSession($session)) {
            return $session;
        }

        $session = $this->refreshSession($session);

        if (! $this->isActiveSession($session)) {
            return $session;
        }

        $profile = $session->employeeProfile;
        $employee = $profile->user;

        try {
            $session->update([
                'status' => EmployeeBiometricSession::STATUS_CAPTURING,
                'error_message' => null,
            ]);

            $this->hikvisionHelperClient->upsertEmployee($employee, $profile);

            $session->refresh();

            if (! $this->isActiveSession($session)) {
                return $session;
            }

            $enrollment = $this->hikvisionHelperClient->enrollFingerprint(
                $profile->fresh(),
                (int) $session->fingerprint_id,
                (int) config('hikvision.enrollment_timeout', 30),
            );

            if (! ($enrollment['savedToDevice'] ?? false)) {
                throw new \RuntimeException((string) ($enrollment['message'] ?? 'The biometric enrollment service did not save the fingerprint to the device.'));
            }

            $profile->update([
                'biometric_status' => EmployeeProfile::STATUS_ENROLLED,
                'biometric_fingerprint_id' => (int) $session->fingerprint_id,
                'biometric_enrolled_at' => now(),
                'biometric_last_error' => null,
            ]);

            $session->update([
                'status' => EmployeeBiometricSession::STATUS_SUCCEEDED,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $this->auditHistoryService->recordSubjectEvent(
                AuditEvent::SUBJECT_EMPLOYEE,
                $employee->id,
                'biometric_enrolled',
                $this->employeeSnapshot($employee->fresh()->loadMissing('roles', 'employeeProfile')),
                [],
                $session->started_by,
                $session->startedBy?->name,
                now(),
            );
        } catch (Throwable $throwable) {
            $errorMessage = $throwable->getMessage();

            $profile->refresh();
            $profile->update([
                'biometric_status' => $profile->biometric_enrolled_at || $profile->biometric_fingerprint_id
                    ? EmployeeProfile::STATUS_ENROLLED
                    : EmployeeProfile::STATUS_FAILED,
                'biometric_last_error' => $errorMessage,
            ]);

            $session->update([
                'status' => EmployeeBiometricSession::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $errorMessage,
            ]);

            $this->auditHistoryService->recordSubjectEvent(
                AuditEvent::SUBJECT_EMPLOYEE,
                $employee->id,
                'biometric_failed',
                $this->employeeSnapshot($employee->fresh()->loadMissing('roles', 'employeeProfile')),
                ['error' => $errorMessage],
                $session->started_by,
                $session->startedBy?->name,
                now(),
            );
        }

        return $session->fresh(['employeeProfile.user', 'startedBy']);
    }

    public function refreshSession(EmployeeBiometricSession $session): EmployeeBiometricSession
    {
        if (! $this->isActiveSession($session)) {
            return $session->fresh(['employeeProfile.user', 'startedBy']);
        }

        if ($session->created_at && $session->created_at->lt(now()->subSeconds((int) config('hikvision.enrollment_timeout', 30) + 5))) {
            $session->update([
                'status' => EmployeeBiometricSession::STATUS_EXPIRED,
                'completed_at' => now(),
                'error_message' => 'Fingerprint enrollment timed out.',
            ]);

            $profile = $session->employeeProfile?->fresh();

            if ($profile) {
                $profile->update([
                    'biometric_status' => $profile->biometric_enrolled_at || $profile->biometric_fingerprint_id
                        ? EmployeeProfile::STATUS_ENROLLED
                        : EmployeeProfile::STATUS_NOT_ENROLLED,
                    'biometric_last_error' => 'Fingerprint enrollment timed out.',
                ]);
            }
        }

        return $session->fresh(['employeeProfile.user', 'startedBy']);
    }

    public function cancelEnrollment(User $employee, EmployeeBiometricSession $session, ?User $actor = null): EmployeeBiometricSession
    {
        $profile = $this->ensureProfile($employee)->fresh();

        if ((int) $session->employee_profile_id !== (int) $profile->id) {
            abort(404);
        }

        if ($this->isTerminalStatus($session->status)) {
            return $session->fresh(['employeeProfile.user', 'startedBy']);
        }

        $session->update([
            'status' => EmployeeBiometricSession::STATUS_CANCELLED,
            'completed_at' => now(),
            'error_message' => null,
        ]);

        $profile->update([
            'biometric_status' => $profile->biometric_enrolled_at || $profile->biometric_fingerprint_id
                ? EmployeeProfile::STATUS_ENROLLED
                : EmployeeProfile::STATUS_NOT_ENROLLED,
            'biometric_last_error' => null,
        ]);

        return $session->fresh(['employeeProfile.user', 'startedBy']);
    }

    public function removeFingerprint(User $employee, ?User $actor = null): EmployeeProfile
    {
        $profile = $this->ensureProfile($employee)->fresh();

        if ($profile->biometric_fingerprint_id !== null) {
            $this->hikvisionHelperClient->deleteFingerprint($profile, (int) $profile->biometric_fingerprint_id);
        }

        $profile->update([
            'biometric_status' => EmployeeProfile::STATUS_NOT_ENROLLED,
            'biometric_fingerprint_id' => null,
            'biometric_enrolled_at' => null,
            'biometric_last_error' => null,
        ]);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'biometric_removed',
            $this->employeeSnapshot($employee->fresh()->loadMissing('roles', 'employeeProfile')),
            [],
            $actor?->id,
            $actor?->name,
            now(),
        );

        return $profile->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeProfile(?EmployeeProfile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'daily_rate' => round((float) ($profile->daily_rate ?? 0), 2),
            'pay_frequency' => $profile->pay_frequency,
            'hikvision_employee_no' => $profile->hikvision_employee_no,
            'biometric_status' => $profile->biometric_status,
            'biometric_fingerprint_id' => $profile->biometric_fingerprint_id,
            'biometric_enrolled_at' => $profile->biometric_enrolled_at?->toISOString(),
            'biometric_last_error' => $profile->biometric_last_error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSession(EmployeeBiometricSession $session): array
    {
        return [
            'id' => $session->id,
            'uuid' => $session->uuid,
            'status' => $session->status,
            'fingerprint_id' => $session->fingerprint_id,
            'completed_at' => $session->completed_at?->toISOString(),
            'error_message' => $session->error_message,
            'employee_profile' => $this->serializeProfile($session->employeeProfile),
        ];
    }

    private function defaultEmployeeNo(User $employee): string
    {
        return str_pad((string) $employee->id, 8, '0', STR_PAD_LEFT);
    }

    private function shouldNormalizeEmployeeNo(?string $employeeNo, User $employee): bool
    {
        $employeeNo = trim((string) $employeeNo);

        if ($employeeNo === '') {
            return true;
        }

        return $employeeNo === 'EMP-'.$employee->id;
    }

    private function isActiveSession(EmployeeBiometricSession $session): bool
    {
        return in_array($session->status, [
            EmployeeBiometricSession::STATUS_PENDING,
            EmployeeBiometricSession::STATUS_CAPTURING,
            EmployeeBiometricSession::STATUS_UPLOADING,
        ], true);
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, [
            EmployeeBiometricSession::STATUS_SUCCEEDED,
            EmployeeBiometricSession::STATUS_FAILED,
            EmployeeBiometricSession::STATUS_EXPIRED,
            EmployeeBiometricSession::STATUS_CANCELLED,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeSnapshot(User $employee): array
    {
        $employee->loadMissing('roles', 'employeeProfile');
        $employeeProfile = $employee->employeeProfile;

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'status' => $employee->status,
            'role_names' => $employee->roles->pluck('name')->values()->all(),
            'daily_rate' => round((float) ($employeeProfile?->daily_rate ?? 0), 2),
            'pay_frequency' => $employeeProfile?->pay_frequency,
            'biometric_status' => $employeeProfile?->biometric_status,
            'biometric_fingerprint_id' => $employeeProfile?->biometric_fingerprint_id,
            'biometric_enrolled_at' => $employeeProfile?->biometric_enrolled_at?->toISOString(),
        ];
    }
}
