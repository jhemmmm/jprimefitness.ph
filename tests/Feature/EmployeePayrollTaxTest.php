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

    public function test_payroll_net_amount_only_uses_manual_deductions_and_cash_advance(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Juan Dela Cruz');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 10000,
                'bonus' => 500,
                'manual_deductions' => 200,
                'cash_advance_deduction' => 0,
                'notes' => 'Simple payroll flow.',
            ])
            ->assertCreated()
            ->assertJsonPath('employee_deductions_total', 200)
            ->assertJsonPath('total_earnings', 10500)
            ->assertJsonPath('net_amount', 10300)
            ->assertJsonMissingPath('income_tax')
            ->assertJsonMissingPath('employee_contributions')
            ->assertJsonMissingPath('employer_contributions');

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'manual_deductions' => 200,
            'cash_advance_deduction' => 0,
            'net_amount' => 10300,
        ]);
    }

    public function test_payroll_snapshots_the_employee_pay_frequency(): void
    {
        $branch = $this->createBranch('Legazpi');
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Maria Santos', Branch::PAYROLL_FREQUENCY_MONTHLY);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-31',
                'gross_amount' => 10000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('pay_frequency', Branch::PAYROLL_FREQUENCY_MONTHLY);

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
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
    private function createEmployeeWithRole(string $role, array $branchIds, string $name, ?string $payFrequency = null): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
            'pay_frequency' => $payFrequency,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
