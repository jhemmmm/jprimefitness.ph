<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 120)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sort_order', 'name']);
        });

        DB::table('inventory_categories')->insert([
            [
                'name' => 'Equipment',
                'slug' => 'equipment',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplements',
                'slug' => 'supplements',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Drinks',
                'slug' => 'drinks',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Merchandise',
                'slug' => 'merchandise',
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Supplies',
                'slug' => 'supplies',
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cleaning Supplies',
                'slug' => 'cleaning-supplies',
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Office Supplies',
                'slug' => 'office-supplies',
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_categories');
    }
};
