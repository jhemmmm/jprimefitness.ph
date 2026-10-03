<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('user_id');
            $table->string('emergency_contact_name')->nullable()->after('date_of_birth');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_name');
            $table->date('hired_at')->nullable()->after('emergency_contact_phone');
            $table->string('tin', 20)->nullable()->after('hired_at');
            $table->string('sss_number', 20)->nullable()->after('tin');
            $table->string('philhealth_number', 20)->nullable()->after('sss_number');
            $table->string('pagibig_number', 20)->nullable()->after('philhealth_number');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'emergency_contact_name', 'emergency_contact_phone', 'hired_at', 'tin', 'sss_number', 'philhealth_number', 'pagibig_number']);
        });
    }
};
