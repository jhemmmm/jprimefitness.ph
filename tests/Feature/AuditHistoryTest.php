<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\CashAdvance;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WalkIn;
use App\Services\AuditHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditHistoryTest extends TestCase
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

        BusinessProfile::factory()->create();
    }

    public function test_users_with_manage_employees_permission_can_view_and_filter_audit_history(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staff = $this->createUserWithRole('staff', 'Staff Sam');
        $employee = $this->createUserWithRole('employee', 'Employee Eli');

        AuditEvent::factory()->create([
            'subject_type' => AuditEvent::SUBJECT_CASH_ADVANCE,
            'subject_id' => 11,
            'subject_label' => 'Cash Advance #11 - '.$employee->name,
            'event' => 'approved',
            'title' => 'Cash advance approved',
            'message' => 'The cash advance for '.$employee->name.' amounting to ₱1,500.00 was approved.',
            'actor_name' => $manager->name,
            'metadata' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'amount' => 1500,
            ],
            'occurred_at' => '2026-04-05 09:00:00',
        ]);

        AuditEvent::factory()->create([
            'subject_type' => AuditEvent::SUBJECT_CASH_ADVANCE,
            'subject_id' => 12,
            'subject_label' => 'Cash Advance #12 - '.$employee->name,
            'event' => 'deleted',
            'title' => 'Cash advance deleted',
            'message' => 'The cash advance for '.$employee->name.' amounting to ₱800.00 was deleted.',
            'actor_name' => $manager->name,
            'metadata' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'amount' => 800,
            ],
            'occurred_at' => '2026-03-20 08:00:00',
        ]);

        $this->actingAs($manager)
            ->get('/panel/audit-history')
            ->assertOk()
            ->assertSee('audit-history-page', false);

        $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?subject_type=cash_advance&subject_id=11&event=approved&search=approved&date_from=2026-04-01&date_to=2026-04-30&per_page=10')
            ->assertOk()
            ->assertJsonPath('events.total', 1)
            ->assertJsonPath('events.data.0.subject_type', AuditEvent::SUBJECT_CASH_ADVANCE)
            ->assertJsonPath('events.data.0.subject_id', 11)
            ->assertJsonPath('events.data.0.event', 'approved')
            ->assertJsonPath('events.data.0.subject_label', 'Cash Advance #11 - '.$employee->name)
            ->assertJsonPath('events.data.0.actor_name', $manager->name)
            ->assertJsonPath('events.data.0.action_url', route('panel.employees.show', $employee));

        $this->actingAs($staff)
            ->get('/panel/audit-history')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/audit-history/list')
            ->assertForbidden();
    }

    public function test_cash_advance_lifecycle_and_delete_actions_record_shared_audit_events(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('employee', 'Employee Eli');

        $releasedCashAdvanceId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/cash-advances", [
                'amount' => 1500,
                'notes' => 'Uniform allowance',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/cash-advances/{$releasedCashAdvanceId}", [
                'status' => CashAdvance::STATUS_APPROVED,
                'notes' => 'Approved for release',
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/cash-advances/{$releasedCashAdvanceId}", [
                'status' => CashAdvance::STATUS_RELEASED,
            ])
            ->assertOk();

        $cancelledCashAdvanceId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/cash-advances", [
                'amount' => 800,
                'notes' => 'Second request',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/cash-advances/{$cancelledCashAdvanceId}", [
                'status' => CashAdvance::STATUS_CANCELLED,
                'notes' => 'Cancelled request',
            ])
            ->assertOk();

        $deletedCashAdvanceId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/cash-advances", [
                'amount' => 500,
                'notes' => 'Delete me',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->deleteJson("/panel/employees/{$employee->id}/cash-advances/{$deletedCashAdvanceId}")
            ->assertNoContent();

        $releasedEvents = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_CASH_ADVANCE)
            ->where('subject_id', $releasedCashAdvanceId)
            ->orderBy('occurred_at')
            ->pluck('event')
            ->all();

        $cancelledEvents = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_CASH_ADVANCE)
            ->where('subject_id', $cancelledCashAdvanceId)
            ->orderBy('occurred_at')
            ->pluck('event')
            ->all();

        $deletedEvents = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_CASH_ADVANCE)
            ->where('subject_id', $deletedCashAdvanceId)
            ->orderBy('occurred_at')
            ->pluck('event')
            ->all();

        $this->assertSame(['requested', 'approved', 'released'], $releasedEvents);
        $this->assertSame(['requested', 'cancelled'], $cancelledEvents);
        $this->assertSame(['requested', 'deleted'], $deletedEvents);

        $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?subject_type=cash_advance&subject_id='.$releasedCashAdvanceId.'&per_page=10')
            ->assertOk()
            ->assertJsonPath('events.total', 3)
            ->assertJsonPath('events.data.0.event', 'released')
            ->assertJsonPath('events.data.1.event', 'approved')
            ->assertJsonPath('events.data.2.event', 'requested');
    }

    public function test_audit_history_list_supports_sorting(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');

        AuditEvent::factory()->create([
            'subject_type' => AuditEvent::SUBJECT_MEMBER,
            'subject_id' => 101,
            'subject_label' => 'Member #101 - Zoe',
            'event' => 'updated',
            'title' => 'Zeta event',
            'actor_name' => 'Zoe Zebra',
            'occurred_at' => '2026-04-07 09:00:00',
        ]);

        AuditEvent::factory()->create([
            'subject_type' => AuditEvent::SUBJECT_MEMBER,
            'subject_id' => 102,
            'subject_label' => 'Member #102 - Ava',
            'event' => 'created',
            'title' => 'Alpha event',
            'actor_name' => 'Ava Alpha',
            'occurred_at' => '2026-04-05 08:00:00',
        ]);

        AuditEvent::factory()->create([
            'subject_type' => AuditEvent::SUBJECT_MEMBER,
            'subject_id' => 103,
            'subject_label' => 'Member #103 - Mia',
            'event' => 'deleted',
            'title' => 'Middle event',
            'actor_name' => 'Mia Middle',
            'occurred_at' => '2026-04-06 10:00:00',
        ]);

        $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?sort_by=actor_name&sort_direction=asc&per_page=10')
            ->assertOk()
            ->assertJsonPath('events.data.0.actor_name', 'Ava Alpha')
            ->assertJsonPath('events.data.1.actor_name', 'Mia Middle')
            ->assertJsonPath('events.data.2.actor_name', 'Zoe Zebra');

        $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?sort_by=occurred_at&sort_direction=asc&per_page=10')
            ->assertOk()
            ->assertJsonPath('events.data.0.subject_id', 102)
            ->assertJsonPath('events.data.1.subject_id', 103)
            ->assertJsonPath('events.data.2.subject_id', 101);
    }

    public function test_deleted_audit_events_expose_restore_state_for_supported_subjects(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('employee', 'Employee Eli');
        $member = $this->createUserWithRole('member', 'Member Max');
        $category = InventoryCategory::factory()->create(['name' => 'Supplements']);

        $deletedEmployee = $this->createUserWithRole('staff', 'Staff Soft Delete');
        $deletedEmployee->delete();

        $attendance = Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-04-10 08:00:00',
            'recorded_by' => $manager->id,
        ]);
        $attendance->delete();

        $walkIn = WalkIn::create([
            'served_by' => $manager->id,
            'name' => 'Walk-in Gwen',
            'amount_paid' => 250,
            'payment_method' => 'cash',
            'visited_at' => '2026-04-10 09:00:00',
        ]);
        $walkIn->delete();

        $inventoryItem = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Protein Shake',
        ]);
        $inventoryItem->delete();

        $cashAdvance = CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => 1500,
            'remaining_amount' => 1500,
            'status' => CashAdvance::STATUS_RELEASED,
            'requested_at' => '2026-04-10 10:00:00',
            'released_at' => '2026-04-10 11:00:00',
            'released_by' => $manager->id,
        ]);
        $cashAdvance->delete();

        $this->makeAuditEvent(AuditEvent::SUBJECT_EMPLOYEE, $deletedEmployee->id, 'deleted', 'Employee #'.$deletedEmployee->id.' - '.$deletedEmployee->name, occurredAt: '2026-04-10 13:00:00');
        $this->makeAuditEvent(AuditEvent::SUBJECT_ATTENDANCE, $attendance->id, 'deleted', 'Attendance #'.$attendance->id.' - '.$member->name, occurredAt: '2026-04-10 13:05:00');
        $this->makeAuditEvent(AuditEvent::SUBJECT_WALK_IN, $walkIn->id, 'deleted', 'Walk-in #'.$walkIn->id.' - '.$walkIn->name, occurredAt: '2026-04-10 13:10:00');
        $this->makeAuditEvent(AuditEvent::SUBJECT_INVENTORY_ITEM, $inventoryItem->id, 'deleted', 'Inventory Item #'.$inventoryItem->id.' - '.$inventoryItem->name, occurredAt: '2026-04-10 13:15:00');
        $this->makeAuditEvent(AuditEvent::SUBJECT_CASH_ADVANCE, $cashAdvance->id, 'deleted', 'Cash Advance #'.$cashAdvance->id.' - '.$employee->name, [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
        ], '2026-04-10 13:20:00');

        $events = collect($this->actingAs($manager)
            ->getJson('/panel/audit-history/list?per_page=20')
            ->assertOk()
            ->json('events.data'));

        foreach ([
            [AuditEvent::SUBJECT_EMPLOYEE, $deletedEmployee->id],
            [AuditEvent::SUBJECT_ATTENDANCE, $attendance->id],
            [AuditEvent::SUBJECT_WALK_IN, $walkIn->id],
            [AuditEvent::SUBJECT_INVENTORY_ITEM, $inventoryItem->id],
            [AuditEvent::SUBJECT_CASH_ADVANCE, $cashAdvance->id],
        ] as [$subjectType, $subjectId]) {
            $event = $this->findSerializedEvent($events->all(), $subjectType, $subjectId, 'deleted');

            $this->assertNotNull($event);
            $this->assertTrue((bool) $event['restore']['available']);
            $this->assertSame('Restore', $event['restore']['label']);
            $this->assertNull($event['restore']['reason']);
        }
    }

    public function test_audit_history_marks_non_restorable_deleted_events_and_non_deleted_events(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $activeEmployee = $this->createUserWithRole('staff', 'Staff Active');

        $missingDeletedEvent = $this->makeAuditEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            999999,
            'deleted',
            'Employee #999999 - Missing Person',
            occurredAt: '2026-04-10 08:00:00',
        );

        $updatedEvent = $this->makeAuditEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $activeEmployee->id,
            'updated',
            'Employee #'.$activeEmployee->id.' - '.$activeEmployee->name,
            occurredAt: '2026-04-10 09:10:00',
        );

        $events = collect($this->actingAs($manager)
            ->getJson('/panel/audit-history/list?per_page=20')
            ->assertOk()
            ->json('events.data'));

        $missingEvent = $this->findSerializedEvent($events->all(), $missingDeletedEvent->subject_type, $missingDeletedEvent->subject_id, 'deleted');
        $this->assertNotNull($missingEvent);
        $this->assertFalse((bool) $missingEvent['restore']['available']);
        $this->assertStringContainsString('no longer exists', $missingEvent['restore']['reason']);

        $nonDeletedEvent = $this->findSerializedEvent($events->all(), $updatedEvent->subject_type, $updatedEvent->subject_id, 'updated');
        $this->assertNotNull($nonDeletedEvent);
        $this->assertFalse((bool) $nonDeletedEvent['restore']['available']);
        $this->assertNull($nonDeletedEvent['restore']['label']);
        $this->assertNull($nonDeletedEvent['restore']['reason']);
    }

    public function test_restore_endpoint_restores_supported_deleted_subjects(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $member = $this->createUserWithRole('member', 'Member Max');
        $inventoryCategory = InventoryCategory::factory()->create(['name' => 'Gear']);

        $employee = $this->createUserWithRole('staff', 'Staff Soft Delete');
        $employee->delete();
        $employeeEvent = $this->makeAuditEvent(AuditEvent::SUBJECT_EMPLOYEE, $employee->id, 'deleted', 'Employee #'.$employee->id.' - '.$employee->name);

        $attendance = Attendance::create([
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'name' => $member->name,
            'checked_in_at' => '2026-04-10 08:00:00',
            'recorded_by' => $manager->id,
        ]);
        $attendance->delete();
        $attendanceEvent = $this->makeAuditEvent(AuditEvent::SUBJECT_ATTENDANCE, $attendance->id, 'deleted', 'Attendance #'.$attendance->id.' - '.$member->name);

        $inventoryItem = InventoryItem::factory()->create([
            'inventory_category_id' => $inventoryCategory->id,
            'name' => 'Jump Rope',
        ]);
        $inventoryItem->delete();
        $inventoryEvent = $this->makeAuditEvent(AuditEvent::SUBJECT_INVENTORY_ITEM, $inventoryItem->id, 'deleted', 'Inventory Item #'.$inventoryItem->id.' - '.$inventoryItem->name);

        foreach ([$employeeEvent, $attendanceEvent, $inventoryEvent] as $auditEvent) {
            $this->actingAs($manager)
                ->postJson("/panel/audit-history/{$auditEvent->id}/restore")
                ->assertOk()
                ->assertJsonPath('message', 'Record restored successfully.');
        }

        $this->assertNull(User::withTrashed()->findOrFail($employee->id)->deleted_at);
        $this->assertNull(Attendance::withTrashed()->findOrFail($attendance->id)->deleted_at);
        $this->assertNull(InventoryItem::withTrashed()->findOrFail($inventoryItem->id)->deleted_at);

        $this->assertSame('restored', AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_EMPLOYEE)
            ->where('subject_id', $employee->id)
            ->latest('id')
            ->value('event'));
    }

    public function test_restore_endpoint_restores_walk_ins(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');

        $walkIn = WalkIn::create([
            'served_by' => $manager->id,
            'name' => 'Walk-in Gwen',
            'amount_paid' => 250,
            'payment_method' => 'cash',
            'visited_at' => '2026-04-10 09:00:00',
        ]);

        $walkIn->delete();

        $auditEvent = $this->makeAuditEvent(AuditEvent::SUBJECT_WALK_IN, $walkIn->id, 'deleted', 'Walk-in #'.$walkIn->id.' - '.$walkIn->name);

        $this->actingAs($manager)
            ->postJson("/panel/audit-history/{$auditEvent->id}/restore")
            ->assertOk();

        $this->assertNull(WalkIn::withTrashed()->findOrFail($walkIn->id)->deleted_at);
    }

    public function test_restore_endpoint_restores_cash_advances(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('employee', 'Employee Eli');

        $cashAdvance = CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => 1500,
            'remaining_amount' => 750,
            'status' => CashAdvance::STATUS_RELEASED,
            'requested_at' => '2026-04-10 08:00:00',
            'released_at' => '2026-04-10 09:00:00',
            'released_by' => $manager->id,
        ]);

        $cashAdvance->delete();

        $auditEvent = $this->makeAuditEvent(
            AuditEvent::SUBJECT_CASH_ADVANCE,
            $cashAdvance->id,
            'deleted',
            'Cash Advance #'.$cashAdvance->id.' - '.$employee->name,
            [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
            ],
        );

        $this->actingAs($manager)
            ->postJson("/panel/audit-history/{$auditEvent->id}/restore")
            ->assertOk();

        $this->assertNull(CashAdvance::withTrashed()->findOrFail($cashAdvance->id)->deleted_at);
    }

    public function test_restore_endpoint_rejects_non_restorable_events(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Staff Active');

        $updatedEvent = $this->makeAuditEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'updated',
            'Employee #'.$employee->id.' - '.$employee->name,
        );

        $alreadyActiveDeletedEvent = $this->makeAuditEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'deleted',
            'Employee #'.$employee->id.' - '.$employee->name,
        );

        $missingDeletedEvent = $this->makeAuditEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            999999,
            'deleted',
            'Employee #999999 - Missing Person',
        );

        foreach ([$updatedEvent, $alreadyActiveDeletedEvent, $missingDeletedEvent] as $auditEvent) {
            $this->actingAs($manager)
                ->postJson("/panel/audit-history/{$auditEvent->id}/restore")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['restore']);
        }
    }

    public function test_deleted_employee_audit_links_remain_openable(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Staff Soft Delete');
        $employee->delete();

        $this->makeAuditEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'deleted',
            'Employee #'.$employee->id.' - '.$employee->name,
            occurredAt: '2026-04-10 11:00:00',
        );

        $response = $this->actingAs($manager)
            ->getJson('/panel/audit-history/list?subject_type=employee&subject_id='.$employee->id.'&per_page=10')
            ->assertOk();

        $actionUrl = $response->json('events.data.0.action_url');

        $this->assertSame(route('panel.employees.show', $employee->id), $actionUrl);

        $this->actingAs($manager)
            ->get($actionUrl)
            ->assertOk()
            ->assertSee('employee-detail-page', false)
            ->assertSeeText($employee->name);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/attendance")
            ->assertOk();

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/cash-advances")
            ->assertOk();
    }

    public function test_walk_in_audit_subject_labels_are_trimmed_to_fit_the_column(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $longName = str_repeat('W', 255);

        $walkInId = $this->actingAs($manager)
            ->postJson('/panel/walk-ins', [
                'name' => $longName,
                'amount_paid' => 250,
                'payment_method' => 'cash',
                'visited_at' => '2026-04-10 09:00:00',
            ])
            ->assertCreated()
            ->json('id');

        $auditEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_WALK_IN)
            ->where('subject_id', $walkInId)
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertLessThanOrEqual(255, strlen($auditEvent->subject_label ?? ''));
        $this->assertStringStartsWith('Walk-in #'.$walkInId.' - ', (string) $auditEvent->subject_label);
    }

    public function test_payroll_deductions_record_partially_paid_and_paid_audit_events(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Pia');
        $coach = $this->createUserWithRole('coach', 'Coach Lou');

        $cashAdvanceId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/cash-advances", [
                'amount' => 1500,
                'notes' => 'Payroll-backed advance',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$coach->id}/cash-advances/{$cashAdvanceId}", [
                'status' => CashAdvance::STATUS_APPROVED,
            ])
            ->assertOk();

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$coach->id}/cash-advances/{$cashAdvanceId}", [
                'status' => CashAdvance::STATUS_RELEASED,
            ])
            ->assertOk();

        $firstPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-04-01',
                'period_end' => '2026-04-15',
                'gross_amount' => 1000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 500,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$firstPayrollId}/approve")
            ->assertOk();

        $secondPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls", [
                'period_start' => '2026-04-16',
                'period_end' => '2026-04-30',
                'gross_amount' => 1200,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 1000,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$coach->id}/payrolls/{$secondPayrollId}/approve")
            ->assertOk();

        $events = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_CASH_ADVANCE)
            ->where('subject_id', $cashAdvanceId)
            ->orderBy('occurred_at')
            ->get();

        $this->assertSame(['requested', 'approved', 'released', 'partially_paid', 'paid'], $events->pluck('event')->all());

        $partiallyPaidEvent = $events->firstWhere('event', 'partially_paid');
        $paidEvent = $events->firstWhere('event', 'paid');

        $this->assertNotNull($partiallyPaidEvent);
        $this->assertNotNull($paidEvent);
        $this->assertSame($firstPayrollId, $partiallyPaidEvent->metadata['source_id']);
        $this->assertSame($secondPayrollId, $paidEvent->metadata['source_id']);
        $this->assertSame(500.0, (float) $partiallyPaidEvent->metadata['deducted_amount']);
        $this->assertSame(1000.0, (float) $paidEvent->metadata['deducted_amount']);
    }

    public function test_backfill_service_normalizes_legacy_cash_advance_history_without_duplicate_lifecycle_events(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('employee', 'Employee Eli');

        Schema::table('cash_advances', function (Blueprint $table) {
            $table->json('audit_data')->nullable()->after('paid_at');
        });

        DB::table('cash_advances')->insert([
            'id' => 1,
            'employee_id' => $employee->id,
            'amount' => 500,
            'remaining_amount' => 200,
            'status' => CashAdvance::STATUS_PARTIALLY_PAID,
            'notes' => 'Legacy allowance',
            'requested_at' => '2026-04-01 09:00:00',
            'approved_at' => '2026-04-01 10:00:00',
            'approved_by' => $manager->id,
            'released_at' => '2026-04-02 08:00:00',
            'released_by' => $manager->id,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
            'paid_at' => null,
            'audit_data' => json_encode([
                [
                    'event' => 'requested',
                    'at' => '2026-04-01T09:00:00+08:00',
                    'by_user_id' => $employee->id,
                    'by_name' => $employee->name,
                    'source' => 'panel',
                    'notes' => 'Legacy allowance',
                ],
                [
                    'event' => 'approved',
                    'at' => '2026-04-01T10:00:00+08:00',
                    'by_user_id' => $manager->id,
                    'by_name' => $manager->name,
                    'source' => 'panel',
                    'notes' => 'Legacy approval',
                ],
                [
                    'event' => 'partially_paid',
                    'at' => '2026-04-03T12:00:00+08:00',
                    'by_user_id' => $manager->id,
                    'by_name' => $manager->name,
                    'source' => 'payroll',
                    'source_id' => 77,
                    'deducted_amount' => 300,
                    'remaining_before' => 500,
                    'remaining_after' => 200,
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => '2026-04-01 09:00:00',
            'updated_at' => '2026-04-03 12:00:00',
        ]);

        app(AuditHistoryService::class)->backfillCashAdvanceEvents();

        $events = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_CASH_ADVANCE)
            ->where('subject_id', 1)
            ->orderBy('occurred_at')
            ->get();

        $this->assertCount(4, $events);
        $this->assertSame(['requested', 'approved', 'released', 'partially_paid'], $events->pluck('event')->all());
        $this->assertSame('Legacy allowance', $events[0]->metadata['notes']);
        $this->assertSame(77, $events[3]->metadata['source_id']);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function makeAuditEvent(
        string $subjectType,
        int $subjectId,
        string $event,
        string $subjectLabel,
        array $metadata = [],
        ?string $occurredAt = null,
    ): AuditEvent {
        return AuditEvent::factory()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'event' => $event,
            'title' => ucfirst(str_replace('_', ' ', $event)).' event',
            'message' => $subjectLabel.' '.$event.'.',
            'metadata' => $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>|null
     */
    private function findSerializedEvent(array $events, string $subjectType, int $subjectId, string $event): ?array
    {
        foreach ($events as $serializedEvent) {
            if (($serializedEvent['subject_type'] ?? null) === $subjectType
                && (int) ($serializedEvent['subject_id'] ?? 0) === $subjectId
                && ($serializedEvent['event'] ?? null) === $event) {
                return $serializedEvent;
            }
        }

        return null;
    }
}
