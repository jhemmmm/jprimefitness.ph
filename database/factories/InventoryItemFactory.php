<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::query()->value('id') ?? Branch::create([
                'name' => fake()->company().' Branch',
                'status' => Branch::STATUS_OPEN,
                'country_code' => Branch::COUNTRY_PHILIPPINES,
                'city' => fake()->city(),
            ])->id,
            'inventory_category_id' => InventoryCategory::query()->value('id') ?? InventoryCategory::factory()->create()->id,
            'name' => ucfirst(fake()->words(2, true)),
            'sku' => fake()->boolean(70) ? strtoupper(fake()->bothify('INV-###??')) : null,
            'unit' => fake()->randomElement(['pcs', 'box', 'pack', 'bottle']),
            'quantity' => fake()->randomFloat(2, 0, 120),
            'low_stock_threshold' => fake()->randomFloat(2, 1, 20),
            'cost_price' => fake()->randomFloat(2, 20, 500),
            'selling_price' => fake()->randomFloat(2, 30, 750),
            'status' => InventoryItem::STATUS_ACTIVE,
            'notes' => fake()->optional()->sentence(),
            'last_restocked_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
