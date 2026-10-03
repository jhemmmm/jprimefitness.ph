<?php

namespace Database\Factories;

use App\Models\EmployeeBiometricSession;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeBiometricSession>
 */
class EmployeeBiometricSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'employee_profile_id' => EmployeeProfile::factory(),
            'status' => EmployeeBiometricSession::STATUS_PENDING,
            'fingerprint_id' => 1,
            'started_by' => User::factory(),
            'completed_at' => null,
            'error_message' => null,
        ];
    }
}
