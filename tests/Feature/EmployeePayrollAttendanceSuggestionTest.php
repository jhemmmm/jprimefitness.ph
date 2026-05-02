<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayrollAttendanceSuggestionTest extends TestCase
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

    public function test_payroll_suggestion_prorates_partial_day_hours_and_excludes_open_attendance_records(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Juan Dela Cruz');

        $this->createAttendance($employee, '2026-03-01 08:00:00', '2026-03-01 09:00:00');
        $this->createAttendance($employee, '2026-03-01 10:00:00', null);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('days_worked', 1)
            ->assertJsonPath('regular_hours', 1)
            ->assertJsonPath('regular_pay_amount', 100)
            ->assertJsonPath('overwork_hours', 0)
            ->assertJsonPath('overwork_pay_amount', 0)
            ->assertJsonPath('gross_amount', 100)
            ->assertJsonPath('open_attendance_count', 1)
            ->assertJsonPath('manual_gross_adjustment_amount', 0);
    }

    public function test_overwork_disabled_ignores_hours_above_eight_and_serializes_zero_overwork_values(): void
    {
        $this->setBusinessProfile(false, 'SG');
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Paula Reyes');

        $this->createAttendance($employee, '2026-03-01 08:00:00', '2026-03-01 14:00:00');
        $this->createAttendance($employee, '2026-03-01 15:00:00', '2026-03-01 19:00:00');

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 800,
                'bonus' => 0,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('regular_pay_amount', 800)
            ->assertJsonPath('overwork_hours', 0)
            ->assertJsonPath('overwork_pay_amount', 0)
            ->assertJsonPath('manual_gross_adjustment_amount', 0);

        $this->assertDatabaseHas('payrolls', [
            'id' => $response->json('id'),
            'regular_hours' => '8.00',
            'regular_pay_amount' => '800.00',
            'overwork_hours' => '0.00',
            'overwork_pay_amount' => '0.00',
        ]);
    }

    public function test_payroll_suggestion_sums_multiple_attendances_per_day_and_pays_overwork_when_enabled(): void
    {
        $this->setBusinessProfile(true);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Maria Santos');

        $this->createAttendance($employee, '2026-03-01 08:00:00', '2026-03-01 14:00:00');
        $this->createAttendance($employee, '2026-03-01 15:00:00', '2026-03-01 19:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('days_worked', 1)
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('regular_pay_amount', 800)
            ->assertJsonPath('overwork_hours', 2)
            ->assertJsonPath('overwork_pay_amount', 200)
            ->assertJsonPath('gross_amount', 1000)
            ->assertJsonPath('manual_gross_adjustment_amount', 0);
    }

    public function test_creating_and_updating_payroll_persists_attendance_breakdown_and_manual_gross_adjustment(): void
    {
        $this->setBusinessProfile(true, 'SG');
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Taylor Cruz');

        $this->createAttendance($employee, '2026-03-01 08:00:00', '2026-03-01 14:00:00');
        $this->createAttendance($employee, '2026-03-01 15:00:00', '2026-03-01 19:00:00');

        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1100,
                'bonus' => 0,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('regular_pay_amount', 800)
            ->assertJsonPath('overwork_hours', 2)
            ->assertJsonPath('overwork_pay_amount', 200)
            ->assertJsonPath('manual_gross_adjustment_amount', 100)
            ->json('id');

        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'regular_hours' => '8.00',
            'regular_pay_amount' => '800.00',
            'overwork_hours' => '2.00',
            'overwork_pay_amount' => '200.00',
            'gross_amount' => '1100.00',
        ]);

        $this->actingAs($manager)
            ->putJson("/panel/employees/{$employee->id}/payrolls/{$payrollId}", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1200,
                'bonus' => 0,
                'manual_deductions' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('regular_pay_amount', 800)
            ->assertJsonPath('overwork_hours', 2)
            ->assertJsonPath('overwork_pay_amount', 200)
            ->assertJsonPath('manual_gross_adjustment_amount', 200);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'regular_hours' => '8.00',
            'regular_pay_amount' => '800.00',
            'overwork_hours' => '2.00',
            'overwork_pay_amount' => '200.00',
            'gross_amount' => '1200.00',
        ]);
    }

    public function test_legacy_payrolls_without_snapshot_do_not_report_manual_adjustments(): void
    {
        $this->setBusinessProfile(false, 'SG');
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Legacy Reyes');

        Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 500,
            'bonus' => 0,
            'income_tax' => 0,
            'manual_deductions' => 0,
            'net_amount' => 500,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls")
            ->assertOk()
            ->assertJsonPath('0.regular_hours', null)
            ->assertJsonPath('0.regular_pay_amount', null)
            ->assertJsonPath('0.overwork_hours', null)
            ->assertJsonPath('0.overwork_pay_amount', null)
            ->assertJsonPath('0.manual_gross_adjustment_amount', null);
    }

    private function setBusinessProfile(bool $payOverworkHours, string $countryCode = BusinessProfile::COUNTRY_PHILIPPINES): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness',
            'country_code' => $countryCode,
            'pay_overwork_hours' => $payOverworkHours,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createEmployeeWithRole(string $role, string $name): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 800,
            'pay_frequency' => 'semi_monthly',
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function createAttendance(User $employee, string $checkedInAt, ?string $checkedOutAt): Attendance
    {
        return Attendance::create([
            'attendee_type' => Attendance::TYPE_EMPLOYEE,
            'user_id' => $employee->id,
            'name' => $employee->name,
            'checked_in_at' => $checkedInAt,
            'checked_out_at' => $checkedOutAt,
            'source' => Attendance::SOURCE_MANUAL,
        ]);
    }
}
