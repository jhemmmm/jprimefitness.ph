<?php

namespace Database\Seeders;

use App\Models\RatePlan;
use App\Models\User;
use App\Models\WalkIn;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WalkInSeeder extends Seeder
{
    public function run(): void
    {
        $rates = RatePlan::query()->where('is_active', true)->get();
        $servedByUsers = User::role(['super admin', 'admin', 'manager', 'staff'])->pluck('id')->all();

        if ($rates->isEmpty()) {
            return;
        }

        WalkIn::query()->delete();

        for ($index = 0; $index < 24; $index++) {
            $ratePlan = $rates[$index % $rates->count()];

            WalkIn::query()->create([
                'rate_plan_id' => $ratePlan->id,
                'served_by' => $servedByUsers !== [] ? $servedByUsers[$index % count($servedByUsers)] : null,
                'name' => fake()->name(),
                'phone' => fake()->phoneNumber(),
                'amount_paid' => (float) ($ratePlan->price ?? fake()->randomFloat(2, 100, 500)),
                'payment_method' => $index % 3 === 0 ? 'gcash' : 'cash',
                'visited_at' => Carbon::now()
                    ->subDays($index)
                    ->setTime(6 + ($index % 8), ($index * 7) % 60),
                'notes' => $index % 5 === 0 ? 'Seeded walk-in record' : null,
            ]);
        }
    }
}
