<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            // 1 = open, NULL = closed. Unique index allows many NULLs on both
            // MySQL and SQLite, so the DB itself enforces one open session.
            $table->boolean('is_open')->nullable()->unique();
            $table->decimal('opening_float', 10, 2);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->decimal('over_short', 10, 2)->nullable();
            $table->decimal('deposited_amount', 10, 2)->nullable();
            $table->string('deposit_reference')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index('opened_at');
        });

        Schema::create('cash_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('session_id')->nullable()->constrained('cash_drawer_sessions')->nullOnDelete();
            $table->string('type', 30);
            $table->string('category', 30)->nullable();
            // Signed: positive = cash into the drawer, negative = cash out.
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'type']);
            $table->index(['session_id', 'type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_ledger_entries');
        Schema::dropIfExists('cash_drawer_sessions');
    }
};
