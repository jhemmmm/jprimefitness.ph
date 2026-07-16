<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SystemActivity;
use App\Models\BusinessProfile;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MemberPtPackage;
use App\Models\MemberPtSessionUsage;
use App\Models\MemberSubscription;
use App\Models\Payroll;
use App\Models\PTProduct;
use App\Models\RatePlan;
use App\Models\SaleTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SystemActivityExpansionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super admin', 'admin', 'manager', 'staff', 'member', 'employee', 'coach'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $permission = Permission::findOrCreate('manage employees');

        Role::findByName('super admin')->givePermissionTo($permission);
        Role::findByName('admin')->givePermissionTo($permission);
        Role::findByName('manager')->givePermissionTo($permission);

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness Naga',
        ]);
    }

    public function test_super_admin_admin_and_manager_can_access_system_activity_with_registry_metadata(): void
    {
        $superAdmin = $this->createUserWithRole('super admin', 'Super Admin Sue');
        $admin = $this->createUserWithRole('admin', 'Admin Ava');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        $member = $this->createUserWithRole('member', 'Member Max');

        SystemActivity::factory()->create([
            'subject_type' => SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            'subject_id' => 88,
            'subject_label' => 'Membership #88 - '.$member->name,
            'event' => 'created',
            'title' => 'Membership created',
            'message' => 'A membership was created for '.$member->name.'.',
            'actor_name' => $manager->name,
            'metadata' => [
                'member_id' => $member->id,
                'member_name' => $member->name,
                'rate_plan_name' => 'Monthly',
                'status' => MemberSubscription::STATUS_ACTIVE,
                'caused_by' => [
                    'subject_type' => SystemActivity::SUBJECT_SALE_TRANSACTION,
                    'subject_id' => 123,
                    'subject_label' => 'Sale #123 - '.$member->name,
                    'event' => 'created',
                    'context' => [
                        'member_id' => $member->id,
                    ],
                ],
            ],
            'occurred_at' => '2026-04-09 10:00:00',
        ]);

        foreach ([$superAdmin, $admin, $manager] as $allowedUser) {
            $this->actingAs($allowedUser)
                ->get('/panel/system-activity')
                ->assertOk()
                ->assertSee('system-activity-page', false);
        }

        $response = $this->actingAs($manager)
            ->getJson('/panel/system-activity/list?subject_type=member_subscription&event=created&per_page=10')
            ->assertOk();

        $this->assertSame([
            SystemActivity::SUBJECT_BUSINESS_PROFILE,
            SystemActivity::SUBJECT_EMPLOYEE,
            SystemActivity::SUBJECT_PAYROLL,
            SystemActivity::SUBJECT_PAYOUT,
            SystemActivity::SUBJECT_MEMBER,
            SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION,
            SystemActivity::SUBJECT_MEMBER_PT_PACKAGE,
            SystemActivity::SUBJECT_MEMBER_PT_SESSION_USAGE,
            SystemActivity::SUBJECT_ATTENDANCE,
            SystemActivity::SUBJECT_SALE_TRANSACTION,
            SystemActivity::SUBJECT_INVENTORY_ITEM,
            SystemActivity::SUBJECT_RATE_PLAN,
            SystemActivity::SUBJECT_PT_PRODUCT,
        ], collect($response->json('meta.subject_types'))->pluck('value')->all());

        $this->assertContains('stock_deducted', collect($response->json('meta.event_options'))->pluck('value')->all());
        $this->assertContains('voided', collect($response->json('meta.event_options'))->pluck('value')->all());

        $response
            ->assertJsonPath('events.total', 1)
            ->assertJsonPath('events.data.0.subject_type', SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION)
            ->assertJsonPath('events.data.0.subject_type_label', 'Memberships')
            ->assertJsonPath('events.data.0.action_url', route('panel.members.show', $member))
            ->assertJsonPath('events.data.0.caused_by.subject_type', SystemActivity::SUBJECT_SALE_TRANSACTION)
            ->assertJsonPath('events.data.0.caused_by.event_label', 'Created')
            ->assertJsonPath('events.data.0.caused_by.action_url', route('panel.sales.index'));

        $this->actingAs($staff)
            ->get('/panel/system-activity')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/system-activity/list')
            ->assertForbidden();
    }

    public function test_business_profile_changes_record_system_activities(): void
    {
        $admin = $this->createUserWithRole('admin', 'Admin Bea');

        $this->actingAs($admin)
            ->putJson('/panel/business/settings', $this->businessSettingsPayload([
                'name' => 'JPrime Fitness System Activity Hub',
                'city' => 'Naga City',
            ]))
            ->assertOk()
            ->assertJsonPath('name', 'JPrime Fitness System Activity Hub');

        $this->assertSame(
            ['updated'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_BUSINESS_PROFILE)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );
    }

    public function test_employee_payroll_payout_side_effects_record_system_activities(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Pia');
        $coachRoleId = Role::findByName('coach')->id;
        $staffRoleId = Role::findByName('staff')->id;

        $employeeId = $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Lou',
                'email' => 'coach.lou@example.com',
                'phone' => '09170000001',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$coachRoleId],
                'employee_profile' => [
                    'daily_rate' => 800,
                    'pay_frequency' => 'semi_monthly',
                    'sss_covered' => true,
                    'sss_monthly_compensation' => 20250,
                    'philhealth_covered' => true,
                    'philhealth_monthly_basic_salary' => 20000,
                    'pagibig_covered' => true,
                    'pagibig_monthly_compensation' => 20000,
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->json('id');

        $employee = User::findOrFail($employeeId);

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employeeId}", [
                'name' => 'Coach Lou Updated',
                'email' => 'coach.lou@example.com',
                'phone' => '09170000002',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$coachRoleId],
                'employee_profile' => [
                    'daily_rate' => 850,
                    'pay_frequency' => 'monthly',
                    'sss_covered' => true,
                    'sss_monthly_compensation' => 20250,
                    'philhealth_covered' => true,
                    'philhealth_monthly_basic_salary' => 20000,
                    'pagibig_covered' => true,
                    'pagibig_monthly_compensation' => 20000,
                ],
                'password' => '',
            ])
            ->assertOk();

        $deleteEmployeeId = $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Staff Delete',
                'email' => 'staff.delete@example.com',
                'phone' => '09170000003',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRoleId],
                'employee_profile' => [
                    'daily_rate' => 500,
                    'pay_frequency' => 'semi_monthly',
                    'sss_covered' => false,
                    'sss_monthly_compensation' => null,
                    'philhealth_covered' => false,
                    'philhealth_monthly_basic_salary' => null,
                    'pagibig_covered' => false,
                    'pagibig_monthly_compensation' => null,
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->deleteJson("/panel/employees/{$deleteEmployeeId}")
            ->assertOk();

        BusinessProfile::current()->update([
            'payroll_government_contributions_enabled' => true,
        ]);

        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employeeId}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 4000,
                'manual_deductions' => 100,
                'notes' => 'First half payroll',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employeeId}/payrolls/{$payrollId}", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 4200,
                'manual_deductions' => 100,
                'notes' => 'Adjusted first half payroll',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employeeId}/payrolls/{$payrollId}/approve")
            ->assertOk();

        $cancelPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employeeId}/payrolls", [
                'period_start' => '2026-04-16',
                'period_end' => '2026-04-30',
                'gross_amount' => 3800,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employeeId}/payrolls/{$cancelPayrollId}/cancel")
            ->assertOk();

        $payoutId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employeeId}/payrolls/{$payrollId}/payouts", [
                'amount' => 2000,
                'method' => 'cash',
                'paid_at' => '2026-04-16 09:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $this->assertSame(
            ['created', 'updated'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_EMPLOYEE)
                ->where('subject_id', $employeeId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'deleted'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_EMPLOYEE)
                ->where('subject_id', $deleteEmployeeId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'updated', 'approved'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_PAYROLL)
                ->where('subject_id', $payrollId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $payrollSystemActivities = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_PAYROLL)
            ->where('subject_id', $payrollId)
            ->orderBy('id')
            ->get();

        $this->assertSame(1725.0, (float) $payrollSystemActivities[0]->metadata['employee_contributions_total']);
        $this->assertSame(2780.0, (float) $payrollSystemActivities[0]->metadata['employer_contributions_total']);
        $this->assertSame(1000.0, (float) data_get($payrollSystemActivities[0]->metadata, 'employee_contributions.sss.lines.regular_ss.amount'));
        $this->assertSame(30.0, (float) data_get($payrollSystemActivities[0]->metadata, 'employer_contributions.sss.lines.ec.amount'));
        $this->assertArrayHasKey('employee_contributions', $payrollSystemActivities[1]->metadata);
        $this->assertArrayHasKey('employer_contributions', $payrollSystemActivities[2]->metadata);

        $this->assertSame(
            ['created', 'cancelled'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_PAYROLL)
                ->where('subject_id', $cancelPayrollId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertDatabaseHas('system_activities', [
            'subject_type' => SystemActivity::SUBJECT_PAYOUT,
            'subject_id' => $payoutId,
            'event' => 'created',
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/system-activity/list?subject_type=payout&subject_id='.$payoutId)
            ->assertOk()
            ->assertJsonPath('events.data.0.action_url', route('panel.employees.show', $employee));
    }

    public function test_member_membership_pt_package_and_pt_session_mutations_record_system_activities(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Nia');
        $coach = $this->createUserWithRole('coach', 'Coach Rey');
        $planA = $this->createRatePlan('Monthly', 30, [
            'price' => 1500,
        ]);
        $planB = $this->createRatePlan('Quarterly', 90, [
            'price' => 3900,
        ]);
        $ptProduct = $this->createPtProduct('12 Sessions', 12, [
            'price' => 6000,
        ]);

        $this->actingAs($manager)
            ->postJson('/panel/members', [
                'name' => 'Member Cara',
                'email' => 'member.cara@example.com',
                'password' => 'password123',
                'status' => User::STATUS_ACTIVE,
                'rate_plan_id' => $planA->id,
                'start_date' => '2026-04-01',
            ])
            ->assertCreated();

        $member = User::query()->where('email', 'member.cara@example.com')->firstOrFail();

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}", [
                'name' => 'Member Cara Updated',
                'email' => 'member.cara@example.com',
                'phone' => '09170000100',
                'status' => User::STATUS_ACTIVE,
                'rate_plan_id' => $planA->id,
                'start_date' => '2026-04-01',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership", [
                'rate_plan_id' => $planB->id,
                'start_date' => '2026-04-15',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/status", [
                'status' => MemberSubscription::STATUS_PAUSED,
            ])
            ->assertOk();

        $membership = $member->fresh()->currentMembership();
        $this->assertNotNull($membership);

        $membership->update([
            'sold_price' => 3900,
        ]);

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $ptProduct->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-20',
                'notes' => 'System activity package',
            ])
            ->assertCreated();

        $package = MemberPtPackage::query()->where('user_id', $member->id)->latest('id')->firstOrFail();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-session-usages", [
                'member_pt_package_id' => $package->id,
                'coach_id' => $coach->id,
                'sessions_used' => 2,
                'used_at' => '2026-04-21 09:30:00',
                'notes' => 'Upper body block',
            ])
            ->assertCreated();

        $usage = MemberPtSessionUsage::query()->where('member_pt_package_id', $package->id)->latest('id')->firstOrFail();

        $this->assertSame(
            ['created', 'updated'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_MEMBER)
                ->where('subject_id', $member->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $membershipEvents = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION)
            ->orderBy('id')
            ->pluck('event')
            ->all();

        $this->assertSame(['created', 'plan_changed', 'status_updated'], $membershipEvents);

        $this->assertSame(
            ['assigned'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_MEMBER_PT_PACKAGE)
                ->where('subject_id', $package->id)
                ->pluck('event')
                ->all()
        );

        $packageAssignedEvent = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_MEMBER_PT_PACKAGE)
            ->where('subject_id', $package->id)
            ->where('event', 'assigned')
            ->first();

        $this->assertNotNull($packageAssignedEvent);
        $this->assertSame('2026-04-20 00:00:00', $packageAssignedEvent->occurred_at?->toDateTimeString());

        $this->assertSame(
            ['recorded'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_MEMBER_PT_SESSION_USAGE)
                ->where('subject_id', $usage->id)
                ->pluck('event')
                ->all()
        );

        $this->actingAs($manager)
            ->getJson('/panel/system-activity/list?subject_type=member_pt_session_usage&subject_id='.$usage->id)
            ->assertOk()
            ->assertJsonPath('events.data.0.action_url', route('panel.members.show', $member));
    }

    public function test_attendance_inventory_and_pricing_mutations_record_primary_system_activities(): void
    {
        $admin = $this->createUserWithRole('admin', 'Admin Zee');
        $member = $this->createUserWithRole('member', 'Member Pax');
        $category = InventoryCategory::factory()->create(['name' => 'Supplements']);
        $pricingRatePlan = $this->createRatePlan('Annual', 365, [
            'price' => null,
        ]);
        $pricingPtProduct = $this->createPtProduct('24 Sessions', 24, [
            'price' => null,
        ]);

        $attendanceId = $this->actingAs($admin)
            ->postJson('/panel/attendance', [
                'attendee_type' => Attendance::TYPE_MEMBER,
                'user_id' => $member->id,
                'checked_in_at' => '2026-04-10 08:00:00',
                'notes' => 'Morning workout',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($admin)
            ->putJson("/panel/attendance/{$attendanceId}", [
                'checked_in_at' => '2026-04-10 08:15:00',
                'checked_out_at' => null,
                'notes' => 'Updated check-in time',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/panel/attendance/{$attendanceId}/checkout")
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/panel/attendance/{$attendanceId}")
            ->assertNoContent();

        $itemId = $this->actingAs($admin)
            ->postJson('/panel/inventory', [
                'inventory_category_id' => $category->id,
                'name' => 'Resistance Band',
                'sku' => 'RB-001',
                'unit' => 'pcs',
                'quantity' => 12,
                'low_stock_threshold' => 3,
                'cost_price' => 120,
                'selling_price' => 250,
                'status' => InventoryItem::STATUS_ACTIVE,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($admin)
            ->putJson("/panel/inventory/{$itemId}", [
                'inventory_category_id' => $category->id,
                'name' => 'Resistance Band XL',
                'sku' => 'RB-001',
                'unit' => 'pcs',
                'quantity' => 10,
                'low_stock_threshold' => 2,
                'cost_price' => 130,
                'selling_price' => 260,
                'status' => InventoryItem::STATUS_ACTIVE,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/panel/inventory/{$itemId}")
            ->assertNoContent();

        $this->actingAs($admin)
            ->postJson("/panel/pricing/rate-plans/{$pricingRatePlan->id}", [
                'price' => 9999,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->putJson("/panel/pricing/rate-plans/{$pricingRatePlan->id}", [
                'price' => 10999,
                'is_active' => false,
                'effective_from' => '2026-04-01',
                'effective_until' => '2026-12-31',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/panel/pricing/rate-plans/{$pricingRatePlan->id}")
            ->assertNoContent();

        $this->actingAs($admin)
            ->postJson("/panel/pricing/pt-products/{$pricingPtProduct->id}", [
                'price' => 4800,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->putJson("/panel/pricing/pt-products/{$pricingPtProduct->id}", [
                'price' => 5200,
                'is_active' => false,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/panel/pricing/pt-products/{$pricingPtProduct->id}")
            ->assertNoContent();

        $this->assertSame(
            ['checked_in', 'updated', 'checked_out', 'deleted'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_ATTENDANCE)
                ->where('subject_id', $attendanceId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'updated', 'deleted'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_INVENTORY_ITEM)
                ->where('subject_id', $itemId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['configured', 'updated', 'removed'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_RATE_PLAN)
                ->where('subject_id', $pricingRatePlan->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['configured', 'updated', 'removed'],
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_PT_PRODUCT)
                ->where('subject_id', $pricingPtProduct->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );
    }

    public function test_sales_record_primary_and_side_effect_system_activities_with_cause_chains(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Sol');
        $category = InventoryCategory::factory()->create(['name' => 'Drinks']);
        $inventoryItem = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Sports Drink',
            'quantity' => 10,
            'selling_price' => 75,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);
        $membershipPlan = $this->createRatePlan('6 Months', 180, [
            'price' => 4999.50,
        ]);
        $member = $this->createUserWithRole('member', 'Member Mia');
        $coach = $this->createUserWithRole('coach', 'Coach Rio');
        $ptProduct = $this->createPtProduct('24 Sessions', 24, [
            'price' => 7200,
        ]);

        $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [[
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity' => 2,
                ]],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 200,
                'sold_at' => '2026-04-10 10:00:00',
            ])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_MEMBERSHIP,
                'member_id' => $member->id,
                'rate_plan_id' => $membershipPlan->id,
                'start_date' => '2026-04-11',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 5000,
                'sold_at' => '2026-04-10 11:00:00',
            ])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_PT_PACKAGE,
                'member_id' => $member->id,
                'pt_product_id' => $ptProduct->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-10',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 7200,
                'sold_at' => '2026-04-10 12:00:00',
            ])
            ->assertCreated();

        $walkInTransactionId = $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_WALK_IN,
                'customer_name' => 'Walk-in Carla',
                'customer_phone' => '09174445555',
                'amount_paid' => 350,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
                'sold_at' => '2026-04-10 13:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $this->assertSame(
            4,
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_SALE_TRANSACTION)
                ->where('event', 'created')
                ->count()
        );

        $inventoryStockEvent = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_INVENTORY_ITEM)
            ->where('event', 'stock_deducted')
            ->first();

        $this->assertNotNull($inventoryStockEvent);
        $this->assertSame(SystemActivity::SUBJECT_SALE_TRANSACTION, $inventoryStockEvent->metadata['caused_by']['subject_type'] ?? null);
        $this->assertSame('2026-04-10 10:00:00', $inventoryStockEvent->occurred_at?->toDateTimeString());
        $this->assertFalse(
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_INVENTORY_ITEM)
                ->where('subject_id', $inventoryItem->id)
                ->where('event', 'updated')
                ->exists()
        );

        $membershipCreatedEvent = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_MEMBER_SUBSCRIPTION)
            ->where('event', 'created')
            ->get()
            ->first(fn (SystemActivity $systemActivity): bool => ($systemActivity->metadata['caused_by']['subject_type'] ?? null) === SystemActivity::SUBJECT_SALE_TRANSACTION);

        $this->assertNotNull($membershipCreatedEvent);
        $this->assertSame('2026-04-10 11:00:00', $membershipCreatedEvent->occurred_at?->toDateTimeString());

        $ptPackageCreatedEvent = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_MEMBER_PT_PACKAGE)
            ->where('event', 'created')
            ->get()
            ->first(fn (SystemActivity $systemActivity): bool => ($systemActivity->metadata['caused_by']['subject_type'] ?? null) === SystemActivity::SUBJECT_SALE_TRANSACTION);

        $this->assertNotNull($ptPackageCreatedEvent);
        $this->assertSame('2026-04-10 12:00:00', $ptPackageCreatedEvent->occurred_at?->toDateTimeString());

        $this->actingAs($manager)
            ->postJson(route('panel.sales.void', $walkInTransactionId), [
                'reason' => 'Customer requested cancellation.',
            ])
            ->assertOk();

        $voidedEvent = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_SALE_TRANSACTION)
            ->where('subject_id', $walkInTransactionId)
            ->where('event', 'voided')
            ->first();

        $this->assertNotNull($voidedEvent);
        $this->assertSame('Sale voided', $voidedEvent->title);
        $this->assertSame('Customer requested cancellation.', $voidedEvent->metadata['void_reason'] ?? null);
        $this->assertSame('Manager Sol', $voidedEvent->metadata['voided_by'] ?? null);

    }

    public function test_read_only_and_failed_actions_do_not_create_extra_system_activities(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Dex');
        $staff = $this->createUserWithRole('staff', 'Staff Lee');
        $employee = $this->createUserWithRole('employee', 'Employee Kai');
        $category = InventoryCategory::factory()->create(['name' => 'Bars']);

        $this->actingAs($staff)
            ->getJson('/panel/notifications/list')
            ->assertOk();

        $this->actingAs($staff)
            ->postJson('/panel/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, SystemActivity::count());

        $this->actingAs($manager)
            ->getJson('/panel/sales/history')
            ->assertOk();

        $this->assertSame(0, SystemActivity::count());

        $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [[
                    'inventory_item_id' => 999999,
                    'quantity' => 1,
                ]],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 100,
                'sold_at' => '2026-04-10 15:00:00',
            ])
            ->assertStatus(422);

        $this->assertSame(0, SystemActivity::count());

        InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Protein Bar',
            'quantity' => 0,
            'selling_price' => 50,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);
        $activityCountBeforeFailedStockSale = SystemActivity::count();

        $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_INVENTORY,
                'items' => [[
                    'inventory_item_id' => InventoryItem::query()->value('id'),
                    'quantity' => 1,
                ]],
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 100,
                'sold_at' => '2026-04-10 15:05:00',
            ])
            ->assertStatus(422);

        $this->assertSame($activityCountBeforeFailedStockSale, SystemActivity::count());

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'gross_amount' => 1000,
            'withholding_tax' => 0,
            'manual_deductions' => 0,
            'net_amount' => 1000,
            'status' => Payroll::STATUS_DRAFT,
            'generated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$payroll->id}/approve")
            ->assertOk();

        $approvedSystemActivityCount = SystemActivity::query()
            ->where('subject_type', SystemActivity::SUBJECT_PAYROLL)
            ->where('subject_id', $payroll->id)
            ->where('event', 'approved')
            ->count();

        $this->assertSame(1, $approvedSystemActivityCount);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$payroll->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only draft payrolls can be approved.');

        $this->assertSame(
            $approvedSystemActivityCount,
            SystemActivity::query()
                ->where('subject_type', SystemActivity::SUBJECT_PAYROLL)
                ->where('subject_id', $payroll->id)
                ->where('event', 'approved')
                ->count()
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function businessSettingsPayload(array $overrides = []): array
    {
        $profile = BusinessProfile::current();

        return array_merge([
            'name' => $profile->name,
            'country_code' => $profile->country_code,
            'city' => $profile->city,
            'province' => $profile->province,
            'address' => $profile->address,
            'timezone' => $profile->timezone,
            'amenities' => $profile->amenities ?? [],
            'opening_time' => $profile->opening_time,
            'closing_time' => $profile->closing_time,
            'operating_hours' => $profile->operating_hours ?? BusinessProfile::defaultOperatingHours(),
        ], $overrides);
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
            'email' => strtolower(str_replace(' ', '.', $name)).'.'.uniqid().'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
