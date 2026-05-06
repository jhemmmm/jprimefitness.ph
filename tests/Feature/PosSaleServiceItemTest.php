<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\SaleTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PosSaleServiceItemTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super admin');
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        Role::findOrCreate('member');

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
        ]);
    }

    public function test_service_item_appears_in_pos_context_even_when_quantity_is_zero(): void
    {
        $staff = $this->createStaff();
        $services = InventoryCategory::factory()->create(['name' => 'Services']);

        $shower = InventoryItem::factory()->service()->create([
            'inventory_category_id' => $services->id,
            'name' => 'Shower',
            'selling_price' => 20,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/sales/context')
            ->assertOk();

        $items = collect($response->json('options.inventory_items'));
        $serviceItem = $items->firstWhere('id', $shower->id);

        $this->assertNotNull($serviceItem, 'Service item should be returned by the POS context.');
        $this->assertFalse($serviceItem['tracks_stock']);
        $this->assertSame(20.0, (float) $serviceItem['selling_price']);
    }

    public function test_service_item_can_be_sold_without_decrementing_stock(): void
    {
        $staff = $this->createStaff();
        $services = InventoryCategory::factory()->create(['name' => 'Services']);

        $shower = InventoryItem::factory()->service()->create([
            'inventory_category_id' => $services->id,
            'name' => 'Shower',
            'selling_price' => 20,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [[
                    'inventory_item_id' => $shower->id,
                    'quantity' => 3,
                ]],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 100,
                'sold_at' => '2026-05-07 09:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('total', 60)
            ->assertJsonPath('change_amount', 40);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $shower->id,
            'quantity' => 0,
            'tracks_stock' => false,
        ]);
    }

    public function test_mixed_cart_decrements_stocked_items_only(): void
    {
        $staff = $this->createStaff();
        $drinks = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $services = InventoryCategory::factory()->create(['name' => 'Services']);

        $bottledWater = InventoryItem::factory()->create([
            'inventory_category_id' => $drinks->id,
            'name' => 'Bottled Water',
            'quantity' => 10,
            'tracks_stock' => true,
            'selling_price' => 35,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $waterRefill = InventoryItem::factory()->service()->create([
            'inventory_category_id' => $services->id,
            'name' => 'Water Refill',
            'selling_price' => 10,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [
                    ['inventory_item_id' => $bottledWater->id, 'quantity' => 2],
                    ['inventory_item_id' => $waterRefill->id, 'quantity' => 4],
                ],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 200,
                'sold_at' => '2026-05-07 10:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('total', 110);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $bottledWater->id,
            'quantity' => 8,
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $waterRefill->id,
            'quantity' => 0,
        ]);
    }

    public function test_service_items_are_never_low_or_out_of_stock(): void
    {
        $services = InventoryCategory::factory()->create(['name' => 'Services']);

        $shower = InventoryItem::factory()->service()->create([
            'inventory_category_id' => $services->id,
            'name' => 'Shower',
            'selling_price' => 20,
        ]);

        $this->assertFalse($shower->is_out_of_stock);
        $this->assertFalse($shower->is_low_stock);
    }

    public function test_stocked_item_still_rejects_oversell(): void
    {
        $staff = $this->createStaff();
        $drinks = InventoryCategory::factory()->create(['name' => 'Drinks']);

        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $drinks->id,
            'name' => 'Sports Drink',
            'quantity' => 1,
            'tracks_stock' => true,
            'selling_price' => 60,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [[
                    'inventory_item_id' => $item->id,
                    'quantity' => 5,
                ]],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
                'sold_at' => '2026-05-07 11:00:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    private function createStaff(): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => 'Staff Ana',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole('staff');

        return $user;
    }
}
