<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
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
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'daily_rate' => 450,
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pay_frequency']);
    }

    public function test_employee_creation_and_update_store_explicit_pay_frequency(): void
    {
        $this->setBusinessProfile('Legazpi');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $createResponse = $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'daily_rate' => 450,
                'pay_frequency' => 'monthly',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('pay_frequency', 'monthly');

        $employeeId = $createResponse->json('id');

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'pay_frequency' => 'monthly',
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employeeId}", [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'daily_rate' => 450,
                'pay_frequency' => 'semi_monthly',
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('pay_frequency', 'semi_monthly');

        $this->assertDatabaseHas('users', [
            'id' => $employeeId,
            'pay_frequency' => 'semi_monthly',
        ]);
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
