<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PTProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $products = [
            ['name' => 'Per Session', 'session_count' => 1, 'category' => \App\Models\PTProduct::CATEGORY_SINGLE, 'is_active' => true],
            ['name' => '12 Sessions', 'session_count' => 12, 'category' => \App\Models\PTProduct::CATEGORY_PACKAGE, 'is_active' => true],
            ['name' => '24 Sessions', 'session_count' => 24, 'category' => \App\Models\PTProduct::CATEGORY_PACKAGE, 'is_active' => true],
            ['name' => '32 Sessions', 'session_count' => 32, 'category' => \App\Models\PTProduct::CATEGORY_PACKAGE, 'is_active' => true],
        ];
        foreach ($products as $product) {
            \App\Models\PTProduct::firstOrCreate($product);
        }
    }
}
