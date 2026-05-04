<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\Attendance;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\KioskPayment;
use App\Models\MemberPtPackage;
use App\Models\MemberPtSessionUsage;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\User;
use App\Services\Sync\Receivers\DefaultReceiver;
use App\Services\Sync\Receivers\KioskPaymentReceiver;
use App\Services\Sync\Receivers\MemberPtSessionUsageReceiver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiverConflictTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sync.role' => 'live']);
    }

    private function event(string $entityType, string $op, array $payload): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $payload['uuid'] ?? ($payload['reference'] ?? 'k'),
            'op' => $op,
            'payload' => $payload,
            'origin_node' => 'remote-test',
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_newer_local_edit_wins_against_older_remote_update(): void
    {
        $plan = RatePlan::create([
            'name' => 'Local',
            'duration_days' => 30,
            'price' => 100,
            'is_active' => true,
        ]);

        // Touch so updated_at is now.
        $plan->update(['price' => 150]);

        $receiver = app(DefaultReceiver::class);
        $status = $receiver->apply($this->event('rate_plan', 'update', [
            'uuid' => $plan->uuid,
            'name' => 'Stale',
            'duration_days' => 30,
            'price' => 50,
            'is_active' => true,
            'created_at' => $plan->created_at->toIso8601String(),
            'updated_at' => now()->subHour()->toIso8601String(),
        ]));

        $this->assertSame('conflict', $status);
        $this->assertSame(150.00, (float) $plan->fresh()->price);
    }

    public function test_newer_remote_update_wins_against_older_local(): void
    {
        $plan = RatePlan::create([
            'name' => 'Local',
            'duration_days' => 30,
            'price' => 100,
            'is_active' => true,
        ]);

        // Force the local row to look old.
        DB::table('rate_plans')->where('id', $plan->id)
            ->update(['updated_at' => now()->subDays(2)]);

        $receiver = app(DefaultReceiver::class);
        $status = $receiver->apply($this->event('rate_plan', 'update', [
            'uuid' => $plan->uuid,
            'name' => 'Fresh Remote',
            'duration_days' => 60,
            'price' => 999,
            'is_active' => true,
            'created_at' => $plan->created_at->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));

        $this->assertSame('ok', $status);
        $this->assertSame('Fresh Remote', $plan->fresh()->name);
    }

    public function test_soft_delete_event_marks_local_row_deleted(): void
    {
        $category = InventoryCategory::create([
            'name' => 'Cat',
            'slug' => 'cat',
            'sort_order' => 1,
        ]);

        $item = InventoryItem::create([
            'inventory_category_id' => $category->id,
            'name' => 'Towel',
            'sku' => 'TWL',
            'unit' => 'pcs',
            'quantity' => 5,
            'cost_price' => 10,
            'selling_price' => 20,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $receiver = app(DefaultReceiver::class);
        $status = $receiver->apply($this->event('inventory_item', 'delete', [
            'uuid' => $item->uuid,
        ]));

        $this->assertSame('ok', $status);
        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);
    }

    public function test_kiosk_payment_locates_by_reference(): void
    {
        $reference = 'KP-'.Str::random(10);

        $receiver = app(KioskPaymentReceiver::class);
        $status = $receiver->apply($this->event('kiosk_payment', 'create', [
            'reference' => $reference,
            'name' => 'Walter',
            'phone' => '09171234567',
            'amount' => 150,
            'status' => KioskPayment::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));

        $this->assertSame('ok', $status);
        $this->assertDatabaseHas('kiosk_payments', ['reference' => $reference]);
    }

    public function test_member_pt_session_usage_replays_are_skipped(): void
    {
        $coach = User::create([
            'name' => 'Coach',
            'email' => 'coach@test.com',
            'password' => bcrypt('x'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $member = User::create([
            'name' => 'Member',
            'email' => 'member@test.com',
            'password' => bcrypt('x'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $product = PTProduct::create([
            'name' => '5 Sessions',
            'session_count' => 5,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'price' => 1500,
            'is_active' => true,
        ]);
        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'sold_price' => 1500,
            'coach_id' => $coach->id,
            'total_sessions' => 5,
            'remaining_sessions' => 5,
            'status' => MemberPtPackage::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);

        $usageUuid = (string) Str::uuid();
        $receiver = app(MemberPtSessionUsageReceiver::class);

        $first = $receiver->apply($this->event('member_pt_session_usage', 'create', [
            'uuid' => $usageUuid,
            'member_pt_package_id' => $package->id,
            'recorded_by' => $coach->id,
            'coach_id' => $coach->id,
            'sessions_used' => 1,
            'used_at' => now()->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));

        $second = $receiver->apply($this->event('member_pt_session_usage', 'create', [
            'uuid' => $usageUuid,
            'member_pt_package_id' => $package->id,
            'recorded_by' => $coach->id,
            'coach_id' => $coach->id,
            'sessions_used' => 1,
            'used_at' => now()->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));

        $this->assertSame('ok', $first);
        $this->assertSame('skipped', $second);
        $this->assertSame(1, MemberPtSessionUsage::where('uuid', $usageUuid)->count());
    }

    public function test_attendance_create_does_not_re_emit_outbox_event(): void
    {
        $member = User::create([
            'name' => 'Att Member',
            'email' => 'att@test.com',
            'password' => bcrypt('x'),
            'status' => User::STATUS_ACTIVE,
        ]);

        // Drop any outbox writes that happened during user/category setup.
        DB::table('sync_outbox')->truncate();

        $receiver = app(DefaultReceiver::class);
        $status = $receiver->apply($this->event('attendance', 'create', [
            'uuid' => (string) Str::uuid(),
            'user_id' => $member->id,
            'attendee_type' => Attendance::TYPE_MEMBER,
            'source' => Attendance::SOURCE_KIOSK,
            'checked_in_at' => now()->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]));

        $this->assertSame('ok', $status);
        $this->assertSame(0, DB::table('sync_outbox')->where('entity_type', 'attendance')->count());
    }
}
