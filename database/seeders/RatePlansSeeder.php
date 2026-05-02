<?php

namespace Database\Seeders;

use App\Models\RatePlan;
use Illuminate\Database\Seeder;

class RatePlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Daily Pass',
                'duration_days' => 1,
                'price' => 120.00,
                'is_active' => true,
                'description' => 'Single-day access for trial members and guest check-ins.',
            ],
            [
                'name' => 'Monthly',
                'duration_days' => 30,
                'price' => 1600.00,
                'is_active' => true,
                'description' => 'Thirty-day gym membership with full facility access.',
            ],
            [
                'name' => '3 Months',
                'duration_days' => 90,
                'price' => 4200.00,
                'is_active' => true,
                'description' => 'Quarterly plan for members training on a regular schedule.',
            ],
            [
                'name' => '6 Months',
                'duration_days' => 180,
                'price' => 7800.00,
                'is_active' => true,
                'description' => 'Six-month commitment with stronger value per day.',
            ],
            [
                'name' => 'Annual',
                'duration_days' => 365,
                'price' => 14400.00,
                'is_active' => true,
                'description' => 'Best-value membership for year-round training.',
            ],
        ];

        foreach ($plans as $plan) {
            RatePlan::query()->updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
