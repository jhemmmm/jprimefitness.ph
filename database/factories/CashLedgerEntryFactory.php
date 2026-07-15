<?php

namespace Database\Factories;

use App\Models\CashLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashLedgerEntry>
 */
class CashLedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => null,
            'type' => CashLedgerEntry::TYPE_EXPENSE,
            'category' => 'supplies',
            'amount' => -150,
            'description' => fake()->sentence(3),
            'recorded_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
