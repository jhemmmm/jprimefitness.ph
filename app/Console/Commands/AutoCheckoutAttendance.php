<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('panel:auto-checkout-attendance')]
#[Description('Check out members and walk-ins who forgot to time out at the kiosk')]
class AutoCheckoutAttendance extends Command
{
    public function handle(): int
    {
        $hours = (int) config('jprime.attendance_auto_checkout_hours');
        $closed = 0;

        // Employees are excluded on purpose: payroll pays from checked_out_at, so a
        // fabricated time-out would fabricate hours. Their gaps stay visible for staff to fix.
        Attendance::query()
            ->whereIn('attendee_type', [Attendance::TYPE_MEMBER, Attendance::TYPE_WALK_IN])
            ->whereNull('checked_out_at')
            ->where('checked_in_at', '<=', now()->subHours($hours))
            ->chunkById(100, function ($attendances) use ($hours, &$closed): void {
                foreach ($attendances as $attendance) {
                    // Per-row update so AttendanceObserver + sync outbox fire like a manual check-out.
                    $attendance->update(['checked_out_at' => $attendance->checked_in_at->copy()->addHours($hours)]);
                    $closed++;
                }
            });

        $this->info("Auto-checked out {$closed} attendance record(s).");

        return self::SUCCESS;
    }
}
