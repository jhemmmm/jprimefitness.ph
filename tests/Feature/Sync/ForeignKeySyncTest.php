<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\CashDrawerSession;
use App\Models\CashLedgerEntry;
use App\Models\MemberPtPackage;
use App\Models\PTProduct;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\Sync\AckStatus;
use App\Services\Sync\SyncEventApplier;
use App\Services\Sync\SyncOp;
use App\Services\Sync\SyncReceiverRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Foreign keys travel as the parent's uuid (`_refs`) because auto-increment
 * ids differ per node; this pins both directions plus the retry path.
 */
class ForeignKeySyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outbox_carries_parent_uuids_for_every_foreign_key(): void
    {
        config(['sync.role' => 'local', 'sync.node_id' => 'local-test']);
        DB::table('sync_outbox')->delete();

        [$member, $package, $sale] = $this->createLinkedPackage();

        $payload = json_decode(DB::table('sync_outbox')->where('entity_type', 'member_pt_package')->latest('id')->value('payload'), true);

        $this->assertSame($sale->uuid, $payload['_refs']['sale_transaction_id']);
        $this->assertSame($member->uuid, $payload['_refs']['user_id']);
        $this->assertSame($package->ptProduct->uuid, $payload['_refs']['pt_product_id']);
        $this->assertArrayNotHasKey('cancelled_by', $payload['_refs'], 'null foreign keys carry no ref');
    }

    public function test_receiver_resolves_refs_and_ignores_sender_integer_ids(): void
    {
        config(['sync.role' => 'live']);
        $member = User::factory()->create();
        $product = $this->createPtProduct();
        $wrongSale = $this->createSaleTransaction($member, $product);
        $rightSale = $this->createSaleTransaction($member, $product);
        $uuid = (string) Str::uuid();
        $receiver = app(SyncReceiverRegistry::class)->receiverFor('member_pt_package');

        $status = $receiver->apply($this->event('member_pt_package', SyncOp::CREATE, [
            'uuid' => $uuid,
            'user_id' => 999,
            'sale_transaction_id' => $wrongSale->id,
            'pt_product_id' => 999,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'status' => MemberPtPackage::STATUS_ACTIVE,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            '_refs' => ['user_id' => $member->uuid, 'sale_transaction_id' => $rightSale->uuid, 'pt_product_id' => $product->uuid],
        ]));

        $this->assertSame(AckStatus::OK, $status);
        $package = MemberPtPackage::where('uuid', $uuid)->firstOrFail();
        $this->assertSame($rightSale->id, $package->sale_transaction_id);
        $this->assertSame($member->id, $package->user_id);
        $this->assertSame($product->id, $package->pt_product_id);
    }

    public function test_child_arriving_before_its_parent_errors_and_is_retried_not_replayed(): void
    {
        config(['sync.role' => 'live']);
        $member = User::factory()->create();
        $product = $this->createPtProduct();
        $saleUuid = (string) Str::uuid();
        $applier = app(SyncEventApplier::class);
        $event = $this->event('member_pt_package', SyncOp::CREATE, [
            'uuid' => (string) Str::uuid(),
            'user_id' => 1,
            'sale_transaction_id' => 1,
            'pt_product_id' => 1,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'status' => MemberPtPackage::STATUS_ACTIVE,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            '_refs' => ['user_id' => $member->uuid, 'sale_transaction_id' => $saleUuid, 'pt_product_id' => $product->uuid],
        ]);

        $this->assertSame(AckStatus::ERROR, $applier->applyBatch([$event])[0]['status']);
        $this->assertSame(0, MemberPtPackage::count());
        $this->assertDatabaseMissing('sync_inbox', ['event_id' => $event['event_id']]);

        $this->createSaleTransaction($member, $product, $saleUuid);

        $this->assertSame(AckStatus::OK, $applier->applyBatch([$event])[0]['status']);
        $this->assertSame(1, MemberPtPackage::count());
    }

    public function test_snapshot_rows_carry_refs_including_polymorphic_ledger_source(): void
    {
        $member = User::factory()->create();
        $session = CashDrawerSession::factory()->create(['opened_by' => $member->id]);
        $sale = $this->createSaleTransaction($member, $this->createPtProduct()); // the cash-sale observer writes the ledger entry
        $entry = CashLedgerEntry::where('source_type', 'sale_transaction')->where('source_id', $sale->id)->firstOrFail();

        $row = collect(app(SyncReceiverRegistry::class)->receiverFor('cash_ledger_entry')->snapshot('cash_ledger_entry', 0, 100)['rows'])->firstWhere('uuid', $entry->uuid);

        $this->assertSame($sale->uuid, $row['_refs']['source_id']);
        $this->assertSame($session->uuid, $row['_refs']['session_id']);
        $this->assertSame($member->uuid, $row['_refs']['recorded_by']);
    }

    public function test_sync_bootstrap_orders_parents_before_children(): void
    {
        $order = array_flip(array_keys((array) config('sync.entities')));

        $this->assertLessThan($order['member_pt_package'], $order['sale_transaction']);
        $this->assertLessThan($order['member_pt_package'], $order['user']);
        $this->assertLessThan($order['cash_ledger_entry'], $order['cash_drawer_session']);
    }

    /** @return array{0: User, 1: MemberPtPackage, 2: SaleTransaction} */
    private function createLinkedPackage(): array
    {
        $member = User::factory()->create();
        $product = $this->createPtProduct();
        $sale = $this->createSaleTransaction($member, $product);
        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'sale_transaction_id' => $sale->id,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);

        return [$member, $package, $sale];
    }

    private function createPtProduct(): PTProduct
    {
        return PTProduct::create(['name' => '8 Sessions', 'session_count' => 8, 'category' => PTProduct::CATEGORY_PACKAGE, 'price' => 500, 'is_active' => true]);
    }

    private function createSaleTransaction(User $member, PTProduct $product, ?string $uuid = null): SaleTransaction
    {
        $sale = new SaleTransaction([
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'status' => SaleTransaction::STATUS_COMPLETED,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $member->id,
            'sold_at' => '2026-04-01 09:00:00',
            'customer_name' => $member->name,
            'item_name' => $product->name,
            'details' => [],
        ]);
        $sale->uuid = $uuid; // null → the trait generates one
        $sale->save();

        return $sale;
    }

    private function event(string $entityType, string $op, array $payload): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $payload['uuid'],
            'op' => $op,
            'payload' => $payload,
            'origin_node' => 'remote-test',
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
