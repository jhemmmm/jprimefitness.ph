<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\RatePlan;
use App\Models\WalkIn;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WalkInSeeder extends Seeder
{
    public function run(): void
    {
        $branches  = Branch::all();
        $rates = RatePlan::all();

        for ($i = 0; $i < 50; $i++) {
            WalkIn::create([
                'branch_id' => fake()->randomElement($branches)->id,
                'rate_plan_id' => fake()->randomElement($rates)->id,
                'name' => fake()->name(),
                'phone' => fake()->phoneNumber(),
                'amount_paid' => fake()->randomFloat(2, 50, 200),
                'visited_at' => Carbon::now()->subDays(rand(0, 30))->setHour(rand(6, 22))->setMinute(rand(0, 59)),
            ]);
        }
    }
}
