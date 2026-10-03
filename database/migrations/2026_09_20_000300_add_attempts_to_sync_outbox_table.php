<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rows live keeps rejecting go to the back of the push queue instead of blocking everything behind them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_outbox', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('pushed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sync_outbox', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
