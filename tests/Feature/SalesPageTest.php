<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SalesPageTest extends TestCase
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
        Role::findOrCreate('coach');
    }

    public function test_sales_page_loads_for_panel_users(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/sales')
            ->assertOk()
            ->assertSee('sales-page', false);
    }

    public function test_sales_context_returns_sellable_options_for_selected_branch(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $inventoryItem = InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Bottled Water',
            'quantity' => 12,
            'selling_price' => 35,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);
        $ratePlan = $this->createRatePlan('Monthly', 30);
        $ptProduct = $this->createPtProduct('12 Sessions', 12);

        $branch->ratePlans()->attach($ratePlan->id, [
            'price' => 1499,
            'is_active' => true,
        ]);
        $branch->ptProducts()->attach($ptProduct->id, [
            'price' => 3600,
            'coach_commission_rate' => 40,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/sales/context?branch='.$branch->id)
            ->assertOk()
            ->assertJsonPath('options.inventory_items.0.id', $inventoryItem->id)
            ->assertJsonPath('options.membership_rates.0.id', $ratePlan->id)
            ->assertJsonPath('options.pt_rates.0.id', $ptProduct->id);
    }

    public function test_members_list_can_search_branch_members_by_phone_for_sales_selection(): void
    {
        $branch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Zara');
        $member->update([
            'email' => 'zara@example.com',
            'phone' => '09171234567',
        ]);

        $this->createUserWithRole('member', [$otherBranch->id], 'Member Outside')->update([
            'email' => 'outside@example.com',
            'phone' => '09179999999',
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/members/list?branch='.$branch->id.'&search=09171234567')
            ->assertOk()
            ->assertJsonPath('members.data.0.id', $member->id)
            ->assertJsonCount(1, 'members.data');
    }

    public function test_inventory_sale_supports_multiple_items_and_creates_a_receipt_ready_transaction(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $sportsDrink = InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Sports Drink',
            'quantity' => 10,
            'selling_price' => 55,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);
        $water = InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Bottled Water',
            'quantity' => 20,
            'selling_price' => 40,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $branch->id,
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [
                    [
                        'inventory_item_id' => $sportsDrink->id,
                        'quantity' => 2,
                    ],
                    [
                        'inventory_item_id' => $water->id,
                        'quantity' => 2,
                    ],
                ],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 200,
                'sold_at' => '2026-03-29 14:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('type', SaleTransaction::TYPE_INVENTORY)
            ->assertJsonPath('item_name', '2 inventory items')
            ->assertJsonPath('total', 190)
            ->assertJsonPath('amount_received', 200)
            ->assertJsonPath('change_amount', 10);

        $transactionId = $response->json('id');

        $this->assertDatabaseHas('sale_transactions', [
            'id' => $transactionId,
            'branch_id' => $branch->id,
            'type' => SaleTransaction::TYPE_INVENTORY,
            'total' => 190,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $sportsDrink->id,
            'quantity' => 8,
        ]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $water->id,
            'quantity' => 18,
        ]);

        $this->assertDatabaseHas('branch_cash_ledger_entries', [
            'branch_id' => $branch->id,
            'entry_type' => 'inventory_sale',
            'source_id' => $transactionId,
            'amount' => 190,
            'direction' => 'in',
            'is_system' => true,
        ]);

        $this->assertSame(route('panel.sales.receipt.print', $transactionId), $response->json('receipt_url'));
    }

    public function test_inventory_sale_requires_whole_number_quantities(): void
    {
        $branch = $this->createBranch('Naga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ana');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $item = InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Electrolyte Drink',
            'quantity' => 10,
            'selling_price' => 60,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $branch->id,
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [[
                    'inventory_item_id' => $item->id,
                    'quantity' => 1.5,
                ]],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 200,
                'sold_at' => '2026-03-29 14:15:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_membership_sale_can_create_new_member_subscription_and_transaction(): void
    {
        $branch = $this->createBranch('Iriga');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Ben');
        $ratePlan = $this->createRatePlan('6 Months', 180);

        $branch->ratePlans()->attach($ratePlan->id, [
            'price' => 4999.50,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $branch->id,
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'member_mode' => 'new',
                'customer_name' => 'New Member Mia',
                'customer_email' => 'mia@example.com',
                'customer_phone' => '09171234567',
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 5000,
                'sold_at' => '2026-03-29 15:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('type', SaleTransaction::TYPE_MEMBERSHIP)
            ->assertJsonPath('customer_name', 'New Member Mia')
            ->assertJsonPath('item_name', '6 Months')
            ->assertJsonPath('change_amount', 0.5);

        $member = User::where('email', 'mia@example.com')->firstOrFail();

        $this->assertTrue($member->hasRole('member'));
        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $branch->id,
            'user_id' => $member->id,
        ]);
        $subscription = MemberSubscription::where('user_id', $member->id)->firstOrFail();

        $this->assertSame($ratePlan->id, $subscription->rate_plan_id);
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame('2026-04-01', $subscription->start_date?->toDateString());
        $this->assertDatabaseHas('sale_transactions', [
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 4999.50,
        ]);

        $this->assertDatabaseHas('branch_cash_ledger_entries', [
            'branch_id' => $branch->id,
            'entry_type' => 'membership_sale',
            'amount' => 4999.50,
            'direction' => 'in',
            'is_system' => true,
        ]);
    }

    public function test_pt_package_sale_can_attach_to_existing_member_without_coach_assignment(): void
    {
        $branch = $this->createBranch('Daet');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Lou');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Zoe');
        $ptProduct = $this->createPtProduct('24 Sessions', 24);

        $branch->ptProducts()->attach($ptProduct->id, [
            'price' => 7200,
            'coach_commission_rate' => 40,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $branch->id,
                'type' => SaleTransaction::TYPE_PT_PACKAGE,
                'member_mode' => 'existing',
                'member_id' => $member->id,
                'pt_product_id' => $ptProduct->id,
                'assigned_at' => '2026-03-29',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_GCASH,
                'amount_received' => 7200,
                'payment_reference' => 'GCASH-20260329-001',
                'sold_at' => '2026-03-29 16:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('type', SaleTransaction::TYPE_PT_PACKAGE)
            ->assertJsonPath('customer_name', 'Member Zoe')
            ->assertJsonPath('item_name', '24 Sessions')
            ->assertJsonPath('payment_reference', 'GCASH-20260329-001');

        $package = MemberPtPackage::where('user_id', $member->id)->firstOrFail();

        $this->assertNull($package->coach_id);
        $this->assertSame(MemberPtPackage::COMMISSION_STATUS_UNASSIGNED, $package->coach_commission_status);
        $this->assertDatabaseHas('sale_transactions', [
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'total' => 7200,
        ]);

        $this->assertDatabaseMissing('branch_cash_ledger_entries', [
            'branch_id' => $branch->id,
            'entry_type' => 'pt_package_sale',
        ]);
    }

    public function test_cash_pt_package_sale_creates_branch_cash_ledger_entry(): void
    {
        $branch = $this->createBranch('Sorsogon');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Cole');
        $member = $this->createUserWithRole('member', [$branch->id], 'Member Kai');
        $ptProduct = $this->createPtProduct('10 Sessions', 10);

        $branch->ptProducts()->attach($ptProduct->id, [
            'price' => 5000,
            'coach_commission_rate' => 40,
            'is_active' => true,
        ]);

        $transactionId = $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $branch->id,
                'type' => SaleTransaction::TYPE_PT_PACKAGE,
                'member_mode' => 'existing',
                'member_id' => $member->id,
                'pt_product_id' => $ptProduct->id,
                'assigned_at' => '2026-03-29',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 5000,
                'sold_at' => '2026-03-29 16:30:00',
            ])
            ->assertCreated()
            ->json('id');

        $this->assertDatabaseHas('branch_cash_ledger_entries', [
            'branch_id' => $branch->id,
            'entry_type' => 'pt_package_sale',
            'source_id' => $transactionId,
            'amount' => 5000,
            'direction' => 'in',
            'is_system' => true,
        ]);
    }

    public function test_sale_transaction_history_survives_processor_deletion(): void
    {
        $branch = $this->createBranch('Tabaco');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff June');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $item = InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'name' => 'Protein Shake',
            'quantity' => 5,
            'selling_price' => 120,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $transactionId = $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $branch->id,
                'type' => SaleTransaction::TYPE_INVENTORY,
                'inventory_item_id' => $item->id,
                'quantity' => 1,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'sold_at' => '2026-03-29 19:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $staff->delete();

        $this->assertDatabaseHas('sale_transactions', [
            'id' => $transactionId,
        ]);

        $this->assertNull(SaleTransaction::findOrFail($transactionId)->processed_by);
    }

    public function test_receipt_page_can_be_viewed_and_printed_by_branch_staff(): void
    {
        $branch = $this->createBranch('Ligao');
        $staff = $this->createUserWithRole('staff', [$branch->id], 'Staff Rae');
        $transaction = SaleTransaction::factory()->create([
            'branch_id' => $branch->id,
            'processed_by' => $staff->id,
            'customer_name' => 'Customer Joy',
            'item_name' => 'Monthly Membership',
            'details' => [
                'line_items' => [[
                    'name' => 'Monthly Membership',
                    'description' => '30 day membership',
                    'quantity' => 1,
                    'unit' => 'plan',
                    'unit_price' => 1500,
                    'line_total' => 1500,
                ]],
                'payment' => [
                    'amount_received' => 1500,
                    'change_amount' => 0,
                    'reference' => 'POS-123',
                ],
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('panel.sales.receipt', $transaction))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');

        $this->actingAs($staff)
            ->get(route('panel.sales.receipt.print', $transaction))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_walk_in_sale_creates_walk_in_record_and_history_is_branch_scoped(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');
        $otherStaff = $this->createUserWithRole('staff', [$otherBranch->id], 'Staff Bea');

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $accessibleBranch->id,
                'type' => SaleTransaction::TYPE_WALK_IN,
                'customer_name' => 'Walk-in Carla',
                'customer_phone' => '09170000000',
                'amount_paid' => 350,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
                'sold_at' => '2026-03-29 17:00:00',
            ])
            ->assertCreated();

        SaleTransaction::create([
            'branch_id' => $otherBranch->id,
            'member_id' => null,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $otherStaff->id,
            'sold_at' => '2026-03-29 18:00:00',
            'customer_name' => 'Other Branch Guest',
            'item_name' => 'Walk-in',
            'details' => [],
        ]);

        $this->assertDatabaseHas('walk_ins', [
            'branch_id' => $accessibleBranch->id,
            'name' => 'Walk-in Carla',
            'amount_paid' => 350,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/sales/history?branch='.$accessibleBranch->id)
            ->assertOk()
            ->assertJsonPath('transactions.total', 1);

        $this->assertSame('Walk-in Carla', $response->json('transactions.data.0.customer_name'));
        $this->assertNotNull($response->json('transactions.data.0.receipt_url'));
        $this->assertNull($response->json('transactions.data.0.source_url'));

        $this->actingAs($staff)
            ->getJson('/panel/sales/history?branch='.$otherBranch->id)
            ->assertForbidden();
    }

    public function test_sales_store_rejects_inaccessible_branch_via_branch_input_middleware(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'branch_id' => $otherBranch->id,
                'type' => SaleTransaction::TYPE_WALK_IN,
                'customer_name' => 'Unauthorized Guest',
                'amount_paid' => 350,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 400,
                'sold_at' => '2026-03-29 17:30:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('sale_transactions', [
            'branch_id' => $otherBranch->id,
            'customer_name' => 'Unauthorized Guest',
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

    private function createRatePlan(string $name, int $durationDays): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'description' => $name.' membership',
            'is_active' => true,
        ]);
    }

    private function createPtProduct(string $name, int $sessionCount): PTProduct
    {
        return PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'description' => $name.' PT package',
            'is_active' => true,
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
