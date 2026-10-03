<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'Equipment',
            'Supplements',
            'Drinks',
            'Merchandise',
            'Supplies',
            'Cleaning Supplies',
            'Services',
        ])->values()->each(function (string $name, int $index): void {
            InventoryCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'sort_order' => $index + 1,
                ],
            );
        });
    }
}
