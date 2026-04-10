<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => AuditEvent::SUBJECT_CASH_ADVANCE,
            'subject_id' => fake()->numberBetween(1, 9999),
            'subject_label' => 'Cash Advance #'.fake()->numberBetween(1, 9999).' - '.fake()->name(),
            'event' => 'requested',
            'title' => 'Cash advance requested',
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
