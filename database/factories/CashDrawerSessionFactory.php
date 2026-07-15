<?php

namespace Database\Factories;

use App\Models\CashDrawerSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashDrawerSession>
 */
class CashDrawerSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_open' => true,
            'opening_float' => 1000,
            'opened_by' => User::factory(),
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'is_open' => null,
            'closed_at' => now(),
            'expected_cash' => 1000,
            'counted_cash' => 1000,
            'over_short' => 0,
            'deposited_amount' => 0,
        ]);
    }
}
