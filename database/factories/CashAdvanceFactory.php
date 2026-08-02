<?php

namespace Database\Factories;

use App\Models\CashAdvance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashAdvance>
 */
class CashAdvanceFactory extends Factory
{
    protected $model = CashAdvance::class;

    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'amount' => 500,
            'method' => CashAdvance::METHOD_CASH,
            'paid_at' => now(),
        ];
    }
}
