<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\AuditHistoryService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SystemActivityTest extends TestCase
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
            'subject_type' => AuditEvent::SUBJECT_PAYROLL,
            'subject_id' => 11,
            'subject_label' => 'Payroll #11 - '.$employee->name,
            'event' => 'approved',
            'title' => 'Payroll approved',
            'message' => 'The payroll for '.$employee->name.' was approved.',
            'actor_name' => $manager->name,
            'metadata' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
            ],
            'occurred_at' => '2026-04-05 09:00:00',
        ]);

        AuditEvent::factory()->create([
            'subject_type' => AuditEvent::SUBJECT_PAYROLL,
            'subject_id' => 12,
            'subject_label' => 'Payroll #12 - '.$employee->name,
            'event' => 'created',
            'title' => 'Payroll created',
            'message' => 'The payroll for '.$employee->name.' was created.',
            'actor_name' => $manager->name,
            'metadata' => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
            ],
            'occurred_at' => '2026-03-20 08:00:00',
        ]);

        $this->actingAs($manager)
            ->get('/panel/system-activity')
            ->assertOk()
            ->assertSee('system-activity-page', false);

        $this->actingAs($manager)
            ->getJson('/panel/system-activity/list?subject_type=payroll&subject_id=11&event=approved&search=approved&date_from=2026-04-01&date_to=2026-04-30&per_page=10')
            ->assertOk()
            ->assertJsonPath('events.total', 1)
            ->assertJsonPath('events.data.0.subject_type', AuditEvent::SUBJECT_PAYROLL)
            ->assertJsonPath('events.data.0.subject_id', 11)
            ->assertJsonPath('events.data.0.event', 'approved')
            ->assertJsonPath('events.data.0.subject_label', 'Payroll #11 - '.$employee->name)
            ->assertJsonPath('events.data.0.actor_name', $manager->name)
            ->assertJsonPath('events.data.0.action_url', route('panel.employees.show', $employee));

        $this->actingAs($staff)
            ->get('/panel/system-activity')
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/panel/system-activity/list')
            ->assertForbidden();
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
            ->getJson('/panel/system-activity/list?sort_by=actor_name&sort_direction=asc&per_page=10')
            ->assertOk()
            ->assertJsonPath('events.data.0.actor_name', 'Ava Alpha')
            ->assertJsonPath('events.data.1.actor_name', 'Mia Middle')
            ->assertJsonPath('events.data.2.actor_name', 'Zoe Zebra');

        $this->actingAs($manager)
            ->getJson('/panel/system-activity/list?sort_by=occurred_at&sort_direction=asc&per_page=10')
            ->assertOk()
            ->assertJsonPath('events.data.0.subject_id', 102)
            ->assertJsonPath('events.data.1.subject_id', 103)
            ->assertJsonPath('events.data.2.subject_id', 101);
    }

    public function test_deleted_audit_events_expose_restore_state_for_supported_subjects(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
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

        $inventoryItem = InventoryItem::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Protein Shake',
        ]);
        $inventoryItem->delete();

        $this->makeAuditEvent(AuditEvent::SUBJECT_EMPLOYEE, $deletedEmployee->id, 'deleted', 'Employee #'.$deletedEmployee->id.' - '.$deletedEmployee->name, occurredAt: '2026-04-10 13:00:00');
        $this->makeAuditEvent(AuditEvent::SUBJECT_ATTENDANCE, $attendance->id, 'deleted', 'Attendance #'.$attendance->id.' - '.$member->name, occurredAt: '2026-04-10 13:05:00');
        $this->makeAuditEvent(AuditEvent::SUBJECT_INVENTORY_ITEM, $inventoryItem->id, 'deleted', 'Inventory Item #'.$inventoryItem->id.' - '.$inventoryItem->name, occurredAt: '2026-04-10 13:15:00');

        $events = collect($this->actingAs($manager)
            ->getJson('/panel/system-activity/list?per_page=20')
            ->assertOk()
            ->json('events.data'));

        foreach ([
            [AuditEvent::SUBJECT_EMPLOYEE, $deletedEmployee->id],
            [AuditEvent::SUBJECT_ATTENDANCE, $attendance->id],
            [AuditEvent::SUBJECT_INVENTORY_ITEM, $inventoryItem->id],
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
            ->getJson('/panel/system-activity/list?per_page=20')
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
                ->postJson("/panel/system-activity/{$auditEvent->id}/restore")
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

    public function test_restore_endpoint_restores_attendance_walk_in_check_ins(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');

        $attendance = Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Walk-in Gwen',
            'checked_in_at' => '2026-04-10 09:00:00',
            'recorded_by' => $manager->id,
        ]);
        $attendance->delete();

        $auditEvent = $this->makeAuditEvent(AuditEvent::SUBJECT_ATTENDANCE, $attendance->id, 'deleted', 'Attendance #'.$attendance->id.' - '.$attendance->name);

        $this->actingAs($manager)
            ->postJson("/panel/system-activity/{$auditEvent->id}/restore")
            ->assertOk();

        $this->assertNull(Attendance::withTrashed()->findOrFail($attendance->id)->deleted_at);
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
                ->postJson("/panel/system-activity/{$auditEvent->id}/restore")
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
            ->getJson('/panel/system-activity/list?subject_type=employee&subject_id='.$employee->id.'&per_page=10')
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

    }

    public function test_sale_audit_subject_labels_are_trimmed_to_fit_the_column(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $longName = str_repeat('S', 255);

        app(AuditHistoryService::class)->recordSubjectEvent(
            AuditEvent::SUBJECT_SALE_TRANSACTION,
            999,
            'created',
            [
                'id' => 999,
                'customer_name' => $longName,
                'item_name' => 'Single Visit',
                'type' => 'walk_in',
                'payment_method' => 'cash',
                'total' => 250,
            ],
            [],
            $manager->id,
            $manager->name,
            now(),
        );

        $auditEvent = AuditEvent::query()
            ->where('subject_type', AuditEvent::SUBJECT_SALE_TRANSACTION)
            ->where('subject_id', 999)
            ->where('event', 'created')
            ->firstOrFail();

        $this->assertLessThanOrEqual(255, strlen($auditEvent->subject_label ?? ''));
        $this->assertStringStartsWith('Sale #999 - ', (string) $auditEvent->subject_label);
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
