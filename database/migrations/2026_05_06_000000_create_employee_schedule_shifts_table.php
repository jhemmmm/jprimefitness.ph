<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedule_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('employee_profile_id')
                ->constrained('employee_profiles')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['employee_profile_id', 'day_of_week'], 'esched_emp_dow_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedule_shifts');
    }
};
