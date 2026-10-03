<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('origin_node', 64);
            $table->uuid('event_id');
            $table->string('entity_type', 64);
            $table->string('entity_id', 64);
            $table->string('op', 16);
            $table->string('status', 16);
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique(['origin_node', 'event_id'], 'sync_inbox_dedup_uq');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_inbox');
    }
};
