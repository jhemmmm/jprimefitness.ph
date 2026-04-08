<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use App\Models\WalkIn;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        Attendance::query()->delete();

        $walkIns = WalkIn::query()
            ->orderByDesc('visited_at')
            ->take(8)
            ->get();
        $members = User::role('member')->get();
        $employees = User::role(['employee', 'coach', 'manager', 'admin', 'staff'])->get();
        $recordedBy = User::role(['super admin', 'admin', 'manager', 'staff'])->first()?->id;

        foreach ($walkIns as $index => $walkIn) {
            $checkedIn = ($walkIn->visited_at ?? Carbon::now())->copy();

            Attendance::query()->create([
                'attendee_type' => Attendance::TYPE_WALK_IN,
                'walk_in_id' => $walkIn->id,
                'name' => $walkIn->name,
                'checked_in_at' => $checkedIn,
                'checked_out_at' => $index % 3 === 0 ? null : $checkedIn->copy()->addMinutes(45 + ($index * 5)),
                'recorded_by' => $recordedBy ?? $walkIn->served_by,
            ]);
        }

        foreach ($members->take(8) as $index => $member) {
            $checkedIn = Carbon::now()
                ->subDays($index)
                ->setTime(6 + ($index % 7), ($index * 9) % 60);

            Attendance::query()->create([
                'attendee_type' => Attendance::TYPE_MEMBER,
                'user_id' => $member->id,
                'name' => $member->name,
                'checked_in_at' => $checkedIn,
                'checked_out_at' => $index % 4 === 0 ? null : $checkedIn->copy()->addMinutes(60 + ($index * 10)),
                'recorded_by' => $recordedBy,
            ]);
        }

        foreach ($employees->take(6) as $index => $employee) {
            $checkedIn = Carbon::today()
                ->subDays($index % 5)
                ->setTime(6 + ($index % 3), ($index * 11) % 30);

            Attendance::query()->create([
                'attendee_type' => Attendance::TYPE_EMPLOYEE,
                'user_id' => $employee->id,
                'name' => $employee->name,
                'checked_in_at' => $checkedIn,
                'checked_out_at' => $index === 0 ? null : $checkedIn->copy()->addHours(8),
                'recorded_by' => $recordedBy,
            ]);
        }
    }
}
