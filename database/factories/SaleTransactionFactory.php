<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SaleTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaleTransaction>
 */
class SaleTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::query()->first() ?: Branch::create([
            'name' => 'Factory Branch',
            'status' => Branch::STATUS_OPEN,
            'country_code' => Branch::COUNTRY_PHILIPPINES,
            'city' => 'Naga City',
        ]);

        $processor = User::query()->first() ?: User::factory()->create([
            'name' => 'Factory User',
            'email' => 'factory-user@example.com',
            'status' => User::STATUS_ACTIVE,
            'password' => Hash::make(Str::random(24)),
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
        ]);

        return [
            'branch_id' => $branch->id,
            'member_id' => null,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 350,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $processor->id,
            'sold_at' => now(),
            'customer_name' => fake()->name(),
            'item_name' => 'Walk-in',
            'details' => [
                'notes' => fake()->sentence(),
            ],
        ];
    }
}
