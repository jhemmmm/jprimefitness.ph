<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\CashDrawerSession;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ErrorRetryAndPruneTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pull_holds_the_cursor_at_an_errored_event_and_retries_it_next_run(): void
    {
        config(['sync.role' => 'local', 'sync.live_api_url' => 'https://live.test', 'sync.live_token' => 't']);
        $good = $this->event(1, ['uuid' => (string) Str::uuid(), 'name' => 'Good', 'duration_days' => 30, 'price' => 1, 'is_active' => true]);
        $bad = $this->event(2, ['uuid' => (string) Str::uuid(), 'name' => null, 'duration_days' => 30, 'price' => 1, 'is_active' => true]); // NOT NULL violation
        $after = $this->event(3, ['uuid' => (string) Str::uuid(), 'name' => 'After', 'duration_days' => 30, 'price' => 1, 'is_active' => true]);
        $events = [$good, $bad, $after];
        Http::fake(function () use (&$events) {
            return Http::response(['events' => $events, 'next_cursor' => 3, 'has_more' => false]);
        });

        $this->artisan('sync:pull')->expectsOutputToContain('cursor held at 1')->assertFailed();

        $this->assertSame(1, app(SyncStateRepository::class)->getInt('live_pull_cursor'));
        $this->assertSame(2, RatePlan::whereIn('name', ['Good', 'After'])->count());
        $this->assertDatabaseMissing('sync_inbox', ['event_id' => $bad['event_id']]);

        // once the cause is fixed on live the retry lands, and the rows applied past it are replayed cheaply
        $bad['payload']['name'] = 'Fixed';
        $events = [$bad, $after];
        $this->artisan('sync:pull')->expectsOutputToContain('Cursor: 3')->assertSuccessful();
        $this->assertDatabaseHas('rate_plans', ['name' => 'Fixed']);
        $this->assertSame(1, RatePlan::where('name', 'After')->count());
    }

    public function test_live_prunes_outbox_rows_the_local_node_has_pulled(): void
    {
        config(['sync.role' => 'live', 'sync.node_id' => 'live']);
        DB::table('sync_outbox')->delete();
        RatePlan::create(['name' => 'Old', 'duration_days' => 30, 'price' => 1, 'is_active' => true]);
        RatePlan::create(['name' => 'Unpulled', 'duration_days' => 30, 'price' => 1, 'is_active' => true]);
        DB::table('sync_outbox')->update(['created_at' => now()->subDays(40)]);
        $pulledUpTo = (int) DB::table('sync_outbox')->min('id');
        app(SyncStateRepository::class)->set('last_local_pull_cursor', (string) $pulledUpTo);

        $this->artisan('sync:prune')->expectsOutputToContain('Pruned 1 outbox')->assertSuccessful();

        $this->assertSame(1, DB::table('sync_outbox')->count());
        $this->assertSame($pulledUpTo + 1, (int) DB::table('sync_outbox')->value('id'));
    }

    public function test_heartbeat_reports_the_pull_cursor_so_live_knows_what_it_can_prune(): void
    {
        config(['sync.role' => 'live', 'sync.live_token' => 't']);

        $this->withHeader('Authorization', 'Bearer t')->postJson('/api/sync/heartbeat', ['node_id' => 'local-1', 'pull_cursor' => 42])->assertOk();

        $this->assertSame(42, app(SyncStateRepository::class)->getInt('last_local_pull_cursor'));
    }

    public function test_pos_stock_deduction_and_void_restock_reach_the_outbox(): void
    {
        config(['sync.role' => 'local', 'sync.node_id' => 'local-test', 'sync.instant_push' => false]);
        $staff = User::factory()->withEmployeeProfile(['daily_rate' => 500, 'pay_frequency' => 'semi_monthly'])->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('manager');
        CashDrawerSession::factory()->create(['opened_at' => '2026-05-07 08:00:00']);
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => InventoryCategory::factory()->create()->id,
            'quantity' => 10, 'tracks_stock' => true, 'selling_price' => 35, 'status' => InventoryItem::STATUS_ACTIVE,
        ]);
        DB::table('sync_outbox')->delete();
        $quantities = fn () => DB::table('sync_outbox')->where('entity_type', 'inventory_item')->where('entity_id', $item->uuid)->orderBy('id')->pluck('payload')->map(fn ($p) => (float) json_decode($p)->quantity)->all();

        $saleId = $this->actingAs($staff)->postJson('/panel/sales', [
            'type' => SaleTransaction::TYPE_INVENTORY,
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 2]],
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'amount_received' => 200,
            'sold_at' => '2026-05-07 10:00:00',
        ])->assertCreated()->json('id');
        $this->assertSame([8.0], $quantities());

        $this->actingAs($staff)->postJson("/panel/sales/{$saleId}/void", ['reason' => 'test'])->assertOk();
        $this->assertSame([8.0, 10.0], $quantities());
    }

    private function event(int $sequence, array $payload): array
    {
        return [
            'sequence' => $sequence,
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'rate_plan',
            'entity_id' => $payload['uuid'],
            'op' => 'create',
            'payload' => $payload + ['updated_at' => now()->toIso8601String()],
            'origin_node' => 'live',
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
