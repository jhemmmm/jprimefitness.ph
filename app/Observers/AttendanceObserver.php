<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;
use DateTimeInterface;

class AttendanceObserver
{
    /**
     * Record a new attendance check-in activity.
     *
     * @return void
     */
    public function created(Attendance $attendance): void
    {
        $this->record($attendance, 'checked_in', $attendance->checked_in_at ?? now());
    }

    /**
     * Record attendance update activity.
     *
     * @return void
     */
    public function updated(Attendance $attendance): void
    {
        if (
            $attendance->wasChanged('checked_out_at')
            && $attendance->getOriginal('checked_out_at') === null
            && $attendance->checked_out_at !== null
        ) {
            $this->record($attendance, 'checked_out', $attendance->checked_out_at);

            return;
        }

        $this->record($attendance, 'updated');
    }

    /**
     * Record attendance deletion activity.
     *
     * @return void
     */
    public function deleted(Attendance $attendance): void
    {
        $this->record($attendance, 'deleted');
    }

    /**
     * Record an attendance system activity.
     *
     * @return void
     */
    private function record(Attendance $attendance, string $event, DateTimeInterface|string|null $occurredAt = null): void
    {
        $attendance->loadMissing(['user', 'recordedBy']);

        app(SystemActivityService::class)->recordSubjectEvent(
            SystemActivity::SUBJECT_ATTENDANCE,
            $attendance->id,
            $event,
            $this->snapshot($attendance),
            [],
            auth()->id(),
            $this->actorName($attendance),
            $occurredAt ?? now(),
        );
    }

    /**
     * Resolve the activity actor name.
     *
     * @return string|null
     */
    private function actorName(Attendance $attendance): ?string
    {
        if (auth()->user()?->name) {
            return auth()->user()->name;
        }

        if ($attendance->source === Attendance::SOURCE_HIKVISION && $attendance->source_device_serial) {
            return 'Hikvision '.$attendance->source_device_serial;
        }

        if ($attendance->source === Attendance::SOURCE_KIOSK && $attendance->source_device_serial) {
            return 'Kiosk '.$attendance->source_device_serial;
        }

        return null;
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
