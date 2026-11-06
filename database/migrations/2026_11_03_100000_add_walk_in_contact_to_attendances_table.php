<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Walk-ins have no user profile, so their contact and emergency contact live on the visit itself.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('name');
            $table->string('emergency_contact_name', 150)->nullable()->after('phone');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_name');
        });

        // Kiosk walk-ins so far kept the phone only inside their JSON notes.
        DB::table('attendances')
            ->where('attendee_type', 'walk_in')
            ->where('notes', 'like', '{%')
            ->orderBy('id')
            ->each(function (object $row): void {
                $phone = json_decode((string) $row->notes, true)['phone'] ?? null;

                if (is_string($phone) && $phone !== '') {
                    DB::table('attendances')->where('id', $row->id)->update(['phone' => $phone]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['phone', 'emergency_contact_name', 'emergency_contact_phone']);
        });
    }
};
