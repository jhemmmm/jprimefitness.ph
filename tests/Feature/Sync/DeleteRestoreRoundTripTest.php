<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteRestoreRoundTripTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOKEN = 'sync-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.role' => 'live',
            'sync.live_token' => self::TOKEN,
        ]);
    }

    private function pushEvents(array $events): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.self::TOKEN,
        ])->postJson('/api/sync/push', ['events' => $events]);
    }

    private function event(string $entityType, string $op, array $payload): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $payload['uuid'] ?? '',
            'op' => $op,
            'payload' => $payload,
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_delete_then_restore_round_trips_through_push_pipeline(): void
    {
        $category = InventoryCategory::create([
            'name' => 'Drinks',
            'slug' => 'drinks',
            'sort_order' => 1,
        ]);
        $item = InventoryItem::create([
            'inventory_category_id' => $category->id,
            'name' => 'Sparkling Water',
            'sku' => 'SW',
            'unit' => 'pcs',
            'quantity' => 5,
            'cost_price' => 8,
            'selling_price' => 25,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $deletePayload = ['uuid' => $item->uuid];
        $this->pushEvents([$this->event('inventory_item', 'delete', $deletePayload)])
            ->assertOk()
            ->assertJsonPath('acks.0.status', 'ok');

        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);

        $restorePayload = ['uuid' => $item->uuid];
        $this->pushEvents([$this->event('inventory_item', 'restore', $restorePayload)])
            ->assertOk()
            ->assertJsonPath('acks.0.status', 'ok');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_for_unknown_uuid_acks_skipped(): void
    {
        $this->pushEvents([$this->event('inventory_item', 'delete', ['uuid' => (string) Str::uuid()])])
            ->assertOk()
            ->assertJsonPath('acks.0.status', 'skipped');
    }
}
