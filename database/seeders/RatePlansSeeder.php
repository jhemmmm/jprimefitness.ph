<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RatePlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            ['name' => 'Daily Pass', 'duration_days' => 1, 'is_active' => true],
            ['name' => 'Monthly',  'duration_days' => 30, 'is_active' => true],
            ['name' => '3 Months', 'duration_days' => 90, 'is_active' => true],
            ['name' => '6 Months', 'duration_days' => 180, 'is_active' => true],
            ['name' => 'Annual', 'duration_days' => 365, 'is_active' => true],
        ];
        foreach ($plans as $plan) {
            \App\Models\RatePlan::firstOrCreate($plan);
        }
    }
}
