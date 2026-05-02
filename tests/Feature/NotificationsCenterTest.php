<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MemberPtPackage;
use App\Models\MemberSubscription;
use App\Models\Payroll;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\User;
use App\Notifications\InventoryStockAlertNotification;
use App\Services\InventoryStockAlertService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationsCenterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'super admin',
            'admin',
            'manager',
            'staff',
            'member',
            'employee',
            'coach',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName);
        }

        $permission = Permission::findOrCreate('manage employees');

        Role::findByName('super admin')->givePermissionTo($permission);
        Role::findByName('admin')->givePermissionTo($permission);
        Role::findByName('manager')->givePermissionTo($permission);
        Role::findByName('staff')->givePermissionTo($permission);
    }

    public function test_panel_users_can_fetch_and_mark_notifications_as_read(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        $item = $this->createInventoryItem([
            'name' => 'Whey Protein',
            'quantity' => 3,
            'low_stock_threshold' => 5,
        ]);

        $staff->notify(new InventoryStockAlertNotification($item, InventoryStockAlertService::STATE_LOW_STOCK));
        $staff->notify(new InventoryStockAlertNotification($item, InventoryStockAlertService::STATE_OUT_OF_STOCK));

        $response = $this->actingAs($staff)
            ->getJson('/panel/notifications/list?filter=unread&per_page=20')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('notifications.total', 2);

        $notificationId = $response->json('notifications.data.0.id');

        $this->actingAs($staff)
            ->postJson("/panel/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertCount(1, $staff->fresh()->unreadNotifications);

        $this->actingAs($staff)
            ->postJson('/panel/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertCount(0, $staff->fresh()->unreadNotifications);
    }

    public function test_member_role_cannot_access_notification_endpoints(): void
    {
        $member = $this->createUserWithRole('member', 'Member Max');

        $this->actingAs($member)
            ->getJson('/panel/notifications/list')
            ->assertForbidden();

        $this->actingAs($member)
            ->get('/panel/notifications')
            ->assertRedirect(route('home'));
    }

    public function test_notifications_page_renders_inside_panel_layout(): void
    {
        $staff = $this->createUserWithRole('staff', 'Staff Ina');

        $this->actingAs($staff)
            ->get('/panel/notifications')
            ->assertOk()
            ->assertSee('notifications-page', false)
            ->assertSee('panel-notifications', false);
    }

    public function test_payroll_approval_creates_notifications_for_global_admins_and_managers(): void
    {
        $superAdmin = $this->createUserWithRole('super admin', 'Super Admin Sue');
        $actingAdmin = $this->createUserWithRole('admin', 'Admin Ava');
        $recipientAdmin = $this->createUserWithRole('admin', 'Admin Abe');
        $managerA = $this->createUserWithRole('manager', 'Manager Mia');
        $managerB = $this->createUserWithRole('manager', 'Manager Ben');
        $staff = $this->createUserWithRole('staff', 'Staff Sol');
        $employee = $this->createUserWithRole('employee', 'Employee Eli');

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'gross_amount' => 10000,
            'bonus' => 0,
            'withholding_tax' => 0,
            'manual_deductions' => 0,
            'net_amount' => 10000,
            'status' => Payroll::STATUS_DRAFT,
            'generated_by' => $actingAdmin->id,
        ]);

        $this->actingAs($actingAdmin)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$payroll->id}/approve")
            ->assertOk();

        $this->assertSame(['payroll-approved'], $this->notificationTypesFor($superAdmin));
        $this->assertSame(['payroll-approved'], $this->notificationTypesFor($actingAdmin));
        $this->assertSame(['payroll-approved'], $this->notificationTypesFor($recipientAdmin));
        $this->assertSame(['payroll-approved'], $this->notificationTypesFor($managerA));
        $this->assertSame(['payroll-approved'], $this->notificationTypesFor($managerB));
        $this->assertSame([], $this->notificationTypesFor($staff));
        $this->assertSame([], $this->notificationTypesFor($employee));
    }

    public function test_inventory_stock_alerts_only_fire_on_threshold_crossings_for_global_recipients(): void
    {
        $actingAdmin = $this->createUserWithRole('admin', 'Admin Tia');
        $superAdmin = $this->createUserWithRole('super admin', 'Super Admin Theo');
        $recipientAdmin = $this->createUserWithRole('admin', 'Admin Tala');
        $managerA = $this->createUserWithRole('manager', 'Manager Tori');
        $managerB = $this->createUserWithRole('manager', 'Manager Lio');
        $staff = $this->createUserWithRole('staff', 'Staff Taz');

        $item = $this->createInventoryItem([
            'name' => 'Yoga Mat',
            'quantity' => 10,
            'low_stock_threshold' => 5,
        ]);

        $payload = [
            'inventory_category_id' => $item->inventory_category_id,
            'name' => 'Yoga Mat',
            'sku' => 'MAT-001',
            'unit' => 'pcs',
            'quantity' => 5,
            'low_stock_threshold' => 5,
            'cost_price' => 200,
            'selling_price' => 350,
            'status' => InventoryItem::STATUS_ACTIVE,
            'notes' => 'First threshold crossing',
        ];

        $this->actingAs($actingAdmin)
            ->putJson("/panel/inventory/{$item->id}", $payload)
            ->assertOk();

        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($actingAdmin));
        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($superAdmin));
        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($recipientAdmin));
        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($managerA));
        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($managerB));
        $this->assertSame([], $this->notificationTypesFor($staff));

        $payload['quantity'] = 4;
        $payload['notes'] = 'Still low';

        $this->actingAs($actingAdmin)
            ->putJson("/panel/inventory/{$item->id}", $payload)
            ->assertOk();

        $this->assertCount(1, $actingAdmin->fresh()->notifications);

        $payload['quantity'] = 0;
        $payload['notes'] = 'Now out of stock';

        $this->actingAs($actingAdmin)
            ->putJson("/panel/inventory/{$item->id}", $payload)
            ->assertOk();

        $this->assertCount(2, $actingAdmin->fresh()->notifications);

        $payload['quantity'] = 8;
        $payload['notes'] = 'Restocked';

        $this->actingAs($actingAdmin)
            ->putJson("/panel/inventory/{$item->id}", $payload)
            ->assertOk();

        $payload['quantity'] = 5;
        $payload['notes'] = 'Low again after reset';

        $this->actingAs($actingAdmin)
            ->putJson("/panel/inventory/{$item->id}", $payload)
            ->assertOk();

        $this->assertCount(3, $actingAdmin->fresh()->notifications);
    }

    public function test_inventory_sale_triggers_stock_alert_notifications_when_sale_crosses_the_threshold(): void
    {
        $superAdmin = $this->createUserWithRole('super admin', 'Super Admin Sal');
        $admin = $this->createUserWithRole('admin', 'Admin Miko');
        $manager = $this->createUserWithRole('manager', 'Manager Mara');
        $staff = $this->createUserWithRole('staff', 'Staff Mae');
        $item = $this->createInventoryItem([
            'name' => 'Creatine',
            'quantity' => 6,
            'low_stock_threshold' => 5,
        ]);

        $this->actingAs($staff)
            ->postJson('/panel/sales', [
                'type' => 'inventory',
                'items' => [
                    [
                        'inventory_item_id' => $item->id,
                        'quantity' => 1,
                    ],
                ],
                'payment_method' => 'cash',
                'amount_received' => 500,
                'sold_at' => '2026-04-03 10:00:00',
            ])
            ->assertCreated();

        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($superAdmin));
        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($admin));
        $this->assertSame(['inventory-stock-alert'], $this->notificationTypesFor($manager));
        $this->assertSame([], $this->notificationTypesFor($staff));

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'quantity' => 5,
            'stock_alert_state' => InventoryStockAlertService::STATE_LOW_STOCK,
        ]);
    }

    public function test_expiring_membership_command_notifies_once_per_end_date(): void
    {
        $this->travelTo(Carbon::parse('2026-04-03 08:00:00'));

        $ratePlan = $this->createRatePlan('Monthly', 30);

        $superAdmin = $this->createUserWithRole('super admin', 'Super Admin Nia');
        $admin = $this->createUserWithRole('admin', 'Admin Nilo');
        $managerA = $this->createUserWithRole('manager', 'Manager Nessa');
        $managerB = $this->createUserWithRole('manager', 'Manager Polo');
        $staff = $this->createUserWithRole('staff', 'Staff Nia');
        $member = $this->createUserWithRole('member', 'Member Noel');

        $subscription = MemberSubscription::create([
            'user_id' => $member->id,
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 1500,
            'start_date' => '2026-03-10',
            'end_date' => '2026-04-08',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);

        MemberSubscription::create([
            'user_id' => $member->id,
            'rate_plan_id' => $ratePlan->id,
            'sold_price' => 1500,
            'start_date' => '2026-03-10',
            'end_date' => '2026-04-20',
            'status' => MemberSubscription::STATUS_ACTIVE,
        ]);

        $this->artisan('panel:send-expiring-membership-notifications')
            ->assertExitCode(0);

        $this->assertSame(['membership-expiring'], $this->notificationTypesFor($superAdmin));
        $this->assertSame(['membership-expiring'], $this->notificationTypesFor($admin));
        $this->assertSame(['membership-expiring'], $this->notificationTypesFor($managerA));
        $this->assertSame(['membership-expiring'], $this->notificationTypesFor($managerB));
        $this->assertSame([], $this->notificationTypesFor($staff));

        $subscription->refresh();
        $this->assertSame('2026-04-08', $subscription->expiration_notification_sent_for_date?->toDateString());

        $this->artisan('panel:send-expiring-membership-notifications')
            ->assertExitCode(0);

        $this->assertCount(1, $superAdmin->fresh()->notifications);

        $subscription->update([
            'end_date' => '2026-04-09',
        ]);

        $this->artisan('panel:send-expiring-membership-notifications')
            ->assertExitCode(0);

        $this->assertCount(2, $superAdmin->fresh()->notifications);

        $this->travelBack();
    }

    public function test_pt_usage_notifies_when_a_package_first_drops_to_low_remaining_sessions(): void
    {
        $superAdmin = $this->createUserWithRole('super admin', 'Super Admin Tia');
        $admin = $this->createUserWithRole('admin', 'Admin Timo');
        $managerA = $this->createUserWithRole('manager', 'Manager Teri');
        $managerB = $this->createUserWithRole('manager', 'Manager Lani');
        $staff = $this->createUserWithRole('staff', 'Staff Tovi');
        $coach = $this->createUserWithRole('coach', 'Coach Theo');
        $member = $this->createUserWithRole('member', 'Member Tali');
        $product = $this->createPtProduct('12 Sessions', 12);

        $package = MemberPtPackage::create([
            'user_id' => $member->id,
            'pt_product_id' => $product->id,
            'coach_id' => $coach->id,
            'total_sessions' => 12,
            'remaining_sessions' => 4,
            'assigned_at' => '2026-04-01',
            'status' => MemberPtPackage::STATUS_ACTIVE,
        ]);

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 2,
                'used_at' => '2026-04-03 18:00:00',
            ])
            ->assertCreated();

        $this->assertSame(['pt-package-running-low'], $this->notificationTypesFor($superAdmin));
        $this->assertSame(['pt-package-running-low'], $this->notificationTypesFor($admin));
        $this->assertSame(['pt-package-running-low'], $this->notificationTypesFor($managerA));
        $this->assertSame(['pt-package-running-low'], $this->notificationTypesFor($managerB));
        $this->assertSame([], $this->notificationTypesFor($staff));

        $this->actingAs($staff)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'sessions_used' => 1,
                'used_at' => '2026-04-04 18:00:00',
            ])
            ->assertCreated();

        $this->assertCount(1, $superAdmin->fresh()->notifications);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'email' => str($name)->slug('.').'@example.test',
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInventoryItem(array $attributes = []): InventoryItem
    {
        return InventoryItem::factory()->create(array_merge([
            'inventory_category_id' => InventoryCategory::factory()->create()->id,
            'name' => 'Inventory Item',
            'sku' => null,
            'unit' => 'pcs',
            'quantity' => 10,
            'low_stock_threshold' => 5,
            'cost_price' => 100,
            'selling_price' => 150,
            'status' => InventoryItem::STATUS_ACTIVE,
        ], $attributes));
    }

    private function createRatePlan(string $name, int $durationDays): RatePlan
    {
        return RatePlan::create([
            'name' => $name,
            'duration_days' => $durationDays,
            'price' => 1500,
            'is_active' => true,
        ]);
    }

    private function createPtProduct(string $name, int $sessionCount): PTProduct
    {
        return PTProduct::create([
            'name' => $name,
            'session_count' => $sessionCount,
            'category' => $sessionCount === 1 ? PTProduct::CATEGORY_SINGLE : PTProduct::CATEGORY_PACKAGE,
            'price' => 500,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function notificationTypesFor(User $user): array
    {
        return $user->fresh()
            ->notifications()
            ->latest()
            ->get()
            ->pluck('data.type')
            ->values()
            ->all();
    }
}
