<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hikvision_employee_no' => str_pad((string) fake()->unique()->numberBetween(1, 99999999), 8, '0', STR_PAD_LEFT),
            'biometric_status' => EmployeeProfile::STATUS_NOT_ENROLLED,
            'biometric_fingerprint_id' => null,
            'biometric_enrolled_at' => null,
            'biometric_last_error' => null,
        ];
    }
}
