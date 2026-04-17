<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        DB::table('employee_profiles')
            ->select(['id', 'user_id', 'hikvision_employee_no'])
            ->orderBy('id')
            ->chunkById(100, function ($profiles): void {
                foreach ($profiles as $profile) {
                    if (! is_string($profile->hikvision_employee_no) || ! str_starts_with($profile->hikvision_employee_no, 'EMP-')) {
                        continue;
                    }

                    DB::table('employee_profiles')
                        ->where('id', $profile->id)
                        ->update([
                            'hikvision_employee_no' => str_pad((string) $profile->user_id, 8, '0', STR_PAD_LEFT),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
