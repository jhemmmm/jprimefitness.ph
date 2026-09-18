<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeesListTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BusinessProfile::factory()->create(['name' => 'JPrime Fitness Naga']);
    }

    public function test_administrators_are_not_employees(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $this->createUserWithRole('coach', 'Coach Cara');
        $this->createUserWithRole('admin', 'Admin Alon');
        $this->createUserWithRole('super admin', 'Super Sol');

        $names = $this->actingAs($manager)
            ->getJson('/panel/employees/list')
            ->assertOk()
            ->json('*.name');

        $this->assertEqualsCanonicalizing(['Manager Mia', 'Coach Cara'], $names);

        $this->actingAs($manager)
            ->getJson('/panel/search?search=Alon')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Admin Alon']);

        // the employees UI only offers employee roles, so `admin` can't be assigned from it
        $this->actingAs($manager)
            ->get('/panel/employees')
            ->assertOk()
            ->assertDontSee('"name":"admin"', false)
            ->assertDontSee('"name":"super admin"', false)
            ->assertSee('"name":"coach"', false);

        $this->actingAs($manager)
            ->postJson('/panel/employees', [
                'name' => 'Would-be Admin',
                'email' => 'would-be-admin@example.com',
                'phone' => '09170000000',
                'password' => 'Password123!',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [Role::findByName('admin')->id],
                'employee_profile' => [
                    'date_of_birth' => '1990-01-01',
                    'emergency_contact_name' => 'Next of Kin',
                    'emergency_contact_phone' => '09170000001',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_ids.0']);
    }

    public function test_saving_a_mixed_role_employee_keeps_the_administrator_role(): void
    {
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $both = $this->createUserWithRole('manager', 'Admin Amy');
        $both->assignRole('admin');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$both->id}", [
                'name' => 'Admin Amy',
                'email' => $both->email,
                'phone' => '09170000000',
                'status' => User::STATUS_ACTIVE,
                'role_ids' => [Role::findByName('manager')->id], // the form only offers employee roles
                'employee_profile' => [
                    'daily_rate' => 500,
                    'pay_frequency' => 'semi_monthly',
                    'sss_covered' => false,
                    'philhealth_covered' => false,
                    'pagibig_covered' => false,
                    'date_of_birth' => '1990-01-01',
                    'emergency_contact_name' => 'Next of Kin',
                    'emergency_contact_phone' => '09170000001',
                ],
            ])
            ->assertOk();

        $this->assertEqualsCanonicalizing(['admin', 'manager'], $both->fresh()->roles->pluck('name')->all());
    }

    private function createUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile()->create([
            'name' => $name,
            'email' => str($name)->slug('.').'@example.test',
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
