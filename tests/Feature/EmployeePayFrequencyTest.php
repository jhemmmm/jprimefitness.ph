<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayFrequencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        $staffRole = Role::findOrCreate('staff');
        $permission = Permission::findOrCreate('manage employees');

        $managerRole->givePermissionTo($permission);
        $staffRole->givePermissionTo($permission);
    }

    public function test_employee_creation_requires_an_explicit_pay_frequency(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'status' => User::STATUS_ACTIVE,
                'branch_ids' => [$branch->id],
                'role_ids' => [$staffRole->id],
                'daily_rate' => 450,
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pay_frequency']);
    }

    public function test_employee_creation_and_update_store_explicit_pay_frequency(): void
    {
        $branch = $this->createBranch('Legazpi');
        $manager = $this->createUserWithRole('manager', [$branch->id], 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $createResponse = $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'status' => User::STATUS_ACTIVE,
                'branch_ids' => [$branch->id],
                'role_ids' => [$staffRole->id],
                'daily_rate' => 450,
                'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('pay_frequency', Branch::PAYROLL_FREQUENCY_MONTHLY);

        $employeeId = $createResponse->json('id');

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employeeId}", [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'status' => User::STATUS_ACTIVE,
                'branch_ids' => [$branch->id],
                'role_ids' => [$staffRole->id],
                'daily_rate' => 450,
                'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('pay_frequency', Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY);

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
        ]);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => 'PH',
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
