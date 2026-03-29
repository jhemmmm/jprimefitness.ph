<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
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

    public function test_attendance_list_does_not_leak_other_branch_records_when_filtered_by_branch(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');

        Attendance::create([
            'branch_id' => $otherBranch->id,
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'name' => 'Other Branch Guest',
            'checked_in_at' => now()->subHour(),
            'recorded_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/attendance/list?branch='.$otherBranch->id)
            ->assertOk()
            ->assertJsonPath('records.data', [])
            ->assertJsonPath('stats.today', 0)
            ->assertJsonPath('stats.this_week', 0)
            ->assertJsonPath('stats.this_month', 0)
            ->assertJsonPath('stats.currently_in', 0);
    }

    public function test_walk_in_list_does_not_leak_other_branch_records_when_filtered_by_branch(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');

        WalkIn::create([
            'branch_id' => $otherBranch->id,
            'served_by' => $staff->id,
            'name' => 'Other Branch Guest',
            'amount_paid' => 350,
            'visited_at' => now()->subHour(),
        ]);

        $this->actingAs($staff)
            ->getJson('/panel/walk-ins/list?branch='.$otherBranch->id)
            ->assertOk()
            ->assertJsonPath('walkIns.data', [])
            ->assertJsonPath('stats.today', 0)
            ->assertJsonPath('stats.this_week', 0)
            ->assertJsonPath('stats.this_month', 0)
            ->assertJsonPath('stats.revenue_today', 0);
    }

    public function test_employee_list_does_not_leak_other_branch_records_when_filtered_by_branch(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $manager = $this->createUserWithRole('manager', [$accessibleBranch->id], 'Manager Mia');
        $otherEmployee = $this->createUserWithRole('employee', [$otherBranch->id], 'Other Branch Employee');

        $response = $this->actingAs($manager)
            ->getJson('/panel/employees/list?branch='.$otherBranch->id)
            ->assertOk();

        $employeeIds = collect($response->json())->pluck('id')->all();

        $this->assertNotContains($otherEmployee->id, $employeeIds);
        $this->assertSame([], $employeeIds);
    }

    public function test_member_list_does_not_leak_other_branch_records_when_filtered_by_branch(): void
    {
        $accessibleBranch = $this->createBranch('Naga');
        $otherBranch = $this->createBranch('Legazpi');
        $staff = $this->createUserWithRole('staff', [$accessibleBranch->id], 'Staff Ana');

        $this->createUserWithRole('member', [$accessibleBranch->id], 'Accessible Member');
        $this->createUserWithRole('member', [$otherBranch->id], 'Other Branch Member');

        $this->actingAs($staff)
            ->getJson('/panel/members/list?branch='.$otherBranch->id)
            ->assertOk()
            ->assertJsonPath('members.data', [])
            ->assertJsonPath('stats.total', 0)
            ->assertJsonPath('stats.active', 0)
            ->assertJsonPath('stats.inactive', 0)
            ->assertJsonPath('stats.suspended', 0);
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
