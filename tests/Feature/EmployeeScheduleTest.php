<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\EmployeeScheduleShift;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        $staffRole = Role::findOrCreate('staff');
        $memberRole = Role::findOrCreate('member');
        $permission = Permission::findOrCreate('manage employees');

        $managerRole->givePermissionTo($permission);
        $staffRole->givePermissionTo($permission);
    }

    public function test_schedule_endpoint_returns_empty_when_no_shifts_exist(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/schedule")
            ->assertOk()
            ->assertExactJson(['shifts' => []]);
    }

    public function test_schedule_endpoint_returns_existing_shifts_ordered(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 3,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
        ]);
        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '06:00:00',
            'end_time' => '10:00:00',
        ]);
        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '16:00:00',
            'end_time' => '20:00:00',
        ]);

        $response = $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/schedule")
            ->assertOk();

        $shifts = $response->json('shifts');
        $this->assertCount(3, $shifts);
        $this->assertSame([1, 1, 3], array_column($shifts, 'day_of_week'));
        $this->assertSame(['06:00', '16:00', '14:00'], array_column($shifts, 'start_time'));
    }

    public function test_save_replaces_existing_shifts_and_supports_split_shifts(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 5,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);

        $payload = [
            'shifts' => [
                ['day_of_week' => 1, 'start_time' => '06:00', 'end_time' => '10:00'],
                ['day_of_week' => 1, 'start_time' => '16:00', 'end_time' => '20:00'],
                ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ];

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/schedule", $payload)
            ->assertOk()
            ->assertJsonCount(3, 'shifts');

        $this->assertDatabaseCount('employee_schedule_shifts', 3);
        $this->assertDatabaseMissing('employee_schedule_shifts', [
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 5,
        ]);
        $this->assertDatabaseHas('employee_schedule_shifts', [
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '06:00:00',
            'end_time' => '10:00:00',
        ]);
        $this->assertDatabaseHas('employee_schedule_shifts', [
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '16:00:00',
            'end_time' => '20:00:00',
        ]);
    }

    public function test_save_with_empty_shifts_clears_schedule(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        EmployeeScheduleShift::factory()->count(2)->create([
            'employee_profile_id' => $employee->employeeProfile->id,
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/schedule", ['shifts' => []])
            ->assertOk()
            ->assertExactJson(['shifts' => []]);

        $this->assertDatabaseCount('employee_schedule_shifts', 0);
    }

    public function test_save_rejects_overlapping_shifts_on_the_same_day(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/schedule", [
                'shifts' => [
                    ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '13:00'],
                    ['day_of_week' => 2, 'start_time' => '12:00', 'end_time' => '16:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shifts.1.start_time']);

        $this->assertDatabaseCount('employee_schedule_shifts', 0);
    }

    public function test_save_rejects_end_time_before_or_equal_to_start_time(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/schedule", [
                'shifts' => [
                    ['day_of_week' => 4, 'start_time' => '17:00', 'end_time' => '09:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shifts.0.end_time']);
    }

    public function test_save_rejects_overnight_shift(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/schedule", [
                'shifts' => [
                    ['day_of_week' => 6, 'start_time' => '22:00', 'end_time' => '02:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shifts.0.end_time']);
    }

    public function test_save_rejects_invalid_day_of_week(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/schedule", [
                'shifts' => [
                    ['day_of_week' => 7, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shifts.0.day_of_week']);
    }

    public function test_member_without_manage_employees_permission_cannot_view_schedule(): void
    {
        $this->setBusinessProfile();
        $member = $this->createUserWithRole('member', 'Member Mae');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($member)
            ->getJson("/panel/employees/{$employee->id}/schedule")
            ->assertForbidden();
    }

    public function test_save_logs_a_system_activity_event(): void
    {
        $this->setBusinessProfile();
        $manager = $this->createUserWithRole('manager', 'Manager Mia');
        $employee = $this->createUserWithRole('staff', 'Coach Ben');

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/schedule", [
                'shifts' => [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('system_activities', [
            'subject_type' => 'employee',
            'subject_id' => $employee->id,
            'event' => 'schedule_updated',
            'actor_user_id' => $manager->id,
        ]);
    }

    private function setBusinessProfile(): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => 'JPrime Naga',
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
            'email' => Str::slug($role.' '.$name, '.').'@example.test',
            'phone' => fake()->unique()->numerify('09#########'),
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
