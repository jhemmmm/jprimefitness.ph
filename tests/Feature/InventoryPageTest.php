<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryPageTest extends TestCase
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
    }

    public function test_inventory_page_loads_for_panel_users(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Mia');

        $this->actingAs($manager)
            ->get('/panel/inventory')
            ->assertOk()
            ->assertSee('inventory-page', false);
    }

    public function test_inventory_default_categories_exist_after_migration_without_manual_seeding(): void
    {
        $this->assertDatabaseHas('inventory_categories', [
            'slug' => 'equipment',
            'name' => 'Equipment',
        ]);

        $this->assertDatabaseHas('inventory_categories', [
            'slug' => 'cleaning-supplies',
            'name' => 'Cleaning Supplies',
        ]);
    }

    public function test_inventory_list_is_scoped_to_accessible_branches_and_reports_stats(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');
        $drinkCategory = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $supplementCategory = InventoryCategory::factory()->create(['name' => 'Supplements']);

        InventoryItem::factory()->create([
            'branch_id' => $accessibleBranch->id,
            'inventory_category_id' => $drinkCategory->id,
            'name' => 'Bottled Water',
            'quantity' => 12,
            'low_stock_threshold' => 5,
        ]);
        InventoryItem::factory()->create([
            'branch_id' => $accessibleBranch->id,
            'inventory_category_id' => $supplementCategory->id,
            'name' => 'Protein Shake',
            'quantity' => 2,
            'low_stock_threshold' => 5,
        ]);
        InventoryItem::factory()->create([
            'branch_id' => $accessibleBranch->id,
            'inventory_category_id' => $supplementCategory->id,
            'name' => 'Towel',
            'quantity' => 0,
            'low_stock_threshold' => 3,
        ]);
        InventoryItem::factory()->create([
            'branch_id' => $otherBranch->id,
            'inventory_category_id' => $drinkCategory->id,
            'name' => 'Other Branch Item',
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/inventory/list')
            ->assertOk()
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.low_stock', 1)
            ->assertJsonPath('stats.out_of_stock', 1);

        $names = collect($response->json('inventory.data'))->pluck('name')->all();

        $this->assertContains('Bottled Water', $names);
        $this->assertContains('Protein Shake', $names);
        $this->assertContains('Towel', $names);
        $this->assertNotContains('Other Branch Item', $names);
    }

    public function test_inventory_list_does_not_leak_other_branch_items_when_filtered_by_branch(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);

        InventoryItem::factory()->create([
            'branch_id' => $accessibleBranch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Bottled Water',
        ]);
        InventoryItem::factory()->create([
            'branch_id' => $otherBranch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Other Branch Item',
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/inventory/list?branch='.$otherBranch->id)
            ->assertOk()
            ->assertJsonPath('stats.total', 0)
            ->assertJsonPath('inventory.data', []);
    }

    public function test_inventory_list_can_be_filtered_by_category(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');
        $drinkCategory = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $supplementCategory = InventoryCategory::factory()->create(['name' => 'Supplements']);

        InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $drinkCategory->id,
            'name' => 'Bottled Water',
        ]);
        InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $supplementCategory->id,
            'name' => 'Whey Protein',
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/inventory/list?category='.$drinkCategory->id)
            ->assertOk()
            ->assertJsonPath('stats.total', 1);

        $names = collect($response->json('inventory.data'))->pluck('name')->all();

        $this->assertSame(['Bottled Water'], $names);
    }

    public function test_staff_can_create_update_and_delete_inventory_items_for_accessible_branch(): void
    {
        $branch = $this->createBranch('Iriga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ben');
        $category = InventoryCategory::factory()->create(['name' => 'Equipment']);

        $createResponse = $this->actingAs($staff)
            ->postJson('/panel/inventory', [
                'branch_id' => $branch->id,
                'inventory_category_id' => $category->id,
                'name' => 'Yoga Mat',
                'sku' => null,
                'unit' => 'pcs',
                'quantity' => 8,
                'low_stock_threshold' => 3,
                'cost_price' => 550,
                'selling_price' => 899,
                'status' => InventoryItem::STATUS_ACTIVE,
                'notes' => 'Top shelf display',
                'last_restocked_at' => '2026-03-20 10:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Yoga Mat')
            ->assertJsonPath('branch.id', $branch->id);

        $itemId = $createResponse->json('id');

        $this->actingAs($staff)
            ->putJson("/panel/inventory/{$itemId}", [
                'branch_id' => $branch->id,
                'inventory_category_id' => $category->id,
                'name' => 'Yoga Mat',
                'sku' => null,
                'unit' => 'pcs',
                'quantity' => 2,
                'low_stock_threshold' => 3,
                'cost_price' => 550,
                'selling_price' => 899,
                'status' => InventoryItem::STATUS_ACTIVE,
                'notes' => 'Moved near the cashier',
                'last_restocked_at' => '2026-03-21 09:30:00',
            ])
            ->assertOk()
            ->assertJsonPath('quantity', '2.00')
            ->assertJsonPath('is_low_stock', true);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $itemId,
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'quantity' => 2,
            'notes' => 'Moved near the cashier',
        ]);

        $this->actingAs($staff)
            ->deleteJson("/panel/inventory/{$itemId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('inventory_items', [
            'id' => $itemId,
        ]);
    }

    public function test_staff_cannot_manage_inventory_for_inaccessible_branches(): void
    {
        $accessibleBranch = $this->createBranch('Daet');
        $otherBranch = $this->createBranch('Tabaco');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Gio');
        $category = InventoryCategory::factory()->create(['name' => 'Supplies']);
        $item = InventoryItem::factory()->create([
            'branch_id' => $otherBranch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Restricted Item',
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/inventory', [
                'branch_id' => $otherBranch->id,
                'inventory_category_id' => $category->id,
                'name' => 'Unauthorized Item',
                'sku' => 'INV-LOCK-001',
                'unit' => 'pcs',
                'quantity' => 4,
                'low_stock_threshold' => 1,
                'cost_price' => 125,
                'selling_price' => 250,
                'status' => InventoryItem::STATUS_ACTIVE,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->putJson("/panel/inventory/{$item->id}", [
                'branch_id' => $otherBranch->id,
                'inventory_category_id' => $category->id,
                'name' => 'Restricted Item',
                'sku' => 'INV-REST-001',
                'unit' => 'pcs',
                'quantity' => 2,
                'low_stock_threshold' => 1,
                'cost_price' => 125,
                'selling_price' => 250,
                'status' => InventoryItem::STATUS_ACTIVE,
            ])
            ->assertNotFound();
    }

    public function test_inventory_requires_category_but_not_sku_or_prices(): void
    {
        $branch = $this->createBranch('Iriga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ben');
        $category = InventoryCategory::factory()->create(['name' => 'Equipment']);

        $this->actingAs($staff)
            ->postJson('/panel/inventory', [
                'branch_id' => $branch->id,
                'name' => 'Foam Roller',
                'sku' => null,
                'unit' => 'pcs',
                'quantity' => 5,
                'low_stock_threshold' => 2,
                'status' => InventoryItem::STATUS_ACTIVE,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inventory_category_id']);

        $this->actingAs($staff)
            ->postJson('/panel/inventory', [
                'branch_id' => $branch->id,
                'inventory_category_id' => $category->id,
                'name' => 'Foam Roller',
                'sku' => null,
                'unit' => 'pcs',
                'quantity' => 5,
                'low_stock_threshold' => 2,
                'status' => InventoryItem::STATUS_ACTIVE,
            ])
            ->assertCreated()
            ->assertJsonPath('sku', null)
            ->assertJsonPath('cost_price', null)
            ->assertJsonPath('selling_price', null)
            ->assertJsonPath('category.id', $category->id);
    }

    public function test_edit_modal_zero_price_values_remain_persisted_on_save(): void
    {
        $branch = $this->createBranch('Iriga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ben');
        $category = InventoryCategory::factory()->create(['name' => 'Supplies']);
        $item = InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Complimentary Towel',
            'cost_price' => 0,
            'selling_price' => 0,
            'notes' => 'Original note',
        ]);

        $this->actingAs($staff)
            ->putJson("/panel/inventory/{$item->id}", [
                'branch_id' => $branch->id,
                'inventory_category_id' => $category->id,
                'name' => 'Complimentary Towel',
                'sku' => $item->sku,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'low_stock_threshold' => $item->low_stock_threshold,
                'cost_price' => 0,
                'selling_price' => 0,
                'status' => $item->status,
                'notes' => 'Updated note',
            ])
            ->assertOk()
            ->assertJsonPath('cost_price', '0.00')
            ->assertJsonPath('selling_price', '0.00');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'cost_price' => 0,
            'selling_price' => 0,
            'notes' => 'Updated note',
        ]);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => Branch::COUNTRY_PHILIPPINES,
            'city' => 'Naga City',
        ]);
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createUserWithRole(string $role, array $branchIds, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
