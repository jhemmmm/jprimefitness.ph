<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'payroll_settings')) {
            return;
        }

        DB::table('branches')
            ->select('id', 'payroll_settings')
            ->orderBy('id')
            ->get()
            ->each(function (object $branch): void {
                $settings = json_decode((string) ($branch->payroll_settings ?? ''), true);
                $payFrequency = is_array($settings) ? ($settings['pay_frequency'] ?? null) : null;

                if (! in_array($payFrequency, ['monthly', 'semi_monthly'], true)) {
                    return;
                }

                $userIds = DB::table('branch_user')
                    ->where('branch_id', $branch->id)
                    ->pluck('user_id');

                if ($userIds->isEmpty()) {
                    return;
                }

                DB::table('users')
                    ->whereNull('pay_frequency')
                    ->whereIn('id', $userIds)
                    ->update(['pay_frequency' => $payFrequency]);
            });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('payroll_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('payroll_settings')->nullable()->after('timezone');
        });
    }
};
