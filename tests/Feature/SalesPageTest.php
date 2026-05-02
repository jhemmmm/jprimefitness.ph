<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
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

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
        ]);
    }

    public function test_sales_page_loads_for_panel_users(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        $this->actingAs($staff)
            ->get('/panel/sales')
            ->assertOk()
            ->assertSee('sales-page', false);
    }

    public function test_sales_context_returns_global_sellable_options(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $inventoryItem = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Bottled Water',
            'quantity' => 12,
            'selling_price' => 35,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);
        $ratePlan = $this->createRatePlan('Monthly', 30, [
            'price' => 1499,
        ]);
        $ptProduct = $this->createPtProduct('12 Sessions', 12, [
            'price' => 3600,
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/sales/context')
            ->assertOk()
            ->assertJsonMissingPath('location')
            ->assertJsonPath('options.inventory_items.0.id', $inventoryItem->id)
            ->assertJsonPath('options.membership_rates.0.id', $ratePlan->id)
            ->assertJsonPath('options.pt_rates.0.id', $ptProduct->id);
    }

    public function test_members_list_can_search_members_by_phone_for_sales_selection(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $member = $this->createUserWithRole('member', 'Member Zara');
        $member->update([
            'email' => 'zara@example.com',
            'phone' => '09171234567',
        ]);

        $this->createUserWithRole('member', 'Member Outside')->update([
            'email' => 'outside@example.com',
            'phone' => '09179999999',
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/members/list?search=09171234567')
            ->assertOk()
            ->assertJsonPath('members.data.0.id', $member->id)
            ->assertJsonCount(1, 'members.data');
    }

    public function test_inventory_sale_supports_multiple_items_and_creates_a_receipt_ready_transaction(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $sportsDrink = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Sports Drink',
            'quantity' => 10,
            'selling_price' => 55,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);
        $water = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Bottled Water',
            'quantity' => 20,
            'selling_price' => 40,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/panel/sales', [
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

        $this->assertSame(route('panel.sales.receipt', $transactionId), $response->json('receipt_url'));
    }

    public function test_inventory_sale_requires_whole_number_quantities(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Electrolyte Drink',
            'quantity' => 10,
            'selling_price' => 60,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
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

    public function test_membership_sale_can_attach_to_existing_member_subscription_and_transaction(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ben');
        $member = $this->createUserWithRole('member', 'Member Mia');
        $ratePlan = $this->createRatePlan('6 Months', 180, [
            'price' => 4999.50,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'member_id' => $member->id,
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 5000,
                'sold_at' => '2026-03-29 15:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('type', SaleTransaction::TYPE_MEMBERSHIP)
            ->assertJsonPath('customer_name', 'Member Mia')
            ->assertJsonPath('item_name', '6 Months')
            ->assertJsonPath('change_amount', 0.5);

        $subscription = MemberSubscription::where('user_id', $member->id)->firstOrFail();

        $this->assertSame($ratePlan->id, $subscription->rate_plan_id);
        $this->assertSame(MemberSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame('2026-04-01', $subscription->start_date?->toDateString());

        $this->assertDatabaseHas('sale_transactions', [
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 4999.50,
        ]);
    }

    public function test_membership_sale_requires_existing_member_selection(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ben');
        $ratePlan = $this->createRatePlan('Monthly', 30, [
            'price' => 1499,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'member_mode' => 'new',
                'customer_name' => 'New Member Mia',
                'customer_email' => 'mia@example.com',
                'customer_phone' => '09171234567',
                'rate_plan_id' => $ratePlan->id,
                'start_date' => '2026-04-01',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 1500,
                'sold_at' => '2026-03-29 15:00:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_id']);

        $this->assertDatabaseMissing('users', [
            'email' => 'mia@example.com',
        ]);
        $this->assertDatabaseCount('member_subscriptions', 0);
        $this->assertDatabaseCount('sale_transactions', 0);
    }

    public function test_pt_package_sale_can_attach_to_existing_member_without_coach_assignment(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Lou');
        $member = $this->createUserWithRole('member', 'Member Zoe');
        $ptProduct = $this->createPtProduct('24 Sessions', 24, [
            'price' => 7200,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_PT_PACKAGE,
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

        $this->assertDatabaseHas('sale_transactions', [
            'member_id' => $member->id,
            'type' => SaleTransaction::TYPE_PT_PACKAGE,
            'total' => 7200,
        ]);
    }

    public function test_sales_page_no_longer_renders_new_member_sale_controls(): void
    {
        $component = file_get_contents(resource_path('js/components/panel/SalesPage.vue'));

        $this->assertStringNotContainsString('New Member', $component);
        $this->assertStringNotContainsString('member_mode', $component);
        $this->assertStringNotContainsString('customer_email', $component);
        $this->assertStringContainsString('Search Member', $component);
    }

    public function test_sale_transaction_history_survives_processor_deletion(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff June');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $item = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Protein Shake',
            'quantity' => 5,
            'selling_price' => 120,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

        $transactionId = $this->actingAs($staff)
            ->postJson('/panel/sales', [
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

        $transaction = SaleTransaction::findOrFail($transactionId);

        $this->assertSame($staff->id, $transaction->processed_by);
        $this->assertNull($transaction->processedBy);
    }

    public function test_receipt_page_can_be_viewed_and_printed_by_staff(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Rae');
        $transaction = SaleTransaction::create([
            'processed_by' => $staff->id,
            'customer_name' => 'Customer Joy',
            'item_name' => 'Monthly Membership',
            'type' => SaleTransaction::TYPE_MEMBERSHIP,
            'total' => 1500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'sold_at' => '2026-03-29 17:00:00',
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
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_walk_in_sale_creates_transaction_and_history_is_global(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $otherStaff = $this->createUserWithRole('staff', 'Staff Bea');

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
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
            'member_id' => null,
            'type' => SaleTransaction::TYPE_WALK_IN,
            'total' => 500,
            'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
            'processed_by' => $otherStaff->id,
            'sold_at' => '2026-03-29 18:00:00',
            'customer_name' => 'Other Guest',
            'item_name' => 'Walk-in',
            'details' => [],
        ]);

        $this->assertDatabaseHas('sale_transactions', [
            'type' => SaleTransaction::TYPE_WALK_IN,
            'customer_name' => 'Walk-in Carla',
            'total' => 350,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/sales/history')
            ->assertOk()
            ->assertJsonPath('transactions.total', 2);

        $customerNames = collect($response->json('transactions.data'))->pluck('customer_name')->all();

        $this->assertContains('Walk-in Carla', $customerNames);
        $this->assertContains('Other Guest', $customerNames);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRatePlan(string $name, int $durationDays, array $attributes = []): RatePlan
    {
        return RatePlan::create(array_merge([
            'name' => $name,
            'duration_days' => $durationDays,
            'description' => $name.' membership',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPtProduct(string $name, int $sessionCount, array $attributes = []): PTProduct
    {
        return PTProduct::create(array_merge([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => PTProduct::CATEGORY_PACKAGE,
            'description' => $name.' PT package',
            'is_active' => true,
        ], $attributes));
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
