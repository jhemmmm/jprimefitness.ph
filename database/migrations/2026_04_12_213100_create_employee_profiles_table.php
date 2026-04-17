<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('hikvision_employee_no')->unique();
            $table->string('biometric_status', 50)->default('not_enrolled');
            $table->unsignedTinyInteger('biometric_fingerprint_id')->nullable();
            $table->timestamp('biometric_enrolled_at')->nullable();
            $table->text('biometric_last_error')->nullable();
            $table->timestamps();
        });

        $now = CarbonImmutable::now();

        $employeeRoleIds = DB::table('roles')
            ->whereIn('name', ['employee', 'coach', 'manager', 'admin', 'staff'])
            ->pluck('id');

        if ($employeeRoleIds->isEmpty()) {
            return;
        }

        $employeeUserIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('role_id', $employeeRoleIds)
            ->distinct()
            ->pluck('model_id');

        if ($employeeUserIds->isEmpty()) {
            return;
        }

        $rows = $employeeUserIds
            ->map(fn (int $userId): array => [
                'user_id' => $userId,
                'hikvision_employee_no' => str_pad((string) $userId, 8, '0', STR_PAD_LEFT),
                'biometric_status' => 'not_enrolled',
                'biometric_fingerprint_id' => null,
                'biometric_enrolled_at' => null,
                'biometric_last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('employee_profiles')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
