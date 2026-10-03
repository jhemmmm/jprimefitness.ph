<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('event_id')->unique();
            $table->string('entity_type', 64);
            $table->string('entity_id', 64);
            $table->string('op', 16);
            $table->json('payload');
            $table->string('origin_node', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->index(['pushed_at', 'id'], 'sync_outbox_unpushed_idx');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_outbox');
    }
};
