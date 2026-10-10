<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendancePermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness']);
    }

    public function test_view_attendance_can_read_attendance_but_cannot_change_records_or_view_reports(): void
    {
        $viewer = $this->userWithPermissions('attendance-viewer', [
            'view attendance',
            'manage members',
            'manage employees',
        ]);
        $member = $this->member();
        $employee = $this->employee();
        $memberAttendance = $this->attendance($member, Attendance::TYPE_MEMBER);
        $employeeAttendance = $this->attendance($employee, Attendance::TYPE_EMPLOYEE);

        $this->actingAs($viewer)->get('/panel/attendance')->assertOk();
        $this->actingAs($viewer)->getJson('/panel/attendance/list')->assertOk();
        $this->actingAs($viewer)->getJson("/panel/members/{$member->id}/attendance")->assertOk();
        $this->actingAs($viewer)->postJson("/panel/employees/{$employee->id}/attendance", [])->assertOk();

        $this->actingAs($viewer)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
        ])->assertForbidden();
        $this->actingAs($viewer)->postJson("/panel/attendance/{$memberAttendance->id}/checkout")->assertForbidden();
        $this->actingAs($viewer)->putJson("/panel/attendance/{$employeeAttendance->id}", [
            'checked_in_at' => now()->subHour()->toISOString(),
        ])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/panel/attendance/{$memberAttendance->id}")->assertForbidden();
        $this->actingAs($viewer)->get('/panel/reports/attendance')->assertForbidden();
    }

    public function test_manage_member_attendance_can_change_members_and_walk_ins_but_not_employees(): void
    {
        $manager = $this->userWithPermissions('member-attendance-manager', ['manage member attendance']);
        $member = $this->member();
        $employee = $this->employee();
        $memberAttendance = $this->attendance($member, Attendance::TYPE_MEMBER);
        $employeeAttendance = $this->attendance($employee, Attendance::TYPE_EMPLOYEE);

        $this->actingAs($manager)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
            'checked_in_at' => now()->subHour()->toISOString(),
        ])->assertCreated();
        $this->actingAs($manager)->get('/panel/attendance')->assertOk();
        $this->actingAs($manager)->getJson('/panel/attendance/list')->assertOk();

        $this->actingAs($manager)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Walk-in Guest',
            'checked_in_at' => now()->subHour()->toISOString(),
        ])->assertCreated();

        $this->actingAs($manager)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $employee->id,
        ])->assertForbidden();
        $this->actingAs($manager)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $employee->id,
        ])->assertStatus(422);
        $this->actingAs($manager)->postJson("/panel/attendance/{$employeeAttendance->id}/checkout")->assertForbidden();

        $this->actingAs($manager)->postJson("/panel/attendance/{$memberAttendance->id}/checkout")->assertOk();
        $this->actingAs($manager)->putJson("/panel/attendance/{$memberAttendance->id}", [
            'checked_in_at' => now()->subHours(2)->toISOString(),
        ])->assertOk();
        $this->actingAs($manager)->deleteJson("/panel/attendance/{$memberAttendance->id}")->assertNoContent();
    }

    public function test_manage_employee_attendance_can_change_employees_but_not_members(): void
    {
        $manager = $this->userWithPermissions('employee-attendance-manager', ['manage employee attendance']);
        $member = $this->member();
        $employee = $this->employee();
        $memberAttendance = $this->attendance($member, Attendance::TYPE_MEMBER);
        $employeeAttendance = $this->attendance($employee, Attendance::TYPE_EMPLOYEE);

        $this->actingAs($manager)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $employee->id,
            'checked_in_at' => now()->subHour()->toISOString(),
        ])->assertCreated();
        $this->actingAs($manager)->get('/panel/attendance')->assertOk();
        $this->actingAs($manager)->getJson('/panel/attendance/list')->assertOk();

        $this->actingAs($manager)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_MEMBER,
            'user_id' => $member->id,
        ])->assertForbidden();
        $this->actingAs($manager)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $member->id,
        ])->assertStatus(422);
        $this->actingAs($manager)->postJson("/panel/attendance/{$memberAttendance->id}/checkout")->assertForbidden();
        $this->actingAs($manager)->postJson("/panel/attendance/{$employeeAttendance->id}/checkout")->assertOk();
        $this->actingAs($manager)->putJson("/panel/attendance/{$employeeAttendance->id}", [
            'checked_in_at' => now()->subHours(2)->toISOString(),
        ])->assertOk();
        $this->actingAs($manager)->deleteJson("/panel/attendance/{$employeeAttendance->id}")->assertNoContent();
    }

    public function test_employee_can_read_their_own_attendance_without_a_management_permission(): void
    {
        $employee = $this->userWithPermissions('self-service-employee', []);
        $attendance = $this->attendance($employee, Attendance::TYPE_EMPLOYEE);

        $this->actingAs($employee)
            ->postJson("/panel/employees/{$employee->id}/attendance", [])
            ->assertOk()
            ->assertJsonPath('records.data.0.id', $attendance->id);

        $this->actingAs($employee)->postJson("/panel/attendance/{$attendance->id}/checkout")->assertForbidden();
        $this->actingAs($employee)->putJson("/panel/attendance/{$attendance->id}", [
            'checked_in_at' => now()->subHour()->toISOString(),
        ])->assertForbidden();
        $this->actingAs($employee)->deleteJson("/panel/attendance/{$attendance->id}")->assertForbidden();
        $this->actingAs($employee)->postJson('/panel/attendance', [
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $employee->id,
        ])->assertForbidden();
    }

    public function test_legacy_manage_attendance_permission_is_retired(): void
    {
        $this->assertDatabaseMissing('permissions', ['name' => 'manage attendance']);
        $this->assertDatabaseHas('permissions', ['name' => 'view attendance']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage member attendance']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage employee attendance']);
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(string $roleName, array $permissions): User
    {
        $role = Role::findOrCreate($roleName);
        foreach (['access panel', ...$permissions] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->withEmployeeProfile()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    private function member(): User
    {
        $member = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $member->assignRole('member');

        return $member;
    }

    private function employee(): User
    {
        $employee = User::factory()->withEmployeeProfile()->create(['status' => User::STATUS_ACTIVE]);
        $employee->assignRole('staff');

        return $employee;
    }

    private function attendance(User $user, string $type): Attendance
    {
        return Attendance::query()->create([
            'attendee_type' => $type,
            'user_id' => $user->id,
            'name' => $user->name,
            'checked_in_at' => now()->subHours(2),
            'source' => Attendance::SOURCE_MANUAL,
        ]);
    }
}
