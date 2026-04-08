<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\WalkIn;
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
        $profile = $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Guest One',
            'checked_in_at' => now()->subHour(),
            'recorded_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/attendance/list?branch=999')
            ->assertOk()
            ->assertJsonPath('records.data.0.name', 'Guest One')
            ->assertJsonPath('records.data.0.branch_id', $profile->id)
            ->assertJsonPath('stats.today', 1);
    }

    public function test_walk_in_list_ignores_legacy_branch_query_params(): void
    {
        $profile = $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');

        WalkIn::create([
            'served_by' => $staff->id,
            'name' => 'Guest One',
            'amount_paid' => 350,
            'payment_method' => 'cash',
            'visited_at' => now()->subHour(),
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/walk-ins/list?branch=999')
            ->assertOk()
            ->assertJsonPath('walkIns.data.0.name', 'Guest One')
            ->assertJsonPath('walkIns.data.0.branch_id', $profile->id)
            ->assertJsonPath('stats.today', 1)
            ->assertJsonPath('stats.revenue_today', 350);
    }

    public function test_employee_list_ignores_legacy_branch_query_params(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('employee', 'Team Member');

        $response = $this->actingAs($manager)
            ->getJson('/panel/employees/list?branch=999')
            ->assertOk();

        $employeeIds = collect($response->json())->pluck('id')->all();

        $this->assertContains($employee->id, $employeeIds);
    }

    public function test_member_list_ignores_legacy_branch_query_params(): void
    {
        $this->setBusinessProfile('Naga');
        $staff = $this->createUserWithRole('staff', 'Staff Ana');
        $member = $this->createUserWithRole('member', 'Accessible Member');

        $this->actingAs($staff)
            ->getJson('/panel/members/list?branch=999')
            ->assertOk()
            ->assertJsonPath('members.data.0.id', $member->id)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.active', 1);
    }

    private function setBusinessProfile(string $name): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'status' => BusinessProfile::STATUS_OPEN,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
            'pay_frequency' => 'semi_monthly',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
