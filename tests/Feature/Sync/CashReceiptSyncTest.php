<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\CashLedgerEntry;
use App\Services\Sync\AckStatus;
use App\Services\Sync\SyncEventApplier;
use App\Services\Sync\SyncOp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashReceiptSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expense_receipt_image_travels_with_the_synced_ledger_entry(): void
    {
        config(['sync.role' => 'local', 'sync.node_id' => 'local-test']);
        Storage::fake('local');
        Storage::put('cash-receipts/bill.jpg', 'receipt-bytes');

        $entry = CashLedgerEntry::factory()->create(['receipt_path' => 'cash-receipts/bill.jpg']);
        $payload = json_decode(DB::table('sync_outbox')->where('entity_type', 'cash_ledger_entry')->value('payload'), true);

        // the other node: it has the row but not the file
        Storage::fake('local');

        $ack = app(SyncEventApplier::class)->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'cash_ledger_entry',
            'entity_id' => $entry->uuid,
            'op' => SyncOp::UPDATE,
            'payload' => $payload,
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->assertSame(AckStatus::OK, $ack['status']);
        $this->assertSame('receipt-bytes', Storage::get('cash-receipts/bill.jpg'));
    }
}
