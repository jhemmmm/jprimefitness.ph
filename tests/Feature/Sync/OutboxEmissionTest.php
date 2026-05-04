<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\RatePlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutboxEmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.role' => 'local',
            'sync.node_id' => 'local-test',
        ]);
    }

    public function test_creating_a_synced_model_writes_an_outbox_row(): void
    {
        $plan = RatePlan::create([
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 999.00,
            'is_active' => true,
        ]);

        $row = DB::table('sync_outbox')->where('entity_type', 'rate_plan')->first();

        $this->assertNotNull($row);
        $this->assertSame('create', $row->op);
        $this->assertSame('local-test', $row->origin_node);
        $this->assertSame($plan->uuid, $row->entity_id);
        $this->assertNull($row->pushed_at);

        $payload = json_decode($row->payload, true);
        $this->assertSame('Monthly', $payload['name']);
    }

    public function test_uuid_is_auto_generated_on_create(): void
    {
        $plan = RatePlan::create([
            'name' => 'Annual',
            'duration_days' => 365,
            'price' => 9900.00,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($plan->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $plan->uuid
        );
    }

    public function test_updating_a_synced_model_emits_an_update_event(): void
    {
        $plan = RatePlan::create([
            'name' => 'Trial',
            'duration_days' => 7,
            'price' => 199.00,
            'is_active' => true,
        ]);

        DB::table('sync_outbox')->truncate();

        $plan->update(['price' => 249.00]);

        $row = DB::table('sync_outbox')->where('entity_type', 'rate_plan')->first();
        $this->assertNotNull($row);
        $this->assertSame('update', $row->op);
    }

    public function test_deleting_a_softdelete_model_emits_a_delete_event(): void
    {
        $category = InventoryCategory::create([
            'name' => 'Drinks',
            'slug' => 'drinks',
            'sort_order' => 1,
        ]);

        $item = InventoryItem::create([
            'inventory_category_id' => $category->id,
            'name' => 'Water',
            'sku' => 'WTR',
            'unit' => 'pcs',
            'quantity' => 10,
            'cost_price' => 5,
            'selling_price' => 25,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        DB::table('sync_outbox')->truncate();

        $item->delete();

        $row = DB::table('sync_outbox')->where('entity_type', 'inventory_item')->first();
        $this->assertNotNull($row);
        $this->assertSame('delete', $row->op);
    }

    public function test_standalone_role_skips_outbox_emission(): void
    {
        config(['sync.role' => 'standalone']);

        RatePlan::create([
            'name' => 'Standalone Plan',
            'duration_days' => 30,
            'price' => 999.00,
            'is_active' => true,
        ]);

        $this->assertSame(0, DB::table('sync_outbox')->count());
    }

    public function test_redacted_attributes_are_stripped_from_payload(): void
    {
        $plan = RatePlan::create([
            'name' => 'Redact me',
            'duration_days' => 30,
            'price' => 100.00,
            'is_active' => true,
        ]);

        $row = DB::table('sync_outbox')->where('entity_type', 'rate_plan')->first();
        $payload = json_decode($row->payload, true);

        foreach ((array) config('sync.redacted_attributes') as $field) {
            $this->assertArrayNotHasKey($field, $payload);
        }

        $this->assertSame($plan->uuid, $payload['uuid']);
    }
}
