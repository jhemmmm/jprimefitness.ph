<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePayrollTaxTest extends TestCase
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

    public function test_payroll_uses_branch_configured_contribution_names_and_rates(): void
    {
        $branch = $this->createBranch('Naga', [
            'pay_frequency' => 'semi_monthly',
            'income_tax_mode' => 'manual',
            'contributions' => [
                [
                    'name' => 'SSS',
                    'employee_rate' => 5,
                    'employer_rate' => 10,
                    'salary_floor' => null,
                    'salary_ceiling' => null,
                    'enabled' => true,
                ],
                [
                    'name' => 'Health Fund',
                    'employee_rate' => 2.5,
                    'employer_rate' => 2.5,
                    'salary_floor' => 10000,
                    'salary_ceiling' => 100000,
                    'enabled' => true,
                ],
            ],
        ]);
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Juan Dela Cruz');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 10000,
                'bonus' => 500,
                'income_tax' => 300,
                'manual_deductions' => 200,
                'cash_advance_deduction' => 0,
                'notes' => 'Custom branch contribution settings.',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions.0.name', 'SSS')
            ->assertJsonPath('employee_contributions.0.amount', 500)
            ->assertJsonPath('employee_contributions.1.name', 'Health Fund')
            ->assertJsonPath('employee_contributions.1.amount', 250)
            ->assertJsonPath('employer_contributions.0.amount', 1000)
            ->assertJsonPath('employer_contributions.1.amount', 250)
            ->assertJsonPath('employee_contribution_total', 750)
            ->assertJsonPath('employee_deductions_total', 1250)
            ->assertJsonPath('net_amount', 9250);

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'income_tax' => 300,
            'manual_deductions' => 200,
            'net_amount' => 9250,
        ]);
    }

    public function test_philippine_branch_without_contribution_settings_does_not_inject_defaults(): void
    {
        $branch = $this->createBranch('Legazpi', [
            'pay_frequency' => 'semi_monthly',
            'income_tax_mode' => 'manual',
            'contributions' => [],
        ]);
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Maria Santos');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 10000,
                'bonus' => 500,
                'income_tax' => 300,
                'manual_deductions' => 200,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions', [])
            ->assertJsonPath('employer_contributions', [])
            ->assertJsonPath('employee_contribution_total', 0)
            ->assertJsonPath('employee_deductions_total', 500)
            ->assertJsonPath('net_amount', 10000);
    }

    public function test_pag_ibig_monthly_minimum_is_respected_and_split_per_payroll(): void
    {
        $branch = $this->createBranch('Iriga', [
            'pay_frequency' => 'semi_monthly',
            'income_tax_mode' => 'manual',
            'contributions' => [
                [
                    'name' => 'PAG-IBIG',
                    'employee_rate' => 2,
                    'employer_rate' => 2,
                    'employee_min_amount' => 50,
                    'employer_min_amount' => 50,
                    'salary_floor' => null,
                    'salary_ceiling' => null,
                    'enabled' => true,
                ],
            ],
        ]);
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Ana Reyes');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 1000,
                'bonus' => 0,
                'income_tax' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions.0.name', 'PAG-IBIG')
            ->assertJsonPath('employee_contributions.0.amount', 25)
            ->assertJsonPath('employer_contributions.0.amount', 25)
            ->assertJsonPath('employee_contribution_total', 25)
            ->assertJsonPath('net_amount', 975);
    }

    private function createBranch(string $name, array $payrollSettings): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => 'PH',
            'payroll_settings' => $payrollSettings,
            'city' => 'Naga City',
        ]);
    }

    /**
     * @param  array<int>  $branchIds
     */
    private function createEmployeeWithRole(string $role, array $branchIds, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
