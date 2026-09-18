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

    public function test_employee_creation_rejects_root_level_compensation_payload(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben-root@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'daily_rate' => 450,
                'pay_frequency' => 'monthly',
                'sss_covered' => false,
                'philhealth_covered' => false,
                'pagibig_covered' => false,
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_profile']);
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
                    'sss_covered' => true,
                    'sss_employee_share' => 300,
                    'sss_employer_share' => 600,
                    'philhealth_covered' => true,
                    'philhealth_employee_share' => 275.5,
                    'philhealth_employer_share' => 275.5,
                    'pagibig_covered' => true,
                    'pagibig_employee_share' => 200,
                    'pagibig_employer_share' => 200,
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_profile.pay_frequency', 'monthly')
            ->assertJsonPath('employee_profile.sss_covered', true)
            ->assertJsonPath('employee_profile.sss_employee_share', 300)
            ->assertJsonPath('employee_profile.sss_employer_share', 600)
            ->assertJsonPath('employee_profile.philhealth_employee_share', 275.5)
            ->assertJsonPath('employee_profile.pagibig_employer_share', 200)
            ->assertJsonMissingPath('pay_frequency');

        $employeeId = $createResponse->json('id');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employeeId,
            'pay_frequency' => 'monthly',
            'daily_rate' => '450.00',
            'sss_covered' => 1,
            'sss_employee_share' => '300.00',
            'sss_employer_share' => '600.00',
            'philhealth_covered' => 1,
            'philhealth_employee_share' => '275.50',
            'philhealth_employer_share' => '275.50',
            'pagibig_covered' => 1,
            'pagibig_employee_share' => '200.00',
            'pagibig_employer_share' => '200.00',
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
                    'sss_covered' => true,
                    'sss_employee_share' => 0,
                    'sss_employer_share' => 750,
                    'philhealth_covered' => true,
                    'philhealth_employee_share' => 275.5,
                    'philhealth_employer_share' => 275.5,
                    'pagibig_covered' => false,
                    'pagibig_employee_share' => null,
                    'pagibig_employer_share' => null,
                ],
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('employee_profile.pay_frequency', 'semi_monthly')
            ->assertJsonPath('employee_profile.sss_employee_share', 0)
            ->assertJsonPath('employee_profile.sss_employer_share', 750)
            ->assertJsonPath('employee_profile.pagibig_covered', false)
            ->assertJsonPath('employee_profile.pagibig_employee_share', 100)
            ->assertJsonMissingPath('pay_frequency');

        // Explicit zero is kept; blank falls back to the legal minimum.
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employeeId,
            'pay_frequency' => 'semi_monthly',
            'sss_employee_share' => '0.00',
            'sss_employer_share' => '750.00',
            'pagibig_covered' => 0,
            'pagibig_employee_share' => '100.00',
            'pagibig_employer_share' => '100.00',
        ]);
    }

    public function test_philippines_employee_creation_defaults_contribution_amounts_to_the_legal_minimum(): void
    {
        $this->setBusinessProfile('Naga');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben-ph@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'employee_profile' => [
                    'daily_rate' => 450,
                    'pay_frequency' => 'monthly',
                    'sss_covered' => true,
                    'philhealth_covered' => true,
                    'pagibig_covered' => true,
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_profile.sss_employee_share', 250)
            ->assertJsonPath('employee_profile.sss_employer_share', 500);

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => User::where('email', 'coach-ben-ph@example.com')->value('id'),
            'sss_employee_share' => '250.00',
            'sss_employer_share' => '500.00',
            'philhealth_employee_share' => '250.00',
            'philhealth_employer_share' => '250.00',
            'pagibig_employee_share' => '100.00',
            'pagibig_employer_share' => '100.00',
        ]);
    }

    public function test_non_ph_employee_creation_ignores_ph_government_contribution_validation(): void
    {
        $this->setBusinessProfile('Singapore', 'SG');
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $staffRole = Role::findByName('staff');

        $response = $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Coach Ben',
                'email' => 'coach-ben-sg@example.com',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [$staffRole->id],
                'employee_profile' => [
                    'daily_rate' => 450,
                    'pay_frequency' => 'monthly',
                    'sss_covered' => true,
                    'philhealth_covered' => true,
                    'pagibig_covered' => true,
                ],
                'password' => 'password123',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_profile.sss_covered', false)
            ->assertJsonPath('employee_profile.philhealth_covered', false)
            ->assertJsonPath('employee_profile.pagibig_covered', false);

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $response->json('id'),
            'sss_covered' => 0,
            'philhealth_covered' => 0,
            'pagibig_covered' => 0,
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
                    'sss_covered' => false,
                    'philhealth_covered' => false,
                    'pagibig_covered' => false,
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

    private function setBusinessProfile(string $name, string $countryCode = BusinessProfile::COUNTRY_PHILIPPINES): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'country_code' => $countryCode,
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
