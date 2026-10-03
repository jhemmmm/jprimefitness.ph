<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            // Random 40-char payload + 'JPRIME:' prefix = 47 chars; 64 leaves headroom.
            $table->string('qr_payload', 255)->nullable()->change();
            $table->unique('qr_payload');
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['qr_payload']);
            $table->text('qr_payload')->nullable()->change();
        });
    }
};
