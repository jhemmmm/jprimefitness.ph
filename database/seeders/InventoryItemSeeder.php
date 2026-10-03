<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $services = InventoryCategory::query()->where('slug', Str::slug('Services'))->first();

        if (! $services) {
            return;
        }

        collect([
            ['name' => 'Shower', 'selling_price' => 20.00],
            ['name' => 'Water Refill', 'selling_price' => 10.00],
        ])->each(function (array $row) use ($services): void {
            InventoryItem::query()->updateOrCreate(
                [
                    'inventory_category_id' => $services->id,
                    'name' => $row['name'],
                ],
                [
                    'unit' => 'service',
                    'quantity' => 0,
                    'tracks_stock' => false,
                    'low_stock_threshold' => 0,
                    'selling_price' => $row['selling_price'],
                    'status' => InventoryItem::STATUS_ACTIVE,
                ],
            );
        });
    }
}
