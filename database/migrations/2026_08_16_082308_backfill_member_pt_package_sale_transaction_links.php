<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sale_transactions')
            ->select(['id', 'member_id', 'status', 'details', 'void_reason', 'voided_by', 'voided_at'])
            ->where('type', 'pt_package')
            ->orderBy('id')
            ->chunkById(100, function ($saleTransactions): void {
                foreach ($saleTransactions as $saleTransaction) {
                    $details = json_decode((string) $saleTransaction->details, true);
                    $memberPtPackageId = (int) ($details['member_pt_package_id'] ?? 0);

                    if ($memberPtPackageId < 1) {
                        continue;
                    }

                    $updates = [
                        'sale_transaction_id' => $saleTransaction->id,
                    ];

                    if ($saleTransaction->status === 'voided') {
                        $updates = [
                            ...$updates,
                            'status' => 'cancelled',
                            'remaining_sessions' => DB::raw('total_sessions'),
                            'cancellation_reason' => $saleTransaction->void_reason,
                            'cancelled_by' => $saleTransaction->voided_by,
                            'cancelled_at' => $saleTransaction->voided_at,
                        ];
                    }

                    DB::table('member_pt_packages')
                        ->where('id', $memberPtPackageId)
                        ->where('user_id', $saleTransaction->member_id)
                        ->whereNull('sale_transaction_id')
                        ->update($updates);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: clearing these links would also destroy
        // sale relationships created normally after this migration ran.
    }
};
