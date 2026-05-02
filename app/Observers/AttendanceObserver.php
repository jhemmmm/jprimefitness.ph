<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;

class AttendanceObserver
{
    public function updated(Attendance $attendance): void
    {
        $this->record($attendance, 'updated');
    }

    public function deleted(Attendance $attendance): void
    {
        $this->record($attendance, 'deleted');
    }

    private function record(Attendance $attendance, string $event): void
    {
        $attendance->loadMissing(['user', 'recordedBy']);

        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_ATTENDANCE,
            $attendance->id,
            $event,
            $this->snapshot($attendance),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'name' => $attendance->name ?: $attendance->user?->name,
            'attendee_type' => $attendance->attendee_type,
            'checked_in_at' => $attendance->checked_in_at?->toISOString(),
            'checked_out_at' => $attendance->checked_out_at?->toISOString(),
            'source' => $attendance->source,
            'source_device_serial' => $attendance->source_device_serial,
        ];
    }
}
