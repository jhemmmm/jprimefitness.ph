<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\CashLedgerEntry;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditHistoryExpansionTest extends TestCase
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

    public function test_super_admin_admin_and_manager_can_access_audit_history_with_registry_metadata(): void
    {
        $superAdmin = $this->createUserWithRole('super admin', 'Super Admin Sue');
        $admin = $this->createUserWithRole('admin', 'Admin Ava');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        $member = $this->createUserWithRole('member', 'Member Max');

        AuditEvent::factory()->create([
            'subject_type' => AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION,
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
                    'subject_type' => AuditEvent::SUBJECT_SALE_TRANSACTION,
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
                ->get('/panel/audit-history')
                ->assertOk()
                ->assertSee('audit-history-page', false);
        }

        $response = $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?subject_type=member_subscription&event=created&per_page=10')
            ->assertOk();

        $this->assertSame([
            AuditEvent::SUBJECT_BUSINESS_PROFILE,
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY,
            AuditEvent::SUBJECT_EMPLOYEE,
            AuditEvent::SUBJECT_PAYROLL,
            AuditEvent::SUBJECT_PAYOUT,
            AuditEvent::SUBJECT_CASH_ADVANCE,
            AuditEvent::SUBJECT_MEMBER,
            AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION,
            AuditEvent::SUBJECT_MEMBER_PT_PACKAGE,
            AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE,
            AuditEvent::SUBJECT_ATTENDANCE,
            AuditEvent::SUBJECT_WALK_IN,
            AuditEvent::SUBJECT_SALE_TRANSACTION,
            AuditEvent::SUBJECT_INVENTORY_ITEM,
            AuditEvent::SUBJECT_RATE_PLAN,
            AuditEvent::SUBJECT_PT_PRODUCT,
        ], collect($response->json('meta.subject_types'))->pluck('value')->all());

        $this->assertContains('stock_deducted', collect($response->json('meta.event_options'))->pluck('value')->all());

        $response
            ->assertJsonPath('events.total', 1)
            ->assertJsonPath('events.data.0.subject_type', AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION)
            ->assertJsonPath('events.data.0.subject_type_label', 'Memberships')
            ->assertJsonPath('events.data.0.action_url', route('panel.members.show', $member))
            ->assertJsonPath('events.data.0.caused_by.subject_type', AuditEvent::SUBJECT_SALE_TRANSACTION)
            ->assertJsonPath('events.data.0.caused_by.event_label', 'Created')
            ->assertJsonPath('events.data.0.caused_by.action_url', route('panel.sales.index'));

        $this->actingAs($staff)
            ->get('/panel/audit-history')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/audit-history/list')
            ->assertForbidden();
    }

    public function test_business_profile_photo_and_manual_cash_ledger_changes_record_audit_events(): void
    {
        Storage::fake('public');

        $admin = $this->createUserWithRole('admin', 'Admin Bea');

        $this->actingAs($admin)
            ->putJson('/panel/business/settings', $this->businessSettingsPayload([
                'name' => 'JPrime Fitness Audit Hub',
                'city' => 'Naga City',
            ]))
            ->assertOk()
            ->assertJsonPath('name', 'JPrime Fitness Audit Hub');

        $this->actingAs($admin)
            ->post('/panel/business/photos', [
                'photo' => UploadedFile::fake()->image('lobby.jpg'),
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->deleteJson('/panel/business/photos/0')
            ->assertNoContent();

        $entryId = $this->actingAs($admin)
            ->postJson('/panel/business/cash-ledger', [
                'direction' => CashLedgerEntry::DIRECTION_IN,
                'amount' => 2500,
                'occurred_at' => '2026-04-09 08:00:00',
                'title' => 'Opening Float',
                'description' => 'Front desk cash drawer',
            ])
            ->assertCreated()
            ->json('entry.id');

        $this->actingAs($admin)
            ->putJson("/panel/business/cash-ledger/{$entryId}", [
                'direction' => CashLedgerEntry::DIRECTION_OUT,
                'amount' => 350,
                'occurred_at' => '2026-04-09 09:00:00',
                'title' => 'Utility Bill',
                'description' => 'Internet payment',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/panel/business/cash-ledger/{$entryId}")
            ->assertNoContent();

        $this->assertSame(
            ['updated', 'photo_added', 'photo_removed'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_BUSINESS_PROFILE)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'updated', 'deleted'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_CASH_LEDGER_ENTRY)
                ->where('subject_id', $entryId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );
    }

    public function test_employee_payroll_payout_and_cash_advance_side_effects_record_audit_events(): void
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
                'daily_rate' => 800,
                'pay_frequency' => 'semi_monthly',
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
                'daily_rate' => 850,
                'pay_frequency' => 'monthly',
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
                'daily_rate' => 500,
                'pay_frequency' => 'semi_monthly',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->deleteJson("/panel/employees/{$deleteEmployeeId}")
            ->assertOk();

        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employeeId}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 4000,
                'bonus' => 200,
                'manual_deductions' => 100,
                'cash_advance_deduction' => 0,
                'notes' => 'First half payroll',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employeeId}/payrolls/{$payrollId}", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 4200,
                'bonus' => 250,
                'manual_deductions' => 100,
                'cash_advance_deduction' => 0,
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
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
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
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_EMPLOYEE)
                ->where('subject_id', $employeeId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'deleted'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_EMPLOYEE)
                ->where('subject_id', $deleteEmployeeId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'updated', 'approved'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_PAYROLL)
                ->where('subject_id', $payrollId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'cancelled'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_PAYROLL)
                ->where('subject_id', $cancelPayrollId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertDatabaseHas('audit_events', [
            'subject_type' => AuditEvent::SUBJECT_PAYOUT,
            'subject_id' => $payoutId,
            'event' => 'created',
        ]);

        $payoutLedgerEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_CASH_LEDGER_ENTRY)
            ->where('event', 'created')
            ->get()
            ->first(fn (AuditEvent $auditEvent): bool => ($auditEvent->metadata['entry_type'] ?? null) === CashLedgerEntry::TYPE_PAYROLL_PAYOUT
                && (int) ($auditEvent->metadata['source_id'] ?? 0) === $payoutId);

        $this->assertNotNull($payoutLedgerEvent);
        $this->assertSame(AuditEvent::SUBJECT_PAYOUT, $payoutLedgerEvent->metadata['caused_by']['subject_type'] ?? null);

        $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?subject_type=payout&subject_id='.$payoutId)
            ->assertOk()
            ->assertJsonPath('events.data.0.action_url', route('panel.employees.show', $employee));
    }

    public function test_member_membership_pt_package_and_pt_session_mutations_record_audit_events(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Nia');
        $coach = $this->createUserWithRole('coach', 'Coach Rey');
        $planA = $this->createRatePlan('Monthly', 30, [
            'price' => 1500,
            'manager_commission_rate' => 8,
        ]);
        $planB = $this->createRatePlan('Quarterly', 90, [
            'price' => 3900,
            'manager_commission_rate' => 10,
        ]);
        $ptProduct = $this->createPtProduct('12 Sessions', 12, [
            'price' => 6000,
            'coach_commission_rate' => 40,
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
            'manager_commission_rate' => 10,
            'manager_commission_amount' => 390,
            'manager_commission_status' => MemberSubscription::COMMISSION_STATUS_UNASSIGNED,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/members/{$member->id}/membership/manager", [
                'manager_id' => $manager->id,
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/panel/members/{$member->id}/pt-packages", [
                'pt_product_id' => $ptProduct->id,
                'coach_id' => $coach->id,
                'assigned_at' => '2026-04-20',
                'notes' => 'Audit trail package',
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
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_MEMBER)
                ->where('subject_id', $member->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $membershipEvents = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION)
            ->orderBy('id')
            ->pluck('event')
            ->all();

        $this->assertSame(['created', 'plan_changed', 'status_updated', 'manager_assigned'], $membershipEvents);

        $this->assertSame(
            ['assigned'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_MEMBER_PT_PACKAGE)
                ->where('subject_id', $package->id)
                ->pluck('event')
                ->all()
        );

        $packageAssignedEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_MEMBER_PT_PACKAGE)
            ->where('subject_id', $package->id)
            ->where('event', 'assigned')
            ->first();

        $this->assertNotNull($packageAssignedEvent);
        $this->assertSame('2026-04-20 00:00:00', $packageAssignedEvent->occurred_at?->toDateTimeString());

        $this->assertSame(
            ['recorded'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_MEMBER_PT_SESSION_USAGE)
                ->where('subject_id', $usage->id)
                ->pluck('event')
                ->all()
        );

        $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?subject_type=member_pt_session_usage&subject_id='.$usage->id)
            ->assertOk()
            ->assertJsonPath('events.data.0.action_url', route('panel.members.show', $member));
    }

    public function test_attendance_walk_in_inventory_and_pricing_mutations_record_primary_audit_events(): void
    {
        $admin = $this->createUserWithRole('admin', 'Admin Zee');
        $member = $this->createUserWithRole('member', 'Member Pax');
        $category = InventoryCategory::factory()->create(['name' => 'Supplements']);
        $ratePlan = $this->createRatePlan('Walk-in Plus', 1);
        $pricingRatePlan = $this->createRatePlan('Annual', 365, [
            'price' => null,
            'manager_commission_rate' => null,
        ]);
        $pricingPtProduct = $this->createPtProduct('24 Sessions', 24, [
            'price' => null,
            'coach_commission_rate' => null,
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

        $walkInId = $this->actingAs($admin)
            ->postJson('/panel/walk-ins', [
                'rate_plan_id' => $ratePlan->id,
                'name' => 'Walk-in Gwen',
                'phone' => '09171112222',
                'amount_paid' => 250,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'visited_at' => '2026-04-10 09:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($admin)
            ->putJson("/panel/walk-ins/{$walkInId}", [
                'rate_plan_id' => $ratePlan->id,
                'name' => 'Walk-in Gwen Updated',
                'phone' => '09171112222',
                'amount_paid' => 300,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'visited_at' => '2026-04-10 09:30:00',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/panel/walk-ins/{$walkInId}")
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
                'manager_commission_rate' => 12,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->putJson("/panel/pricing/rate-plans/{$pricingRatePlan->id}", [
                'price' => 10999,
                'manager_commission_rate' => 14,
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
                'coach_commission_rate' => 40,
                'is_active' => true,
                'effective_from' => '2026-04-01',
                'effective_until' => null,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->putJson("/panel/pricing/pt-products/{$pricingPtProduct->id}", [
                'price' => 5200,
                'coach_commission_rate' => 45,
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
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_ATTENDANCE)
                ->where('subject_id', $attendanceId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['created', 'updated', 'deleted'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_WALK_IN)
                ->where('subject_id', $walkInId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $walkInLedgerEvents = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_CASH_LEDGER_ENTRY)
            ->orderBy('id')
            ->get()
            ->filter(fn (AuditEvent $auditEvent): bool => ($auditEvent->metadata['caused_by']['subject_type'] ?? null) === AuditEvent::SUBJECT_WALK_IN)
            ->pluck('event')
            ->values()
            ->all();

        $this->assertSame(['created', 'updated', 'deleted'], $walkInLedgerEvents);

        $this->assertSame(
            ['created', 'updated', 'deleted'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_INVENTORY_ITEM)
                ->where('subject_id', $itemId)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['configured', 'updated', 'removed'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_RATE_PLAN)
                ->where('subject_id', $pricingRatePlan->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );

        $this->assertSame(
            ['configured', 'updated', 'removed'],
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_PT_PRODUCT)
                ->where('subject_id', $pricingPtProduct->id)
                ->orderBy('id')
                ->pluck('event')
                ->all()
        );
    }

    public function test_sales_record_primary_and_side_effect_audit_events_with_cause_chains(): void
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
            'manager_commission_rate' => 12,
        ]);
        $ptProduct = $this->createPtProduct('24 Sessions', 24, [
            'price' => 7200,
            'coach_commission_rate' => 40,
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
                'member_mode' => 'new',
                'customer_name' => 'New Member Mia',
                'customer_email' => 'mia.audit@example.com',
                'customer_phone' => '09173334444',
                'rate_plan_id' => $membershipPlan->id,
                'start_date' => '2026-04-11',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 5000,
                'sold_at' => '2026-04-10 11:00:00',
            ])
            ->assertCreated();

        $member = User::query()->where('email', 'mia.audit@example.com')->firstOrFail();

        $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_PT_PACKAGE,
                'member_mode' => 'existing',
                'member_id' => $member->id,
                'pt_product_id' => $ptProduct->id,
                'assigned_at' => '2026-04-10',
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 7200,
                'sold_at' => '2026-04-10 12:00:00',
            ])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson('/panel/sales', [
                'type' => SaleTransaction::TYPE_WALK_IN,
                'customer_name' => 'Walk-in Carla',
                'customer_phone' => '09174445555',
                'amount_paid' => 350,
                'payment_method' => SaleTransaction::PAYMENT_METHOD_CASH,
                'amount_received' => 500,
                'sold_at' => '2026-04-10 13:00:00',
            ])
            ->assertCreated();

        $this->assertSame(
            4,
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_SALE_TRANSACTION)
                ->where('event', 'created')
                ->count()
        );

        $inventoryStockEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_INVENTORY_ITEM)
            ->where('event', 'stock_deducted')
            ->first();

        $this->assertNotNull($inventoryStockEvent);
        $this->assertSame(AuditEvent::SUBJECT_SALE_TRANSACTION, $inventoryStockEvent->metadata['caused_by']['subject_type'] ?? null);
        $this->assertSame('2026-04-10 10:00:00', $inventoryStockEvent->occurred_at?->toDateTimeString());

        $memberCreatedEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_MEMBER)
            ->where('event', 'created')
            ->get()
            ->first(fn (AuditEvent $auditEvent): bool => ($auditEvent->metadata['caused_by']['subject_type'] ?? null) === AuditEvent::SUBJECT_SALE_TRANSACTION);

        $this->assertNotNull($memberCreatedEvent);
        $this->assertSame($member->id, $memberCreatedEvent->subject_id);
        $this->assertSame('2026-04-10 11:00:00', $memberCreatedEvent->occurred_at?->toDateTimeString());

        $membershipCreatedEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_MEMBER_SUBSCRIPTION)
            ->where('event', 'created')
            ->get()
            ->first(fn (AuditEvent $auditEvent): bool => ($auditEvent->metadata['caused_by']['subject_type'] ?? null) === AuditEvent::SUBJECT_SALE_TRANSACTION);

        $this->assertNotNull($membershipCreatedEvent);
        $this->assertSame('2026-04-10 11:00:00', $membershipCreatedEvent->occurred_at?->toDateTimeString());

        $ptPackageCreatedEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_MEMBER_PT_PACKAGE)
            ->where('event', 'created')
            ->get()
            ->first(fn (AuditEvent $auditEvent): bool => ($auditEvent->metadata['caused_by']['subject_type'] ?? null) === AuditEvent::SUBJECT_SALE_TRANSACTION);

        $this->assertNotNull($ptPackageCreatedEvent);
        $this->assertSame('2026-04-10 12:00:00', $ptPackageCreatedEvent->occurred_at?->toDateTimeString());

        $this->assertSame(
            4,
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_CASH_LEDGER_ENTRY)
                ->where('event', 'created')
                ->get()
                ->filter(fn (AuditEvent $auditEvent): bool => in_array(
                    $auditEvent->metadata['entry_type'] ?? null,
                    [
                        CashLedgerEntry::TYPE_INVENTORY_SALE,
                        CashLedgerEntry::TYPE_MEMBERSHIP_SALE,
                        CashLedgerEntry::TYPE_PT_PACKAGE_SALE,
                        CashLedgerEntry::TYPE_WALK_IN_SALE,
                    ],
                    true,
                ))
                ->count()
        );

        $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?subject_type=cash_ledger_entry&search=Membership sale')
            ->assertOk()
            ->assertJsonPath('events.data.0.caused_by.subject_type', AuditEvent::SUBJECT_SALE_TRANSACTION)
            ->assertJsonPath('events.data.0.caused_by.action_url', route('panel.sales.index'));
    }

    public function test_read_only_and_failed_actions_do_not_create_extra_audit_events(): void
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

        $this->assertSame(0, AuditEvent::count());

        $this->actingAs($manager)
            ->getJson('/panel/sales/history')
            ->assertOk();

        $this->assertSame(0, AuditEvent::count());

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

        $this->assertSame(0, AuditEvent::count());

        InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Protein Bar',
            'quantity' => 0,
            'selling_price' => 50,
            'status' => InventoryItem::STATUS_ACTIVE,
        ]);

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

        $this->assertSame(0, AuditEvent::count());

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-15',
            'gross_amount' => 1000,
            'bonus' => 0,
            'income_tax' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'membership_commission_amount' => 0,
            'membership_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 1000,
            'status' => Payroll::STATUS_DRAFT,
            'generated_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$payroll->id}/approve")
            ->assertOk();

        $approvedAuditCount = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_PAYROLL)
            ->where('subject_id', $payroll->id)
            ->where('event', 'approved')
            ->count();

        $this->assertSame(1, $approvedAuditCount);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$payroll->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only draft payrolls can be approved.');

        $this->assertSame(
            $approvedAuditCount,
            AuditEvent::query()
                ->where('subject_type', AuditEvent::SUBJECT_PAYROLL)
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
            'status' => $profile->status,
            'country_code' => $profile->country_code,
            'city' => $profile->city,
            'province' => $profile->province,
            'address' => $profile->address,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'timezone' => $profile->timezone,
            'amenities' => $profile->amenities ?? [],
            'opening_time' => $profile->opening_time,
            'closing_time' => $profile->closing_time,
            'facebook_url' => $profile->facebook_url,
            'messenger_url' => $profile->messenger_url,
            'whatsapp_url' => $profile->whatsapp_url,
            'map_url' => $profile->map_url,
            'hero_badge' => $profile->hero_badge,
            'hero_title' => $profile->hero_title,
            'hero_highlight' => $profile->hero_highlight,
            'hero_description' => $profile->hero_description,
            'about_heading' => $profile->about_heading,
            'about_description' => $profile->about_description,
            'membership_note' => $profile->membership_note,
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
