<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hikvision_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_serial');
            $table->string('event_serial_no');
            $table->string('event_type')->nullable();
            $table->string('employee_no')->nullable();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['device_serial', 'event_serial_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hikvision_event_logs');
    }
};
