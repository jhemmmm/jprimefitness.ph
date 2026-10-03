<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BusinessProfile;
use App\Models\EmployeeScheduleShift;
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

    public function test_payroll_suggestion_allows_late_time_to_be_made_up_without_overwork_pay(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Late Staff');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->createAttendance($employee, '2026-03-02 10:00:00', '2026-03-02 18:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('days_worked', 1)
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('regular_pay_amount', 800)
            ->assertJsonPath('overwork_hours', 0)
            ->assertJsonPath('overwork_pay_amount', 0)
            ->assertJsonPath('gross_amount', 800)
            ->assertJsonPath('manual_gross_adjustment_amount', 0);
    }

    public function test_payroll_suggestion_caps_regular_scheduled_pay_at_eight_hours_when_overwork_is_disabled(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Capped Staff');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->createAttendance($employee, '2026-03-02 10:00:00', '2026-03-02 19:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('days_worked', 1)
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('regular_pay_amount', 800)
            ->assertJsonPath('overwork_hours', 0)
            ->assertJsonPath('overwork_pay_amount', 0)
            ->assertJsonPath('gross_amount', 800)
            ->assertJsonPath('manual_gross_adjustment_amount', 0);
    }

    public function test_payroll_suggestion_counts_all_overtime_outside_scheduled_shifts(): void
    {
        $this->setBusinessProfile(true);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Overtime Staff');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        $this->createAttendance($employee, '2026-03-02 09:00:00', '2026-03-02 22:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('days_worked', 1)
            ->assertJsonPath('regular_hours', 4)
            ->assertJsonPath('regular_pay_amount', 400)
            ->assertJsonPath('overwork_hours', 9)
            ->assertJsonPath('overwork_pay_amount', 900)
            ->assertJsonPath('gross_amount', 1300)
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
            'withholding_tax' => 0,
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

    public function test_late_check_in_slides_window_and_refills_full_pay(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Refill Staff');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
        ]);

        $this->createAttendance($employee, '2026-03-02 08:00:00', '2026-03-02 16:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('gross_amount', 800)
            ->assertJsonPath('days.0.date', '2026-03-02')
            ->assertJsonPath('days.0.status', 'late')
            ->assertJsonPath('days.0.late_minutes', 120)
            ->assertJsonPath('days.0.paid_hours', 8)
            ->assertJsonPath('days.0.day_pay_amount', 800)
            ->assertJsonPath('days.1.date', '2026-03-09')
            ->assertJsonPath('days.1.status', 'absent')
            ->assertJsonPath('days.1.paid_hours', 0);
    }

    public function test_early_check_in_does_not_pay_before_scheduled_start(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Early Staff');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->createAttendance($employee, '2026-03-02 07:00:00', '2026-03-02 15:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-02&period_end=2026-03-02")
            ->assertOk()
            ->assertJsonPath('regular_hours', 6)
            ->assertJsonPath('gross_amount', 600)
            ->assertJsonPath('days.0.status', 'undertime')
            ->assertJsonPath('days.0.late_minutes', 0)
            ->assertJsonPath('days.0.worked_hours', 8)
            ->assertJsonPath('days.0.paid_hours', 6);
    }

    public function test_partial_refill_is_reported_as_undertime_with_late_minutes(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Partial Staff');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->createAttendance($employee, '2026-03-02 10:00:00', '2026-03-02 15:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-02&period_end=2026-03-02")
            ->assertOk()
            ->assertJsonPath('regular_hours', 5)
            ->assertJsonPath('gross_amount', 500)
            ->assertJsonPath('days.0.status', 'undertime')
            ->assertJsonPath('days.0.late_minutes', 60)
            ->assertJsonPath('days.0.paid_hours', 5);
    }

    public function test_split_shifts_slide_together_on_late_check_in(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Split Staff');

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

        $this->createAttendance($employee, '2026-03-02 08:00:00', '2026-03-02 12:00:00');
        $this->createAttendance($employee, '2026-03-02 18:00:00', '2026-03-02 22:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-02&period_end=2026-03-02")
            ->assertOk()
            ->assertJsonPath('regular_hours', 8)
            ->assertJsonPath('gross_amount', 800)
            ->assertJsonPath('days.0.status', 'late')
            ->assertJsonPath('days.0.late_minutes', 120)
            ->assertJsonPath('days.0.paid_hours', 8);
    }

    public function test_simple_path_returns_unscheduled_day_rows_and_open_only_days_are_not_absent(): void
    {
        $this->setBusinessProfile(false);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Simple Staff');

        $this->createAttendance($employee, '2026-03-01 08:00:00', '2026-03-01 09:00:00');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertOk()
            ->assertJsonPath('days.0.date', '2026-03-01')
            ->assertJsonPath('days.0.status', 'unscheduled')
            ->assertJsonPath('days.0.scheduled_hours', 0)
            ->assertJsonPath('days.0.worked_hours', 1)
            ->assertJsonCount(1, 'days');

        $scheduled = $this->createEmployeeWithRole('staff', 'Open Staff');

        EmployeeScheduleShift::factory()->create([
            'employee_profile_id' => $scheduled->employeeProfile->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->createAttendance($scheduled, '2026-03-02 09:00:00', null);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$scheduled->id}/payrolls/suggest?period_start=2026-03-02&period_end=2026-03-02")
            ->assertOk()
            ->assertJsonPath('open_attendance_count', 1)
            ->assertJsonCount(0, 'days');
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
