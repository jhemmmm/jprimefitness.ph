<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutoCheckoutAttendanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stale_member_and_walk_in_check_ins_are_closed_but_recent_and_employee_ones_are_not(): void
    {
        config(['jprime.attendance_auto_checkout_hours' => 4]);
        $this->travelTo(Carbon::parse('2026-05-01 14:00:00'));

        $member = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $employee = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $create = fn (string $type, ?User $user, string $checkedInAt) => Attendance::create([
            'attendee_type' => $type,
            'user_id' => $user?->id,
            'name' => $user?->name ?? 'Walk-in Wally',
            'checked_in_at' => $checkedInAt,
            'source' => Attendance::SOURCE_KIOSK,
        ]);

        $staleMember = $create(Attendance::TYPE_MEMBER, $member, '2026-05-01 08:30:00');
        $staleWalkIn = $create(Attendance::TYPE_WALK_IN, null, '2026-05-01 09:59:00');
        $recentWalkIn = $create(Attendance::TYPE_WALK_IN, null, '2026-05-01 10:01:00');
        $staleEmployee = $create(Attendance::TYPE_EMPLOYEE, $employee, '2026-05-01 06:00:00');

        $this->artisan('panel:auto-checkout-attendance')
            ->expectsOutputToContain('Auto-checked out 2 attendance record(s).')
            ->assertExitCode(0);

        $this->assertSame('2026-05-01 12:30:00', $staleMember->fresh()->checked_out_at->toDateTimeString());
        $this->assertSame('2026-05-01 13:59:00', $staleWalkIn->fresh()->checked_out_at->toDateTimeString());
        $this->assertNull($recentWalkIn->fresh()->checked_out_at);
        $this->assertNull($staleEmployee->fresh()->checked_out_at);
    }
}
