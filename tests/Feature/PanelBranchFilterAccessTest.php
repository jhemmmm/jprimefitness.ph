<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PanelBranchFilterAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        $staffRole = Role::findOrCreate('staff');
        Role::findOrCreate('member');
        Role::findOrCreate('employee');
        Role::findOrCreate('coach');
        Role::findOrCreate('admin');
        Role::findOrCreate('super admin');

        $permission = Permission::findOrCreate('manage employees');

        $managerRole->givePermissionTo($permission);
        $staffRole->givePermissionTo($permission);
    }

    public function test_attendance_list_ignores_legacy_branch_query_params(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Guest One',
            'checked_in_at' => now()->subHour(),
            'recorded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/panel/attendance/list?branch=999')
            ->assertOk()
            ->assertJsonPath('records.data.0.name', 'Guest One')
            ->assertJsonPath('stats.today', 1);

        $record = $response->json('records.data.0');

        $this->assertIsArray($record);
        $this->assertArrayNotHasKey('branch_id', $record);
        $this->assertArrayNotHasKey('branch', $record);
    }

    public function test_employee_list_ignores_legacy_branch_query_params(): void
    {
        $profile = $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('employee', 'Team Member');

        $response = $this->actingAs($manager)
            ->getJson('/panel/employees/list?branch=999')
            ->assertOk();

        $employeePayload = collect($response->json())
            ->firstWhere('id', $employee->id);

        $this->assertIsArray($employeePayload);
        $this->assertSame($profile->id, $employeePayload['location']['id'] ?? null);
        $this->assertArrayNotHasKey('branches', $employeePayload);
        $this->assertArrayNotHasKey('branch_id', $employeePayload);
        $this->assertArrayNotHasKey('branch', $employeePayload);
    }

    public function test_member_list_ignores_legacy_branch_query_params(): void
    {
        $profile = $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $member = $this->createUserWithRole('member', 'Accessible Member');

        $response = $this->actingAs($staff)
            ->getJson('/panel/members/list?branch=999')
            ->assertOk()
            ->assertJsonPath('members.data.0.id', $member->id)
            ->assertJsonPath('members.data.0.location.id', $profile->id)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.active', 1);

        $memberPayload = $response->json('members.data.0');

        $this->assertIsArray($memberPayload);
        $this->assertArrayNotHasKey('branches', $memberPayload);
        $this->assertArrayNotHasKey('branch_id', $memberPayload);
        $this->assertArrayNotHasKey('branch', $memberPayload);
    }

    private function setBusinessProfile(string $name): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
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
