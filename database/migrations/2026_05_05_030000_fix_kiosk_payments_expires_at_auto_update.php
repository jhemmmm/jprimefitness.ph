<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original migration declared `expires_at` as a non-null TIMESTAMP
        // with no default. Under MySQL's default
        // `explicit_defaults_for_timestamp = OFF` server setting, the first
        // such column in a table silently picks up
        // `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` - which
        // resets `expires_at` to NOW() on every row update. The PayMongo
        // QRPh flow does a follow-up `forceFill+save` to write the QR data
        // a few seconds after row creation, which silently truncated the
        // payment window to ~5s and made every kiosk session time out.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `kiosk_payments` MODIFY COLUMN `expires_at` DATETIME NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `kiosk_payments` MODIFY COLUMN `expires_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        }
    }
};
