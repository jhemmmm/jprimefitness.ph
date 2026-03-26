<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();
        $members = User::role('member')->get();
        $employees = User::role('employee')->get();

        if ($branches->isEmpty()) {
            return;
        }

        // Walk-in attendances
        $walkInNames = [
            'Carlo Bernabe',
            'Ana Villareal',
            'Miguel Cruz',
            'Liza Fontanilla',
            'Ramon Aquino',
            'Trisha Gomez',
        ];

        foreach ($walkInNames as $i => $name) {
            $branch = $branches[$i % $branches->count()];
            $checkedIn = Carbon::now()->subDays(rand(0, 14))->setHour(rand(6, 18))->setMinute(rand(0, 59))->setSecond(0);
            Attendance::create([
                'branch_id' => $branch->id,
                'attendee_type' => 'walk_in',
                'name' => $name,
                'checked_in_at' => $checkedIn,
                'checked_out_at' => rand(0, 1) ? $checkedIn->copy()->addMinutes(rand(30, 180)) : null,
            ]);
        }

        // Member attendances (up to 8)
        foreach ($members->take(8) as $i => $member) {
            $defaultBranch = $branches[$i % $branches->count()];
            $memberBranchId = $member->branches->first()?->id ?? $defaultBranch->id;
            $checkedIn = Carbon::now()->subDays(rand(0, 14))->setHour(rand(6, 18))->setMinute(rand(0, 59))->setSecond(0);
            Attendance::create([
                'branch_id' => $memberBranchId,
                'attendee_type' => 'member',
                'user_id' => $member->id,
                'name' => $member->name,
                'checked_in_at' => $checkedIn,
                'checked_out_at' => rand(0, 1) ? $checkedIn->copy()->addMinutes(rand(45, 120)) : null,
            ]);
        }

        // Employee attendances (up to 4)
        foreach ($employees->take(4) as $i => $emp) {
            $branch = $branches[$i % $branches->count()];
            $employeeBranchId = $emp->branches->first()?->id ?? $branch->id;
            $checkedIn = Carbon::today()->setHour(rand(6, 9))->setMinute(rand(0, 30))->setSecond(0);
            Attendance::create([
                'branch_id' => $employeeBranchId,
                'attendee_type' => 'employee',
                'user_id' => $emp->id,
                'name' => $emp->name,
                'checked_in_at' => $checkedIn,
                'checked_out_at' => rand(0, 1) ? $checkedIn->copy()->addHours(rand(6, 10)) : null,
            ]);
        }
    }
}
