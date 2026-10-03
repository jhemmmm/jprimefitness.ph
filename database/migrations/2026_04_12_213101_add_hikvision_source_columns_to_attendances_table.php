<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('source', 50)->default('manual')->after('recorded_by');
            $table->string('source_device_serial')->nullable()->after('source');

            $table->index(['source', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['source', 'checked_in_at']);
            $table->dropColumn(['source', 'source_device_serial']);
        });
    }
};
