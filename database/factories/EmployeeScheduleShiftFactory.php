<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\EmployeeScheduleShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeScheduleShift>
 */
class EmployeeScheduleShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_profile_id' => EmployeeProfile::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];
    }
}
