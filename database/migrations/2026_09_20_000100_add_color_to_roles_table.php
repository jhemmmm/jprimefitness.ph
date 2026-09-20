<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The colours the seeded roles had as fixed CSS classes before badges became configurable. */
    private const DEFAULTS = [
        'super admin' => 'purple',
        'admin' => 'pink',
        'manager' => 'indigo',
        'staff' => 'sky',
        'member' => 'green',
        'coach' => 'orange',
        'cashier' => 'amber',
    ];

    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->string('color', 20)->default('slate')->after('guard_name'); // Role::COLORS[0]
        });

        // raw update: this is presentation only and must not emit sync events on deploy
        foreach (self::DEFAULTS as $name => $color) {
            DB::table(config('permission.table_names.roles'))->where('name', $name)->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
