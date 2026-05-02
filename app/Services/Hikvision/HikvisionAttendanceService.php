<?php

namespace App\Services\Hikvision;

use App\Models\Attendance;
use App\Models\EmployeeProfile;
use App\Models\HikvisionEventLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HikvisionAttendanceService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{attendance: ?Attendance, status: string, action: string, employee_no: string, event_serial_no: string, event_type: string, occurred_at: string}
     */
    public function ingest(array $payload): array
    {
        $normalized = $this->normalize($payload);

        if (! $normalized['is_fingerprint_employee_event']) {
            return $this->ignoredResponse($normalized);
        }

        return DB::transaction(function () use ($normalized, $payload): array {
            try {
                $eventLog = HikvisionEventLog::query()->create([
                    'device_serial' => $normalized['device_serial'],
                    'event_serial_no' => $normalized['event_serial_no'],
                    'event_type' => $normalized['event_type'],
                    'employee_no' => $normalized['employee_no'],
                    'payload' => $payload,
                ]);
            } catch (QueryException $exception) {
                $eventLog = HikvisionEventLog::query()
                    ->where('device_serial', $normalized['device_serial'])
                    ->where('event_serial_no', $normalized['event_serial_no'])
                    ->lockForUpdate()
                    ->first();

                if (! $eventLog) {
                    throw $exception;
                }

                if ($eventLog->processed_at) {
                    return $this->duplicateResponse($normalized);
                }
            }

            $profile = EmployeeProfile::query()
                ->where('hikvision_employee_no', $normalized['employee_no'])
                ->with('user')
                ->first();

            if (! $profile?->user) {
                $eventLog->update(['processed_at' => now()]);

                return $this->ignoredResponse($normalized);
            }

            $eventTime = Carbon::parse($normalized['occurred_at']);
            $latestAttendance = Attendance::query()
                ->where('attendee_type', Attendance::TYPE_EMPLOYEE)
                ->where('user_id', $profile->user->id)
                ->latest('checked_in_at')
                ->lockForUpdate()
                ->first();

            if ($latestAttendance && $this->shouldIgnoreStaleEvent($latestAttendance, $eventTime)) {
                $eventLog->update([
                    'attendance_id' => $latestAttendance->id,
                    'processed_at' => now(),
                ]);

                return $this->ignoredResponse($normalized, $latestAttendance);
            }

            if ($latestAttendance && ! $latestAttendance->checked_out_at) {
                $latestAttendance->update([
                    'checked_out_at' => $eventTime,
                ]);

                $attendance = $latestAttendance->fresh(['user', 'recordedBy']);
                $action = 'check_out';
            } else {
                $attendance = Attendance::query()->create([
                    'attendee_type' => Attendance::TYPE_EMPLOYEE,
                    'user_id' => $profile->user->id,
                    'name' => $profile->user->name,
                    'checked_in_at' => $eventTime,
                    'checked_out_at' => null,
                    'recorded_by' => null,
                    'source' => Attendance::SOURCE_HIKVISION,
                    'source_device_serial' => $normalized['device_serial'],
                ])->fresh(['user', 'recordedBy']);
                $action = 'check_in';
            }

            $eventLog->update([
                'attendance_id' => $attendance->id,
                'processed_at' => now(),
            ]);

            return [
                'attendance' => $attendance,
                'status' => 'processed',
                'action' => $action,
                'employee_no' => $normalized['employee_no'],
                'event_serial_no' => $normalized['event_serial_no'],
                'event_type' => $normalized['event_type'],
                'occurred_at' => $normalized['occurred_at'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{device_serial:string, device_name:string, employee_no:string, event_serial_no:string, event_type:string, is_fingerprint_employee_event:bool, occurred_at:string}
     */
    private function normalize(array $payload): array
    {
        $raw = Arr::get($payload, 'raw', []);
        $accessEvent = is_array($raw) ? Arr::get($raw, 'AccessControllerEvent', []) : [];
        $employeeNo = trim((string) (
            Arr::get($payload, 'employee_no')
            ?? Arr::get($accessEvent, 'employeeNoString')
            ?? ''
        ));
        $eventType = (string) ($payload['event_type'] ?? Arr::get($raw, 'eventType') ?? 'AccessControllerEvent');
        $occurredAt = (string) ($payload['occurred_at'] ?? Arr::get($raw, 'dateTime') ?? now()->toISOString());
        $deviceSerial = trim((string) (
            Arr::get($payload, 'device_serial')
            ?? Arr::get($raw, 'deviceSerialNo')
            ?? Arr::get($payload, 'device_name')
            ?? Arr::get($payload, 'device_ip')
            ?? 'windows-helper'
        ));
        $deviceName = trim((string) (
            Arr::get($payload, 'device_name')
            ?? Arr::get($accessEvent, 'deviceName')
            ?? $deviceSerial
        ));
        $eventSerialNo = (string) (
            Arr::get($payload, 'event_serial_no')
            ?? Arr::get($accessEvent, 'serialNo')
            ?? sha1(json_encode($payload) ?: Str::uuid()->toString())
        );
        $eventSubType = (string) (
            Arr::get($payload, 'event_sub_type')
            ?? Arr::get($accessEvent, 'subEventType')
            ?? ''
        );

        return [
            'device_serial' => $deviceSerial,
            'device_name' => $deviceName,
            'employee_no' => $employeeNo,
            'event_serial_no' => $eventSerialNo,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'is_fingerprint_employee_event' => $employeeNo !== ''
                && Str::lower($eventType) === 'accesscontrollerevent'
                && in_array($eventSubType, ['38', '49'], true),
        ];
    }

    /**
     * @param  array{device_serial:string, device_name:string, employee_no:string, event_serial_no:string, event_type:string, is_fingerprint_employee_event:bool, occurred_at:string}  $normalized
     * @return array{attendance: ?Attendance, status: string, action: string, employee_no: string, event_serial_no: string, event_type: string, occurred_at: string}
     */
    private function duplicateResponse(array $normalized): array
    {
        return [
            'attendance' => null,
            'status' => 'duplicate',
            'action' => 'duplicate',
            'employee_no' => $normalized['employee_no'],
            'event_serial_no' => $normalized['event_serial_no'],
            'event_type' => $normalized['event_type'],
            'occurred_at' => $normalized['occurred_at'],
        ];
    }

    /**
     * @param  array{device_serial:string, device_name:string, employee_no:string, event_serial_no:string, event_type:string, is_fingerprint_employee_event:bool, occurred_at:string}  $normalized
     * @return array{attendance: ?Attendance, status: string, action: string, employee_no: string, event_serial_no: string, event_type: string, occurred_at: string}
     */
    private function ignoredResponse(array $normalized, ?Attendance $attendance = null): array
    {
        return [
            'attendance' => $attendance,
            'status' => 'ignored',
            'action' => 'ignored',
            'employee_no' => $normalized['employee_no'],
            'event_serial_no' => $normalized['event_serial_no'],
            'event_type' => $normalized['event_type'],
            'occurred_at' => $normalized['occurred_at'],
        ];
    }

    private function shouldIgnoreStaleEvent(Attendance $attendance, Carbon $eventTime): bool
    {
        if ($attendance->checked_in_at && $eventTime->lessThanOrEqualTo($attendance->checked_in_at)) {
            return true;
        }

        return $attendance->checked_out_at !== null
            && $eventTime->lessThanOrEqualTo($attendance->checked_out_at);
    }
}
