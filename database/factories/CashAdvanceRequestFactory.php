<?php

namespace Database\Factories;

use App\Models\CashAdvanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashAdvanceRequest>
 */
class CashAdvanceRequestFactory extends Factory
{
    protected $model = CashAdvanceRequest::class;

    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'amount' => 1500,
            'reason' => 'Emergency expense',
            'status' => CashAdvanceRequest::STATUS_PENDING,
        ];
    }
}
