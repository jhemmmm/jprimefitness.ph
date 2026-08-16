<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\MemberPtPackage;
use App\Models\PTProduct;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\Sync\AckStatus;
use App\Services\Sync\Receivers\MemberPtPackageReceiver;
use App\Services\Sync\SyncOp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberPtPackageSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outbox_uses_sale_uuid_instead_of_node_local_sale_id(): void
    {
        config(['sync.role' => 'local', 'sync.node_id' => 'local-test']);
        DB::table('sync_outbox')->truncate();

        [, $package, $saleTransaction] = $this->createLinkedPackage();

        $outbox = DB::table('sync_outbox')
            ->where('entity_type', 'member_pt_package')
            ->where('entity_id', $package->uuid)
            ->latest('id')
            ->first();

        $this->assertNotNull($outbox);
        $payload = json_decode($outbox->payload, true);

        $this->assertSame($saleTransaction->uuid, $payload['sale_transaction_uuid']);
        $this->assertArrayNotHasKey('sale_transaction_id', $payload);
    }

    public function test_receiver_resolves_sale_uuid_and_ignores_sender_integer_id(): void
    {
        config(['sync.role' => 'live']);

        $member = User::factory()->create();
        $ptProduct = $this->createPtProduct();
        $wrongSaleTransaction = $this->createSaleTransaction($member, $ptProduct);
        $localSaleTransaction = $this->createSaleTransaction($member, $ptProduct);
        $packageUuid = (string) Str::uuid();
        $receiver = app(MemberPtPackageReceiver::class);

        $status = $receiver->apply($this->event(SyncOp::CREATE, $packageUuid, [
            'uuid' => $packageUuid,
            'user_id' => $member->id,
            'sale_transaction_id' => $wrongSaleTransaction->id,
            'sale_transaction_uuid' => $localSaleTransaction->uuid,
            'pt_product_id' => $ptProduct->id,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'status' => MemberPtPackage::STATUS_ACTIVE,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));

        $this->assertSame(AckStatus::OK, $status);
        $package = MemberPtPackage::query()->where('uuid', $packageUuid)->firstOrFail();
        $this->assertSame($localSaleTransaction->id, $package->sale_transaction_id);

        $clearStatus = $receiver->apply($this->event(SyncOp::UPDATE, $packageUuid, [
            'uuid' => $packageUuid,
            'sale_transaction_uuid' => null,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]));

        $this->assertSame(AckStatus::OK, $clearStatus);
        $this->assertNull($package->fresh()->sale_transaction_id);
    }

    public function test_snapshot_replaces_sale_id_with_stable_uuid(): void
    {
        [, $package, $saleTransaction] = $this->createLinkedPackage();

        $snapshot = app(MemberPtPackageReceiver::class)
            ->snapshot('member_pt_package', 0, 100);
        $row = collect($snapshot['rows'])->firstWhere('uuid', $package->uuid);

        $this->assertNotNull($row);
        $this->assertSame($saleTransaction->uuid, $row['sale_transaction_uuid']);
        $this->assertArrayNotHasKey('sale_transaction_id', $row);
        $this->assertFalse($snapshot['has_more']);
    }

    public function test_sync_bootstrap_orders_sales_before_pt_packages(): void
    {
        $entityTypes = array_keys((array) config('sync.entities'));
        $saleTransactionPosition = array_search('sale_transaction', $entityTypes, true);
        $memberPtPackagePosition = array_search('member_pt_package', $entityTypes, true);

        $this->assertIsInt($saleTransactionPosition);
        $this->assertIsInt($memberPtPackagePosition);
        $this->assertLessThan($memberPtPackagePosition, $saleTransactionPosition);
        $this->assertSame(
            MemberPtPackageReceiver::class,
            config('sync.entities.member_pt_package.receiver'),
        );
    }

    /**
     * @return array{0: User, 1: MemberPtPackage, 2: SaleTransaction}
     */
    private function createLinkedPackage(): array
    {
        $member = User::factory()->create();
        $ptProduct = $this->createPtProduct();
        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $ptProduct->id,
            'sold_price' => 500,
            'total_sessions' => 8,
            'remaining_sessions' => 8,
            'assigned_at' => '2026-04-01',
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);
        $saleTransaction = $this->createSaleTransaction($member, $ptProduct);

        $package->update(['sale_transaction_id' => $saleTransaction->id]);

        return [$member, $package, $saleTransaction];
    }

    private function createPtProduct(): PTProduct
    {
        return PTProduct::create([
            'name' => '8 Sessions',
            'session_count' => 8,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => 500,
            'is_active' => true,
        ]);
    }

    private function createSaleTransaction(User $member, PTProduct $ptProduct): SaleTransaction
    {
        return SaleTransaction::create([
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'status' => SaleTransaction::STATUS_COMPLETED,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $member->id,
            'sold_at' => '2026-04-01 09:00:00',
            'customer_name' => $member->name,
            'item_name' => $ptProduct->name,
            'details' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function event(string $operation, string $entityId, array $payload): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'member_pt_package',
            'entity_id' => $entityId,
            'op' => $operation,
            'payload' => $payload,
            'origin_node' => 'remote-test',
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
