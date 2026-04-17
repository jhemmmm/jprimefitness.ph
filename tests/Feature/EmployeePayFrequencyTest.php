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
                'employee_profile' => [
                    'daily_rate' => 450,
                ],
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_profile.pay_frequency']);
    }

    public function test_manager_can_view_employee_details(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($manager)
            ->get("/panel/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('employee-detail-page', false)
            ->assertSeeText($employee->name);
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
                'employee_profile' => [
                    'daily_rate' => 450,
                    'pay_frequency' => 'monthly',
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_profile.pay_frequency', 'monthly')
            ->assertJsonMissingPath('pay_frequency');

        $employeeId = $createResponse->json('id');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employeeId,
            'pay_frequency' => 'monthly',
            'daily_rate' => '450.00',
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employeeId}", [
                'name' => 'Coach Ben',
                'email' => 'coach-ben@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'employee_profile' => [
                    'daily_rate' => 450,
                    'pay_frequency' => 'semi_monthly',
                ],
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('employee_profile.pay_frequency', 'semi_monthly')
            ->assertJsonMissingPath('pay_frequency');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employeeId,
            'pay_frequency' => 'semi_monthly',
        ]);
    }

    public function test_employee_creation_can_reuse_email_from_a_soft_deleted_employee(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $archivedEmployee = $this->createUserWithRole('staff', 'Archived Coach');
        $archivedEmployee->update(['email' => 'archived-coach@example.com']);
        $archivedEmployee->delete();

        $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'archived-coach@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'employee_profile' => [
                    'daily_rate' => 450,
                    'pay_frequency' => 'monthly',
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('email', 'archived-coach@example.com');

        $this->assertSoftDeleted('users', [
            'id' => $archivedEmployee->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'archived-coach@example.com',
            'deleted_at' => null,
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
