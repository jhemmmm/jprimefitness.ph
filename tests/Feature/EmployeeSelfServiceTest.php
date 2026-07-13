<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeSelfServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $manager;

    private User $employee;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerRole = Role::findOrCreate('manager');
        Role::findOrCreate('staff');
        $managerRole->givePermissionTo(Permission::findOrCreate('manage employees'));

        BusinessProfile::factory()->create([
            'name' => 'JPrime Fitness',
            'country_code' => BusinessProfile::COUNTRY_PHILIPPINES,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);

        $this->manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $this->employee = $this->createEmployeeWithRole('staff', 'Self Staff');
        $this->otherEmployee = $this->createEmployeeWithRole('staff', 'Other Staff');
    }

    public function test_employee_can_read_their_own_records(): void
    {
        $payroll = $this->createPayroll($this->employee);

        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->employee->id}/payrolls")->assertOk();
        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->employee->id}/payouts")->assertOk();
        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->employee->id}/payrolls/{$payroll->id}/payouts")->assertOk();
        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->employee->id}/schedule")->assertOk();
        $this->actingAs($this->employee)->postJson("/panel/employees/{$this->employee->id}/attendance", [])->assertOk();
        $this->actingAs($this->employee)->get("/panel/employees/{$this->employee->id}")->assertOk();
    }

    public function test_employee_cannot_read_another_employees_records(): void
    {
        $payroll = $this->createPayroll($this->otherEmployee);

        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->otherEmployee->id}/payrolls")->assertForbidden();
        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->otherEmployee->id}/payouts")->assertForbidden();
        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->otherEmployee->id}/payrolls/{$payroll->id}/payouts")->assertForbidden();
        $this->actingAs($this->employee)->getJson("/panel/employees/{$this->otherEmployee->id}/schedule")->assertForbidden();
        $this->actingAs($this->employee)->postJson("/panel/employees/{$this->otherEmployee->id}/attendance", [])->assertForbidden();
    }

    public function test_employee_cannot_write_their_own_records(): void
    {
        $payroll = $this->createPayroll($this->employee);

        $this->actingAs($this->employee)
            ->putJson("/panel/employees/{$this->employee->id}/schedule", ['shifts' => []])
            ->assertForbidden();
        $this->actingAs($this->employee)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 800,
            ])
            ->assertForbidden();
        $this->actingAs($this->employee)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls/{$payroll->id}/approve")
            ->assertForbidden();
        $this->actingAs($this->employee)
            ->postJson("/panel/employees/{$this->employee->id}/payrolls/{$payroll->id}/payouts", ['amount' => 100, 'method' => 'cash'])
            ->assertForbidden();
        $this->actingAs($this->employee)
            ->getJson("/panel/employees/{$this->employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15")
            ->assertForbidden();
    }

    public function test_manager_retains_access_to_other_employees(): void
    {
        $this->actingAs($this->manager)->getJson("/panel/employees/{$this->employee->id}/payrolls")->assertOk();
        $this->actingAs($this->manager)->getJson("/panel/employees/{$this->employee->id}/schedule")->assertOk();
    }

    public function test_contribution_preview_applies_statutory_floors(): void
    {
        $this->actingAs($this->manager)
            ->getJson('/panel/employees/contribution-preview?'.http_build_query([
                'pay_frequency' => 'semi_monthly',
                'sss_covered' => 1,
                'sss_monthly_compensation' => 1000,
                'philhealth_covered' => 1,
                'philhealth_monthly_basic_salary' => 1000,
                'pagibig_covered' => 1,
                'pagibig_monthly_compensation' => 1500,
            ]))
            ->assertOk()
            ->assertJsonPath('employee_contributions.sss.total', 250)
            ->assertJsonPath('employee_contributions.philhealth.total', 250)
            ->assertJsonPath('employee_contributions.pagibig.total', 15)
            ->assertJsonPath('employee_contributions_total', 515);

        $this->actingAs($this->employee)
            ->getJson('/panel/employees/contribution-preview?sss_covered=1&sss_monthly_compensation=1000')
            ->assertForbidden();
    }

    public function test_payroll_create_page_is_manager_only_and_edits_drafts_only(): void
    {
        $draft = $this->createPayroll($this->employee, Payroll::STATUS_DRAFT);
        $approved = $this->createPayroll($this->employee);

        $this->actingAs($this->manager)->get("/panel/employees/{$this->employee->id}/payrolls/create")->assertOk();
        $this->actingAs($this->manager)->get("/panel/employees/{$this->employee->id}/payrolls/create?payroll={$draft->id}")->assertOk();
        $this->actingAs($this->manager)->get("/panel/employees/{$this->employee->id}/payrolls/create?payroll={$approved->id}")->assertNotFound();
        $this->actingAs($this->employee)->get("/panel/employees/{$this->employee->id}/payrolls/create")->assertForbidden();
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

    private function createPayroll(User $employee, string $status = Payroll::STATUS_APPROVED): Payroll
    {
        return Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
            'gross_amount' => 800,
            'withholding_tax' => 0,
            'manual_deductions' => 0,
            'net_amount' => 800,
            'status' => $status,
            'generated_by' => $this->manager->id,
            'approved_by' => $status === Payroll::STATUS_APPROVED ? $this->manager->id : null,
            'approved_at' => $status === Payroll::STATUS_APPROVED ? now() : null,
        ]);
    }
}
