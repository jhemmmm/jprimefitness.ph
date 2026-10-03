<?php

namespace Database\Factories;

use App\Models\SystemActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemActivity>
 */
class SystemActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => SystemActivity::SUBJECT_PAYROLL,
            'subject_id' => fake()->numberBetween(1, 9999),
            'subject_label' => 'Payroll #'.fake()->numberBetween(1, 9999).' - '.fake()->name(),
            'event' => 'created',
            'title' => 'Payroll created',
            'message' => fake()->sentence(),
            'actor_user_id' => null,
            'actor_name' => fake()->name(),
            'metadata' => [
                'employee_id' => fake()->numberBetween(1, 9999),
                'employee_name' => fake()->name(),
                'amount' => fake()->randomFloat(2, 100, 5000),
            ],
            'occurred_at' => now(),
        ];
    }
}
