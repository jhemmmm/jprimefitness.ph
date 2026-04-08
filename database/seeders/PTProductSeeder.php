<?php

namespace Database\Seeders;

use App\Models\PTProduct;
use Illuminate\Database\Seeder;

class PTProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Per Session',
                'session_count' => 1,
                'category' => PTProduct::CATEGORY_SINGLE,
                'price' => 500.00,
                'coach_commission_rate' => 40.00,
                'is_active' => true,
                'description' => 'One-on-one coaching for a single session.',
            ],
            [
                'name' => '12 Sessions',
                'session_count' => 12,
                'category' => PTProduct::CATEGORY_PACKAGE,
                'price' => 4800.00,
                'coach_commission_rate' => 40.00,
                'is_active' => true,
                'description' => 'Starter PT package for members building consistency.',
            ],
            [
                'name' => '24 Sessions',
                'session_count' => 24,
                'category' => PTProduct::CATEGORY_PACKAGE,
                'price' => 9000.00,
                'coach_commission_rate' => 42.50,
                'is_active' => true,
                'description' => 'Mid-sized PT package for sustained progress.',
            ],
            [
                'name' => '32 Sessions',
                'session_count' => 32,
                'category' => PTProduct::CATEGORY_PACKAGE,
                'price' => 11600.00,
                'coach_commission_rate' => 45.00,
                'is_active' => true,
                'description' => 'High-commitment PT package with the best session value.',
            ],
        ];

        foreach ($products as $product) {
            PTProduct::query()->updateOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}
