<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('walk_ins', function (Blueprint $table) {
            $table->string('payment_method', 50)
                ->default('cash')
                ->after('amount_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('walk_ins', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
