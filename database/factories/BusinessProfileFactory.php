<?php

namespace Database\Factories;

use App\Models\BusinessProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfile>
 */
class BusinessProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge(BusinessProfile::defaultAttributes(), [
            'name' => fake()->company(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'address' => fake()->address(),
        ]);
    }
}
