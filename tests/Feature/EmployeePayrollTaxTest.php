<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashAdvance;
use App\Models\Payroll;
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

    public function test_philippines_semi_monthly_payroll_uses_income_tax_in_preview_and_saved_totals(): void
    {
        $branch = $this->createBranch('Naga');
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Juan Dela Cruz');

        CashAdvance::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'amount' => 1000,
            'remaining_amount' => 1000,
            'status' => CashAdvance::STATUS_RELEASED,
            'requested_at' => '2026-02-28 09:00:00',
            'released_at' => '2026-02-28 10:00:00',
            'released_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15&gross_amount=20000&bonus=500&manual_deductions=200&cash_advance_deduction=1000")
            ->assertOk()
            ->assertJsonPath('bonus_non_taxable_amount', 500)
            ->assertJsonPath('bonus_taxable_amount', 0)
            ->assertJsonPath('income_tax', 1604.1)
            ->assertJsonPath('taxable_earnings', 20000)
            ->assertJsonPath('employee_deductions_total', 1804.1)
            ->assertJsonPath('net_amount_preview', 17695.9);

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 20000,
                'bonus' => 500,
                'manual_deductions' => 200,
                'cash_advance_deduction' => 1000,
                'notes' => 'Semi-monthly Philippines payroll.',
            ])
            ->assertCreated()
            ->assertJsonPath('income_tax', 1604.1)
            ->assertJsonPath('employee_deductions_total', 1804.1)
            ->assertJsonPath('total_earnings', 20500)
            ->assertJsonPath('net_amount', 17695.9);

        $this->assertDatabaseHas('payrolls', [
            'id' => $response->json('id'),
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'income_tax' => '1604.10',
            'manual_deductions' => '200.00',
            'cash_advance_deduction' => '1000.00',
            'net_amount' => '17695.90',
        ]);
    }

    public function test_philippines_bonus_only_taxes_the_amount_above_the_annual_exemption_cap(): void
    {
        $branch = $this->createBranch('Daet');
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Paula Reyes');

        Payroll::create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-15',
            'gross_amount' => 0,
            'bonus' => 89500,
            'income_tax' => 0,
            'pt_commission_amount' => 0,
            'pt_commission_items' => [],
            'membership_commission_amount' => 0,
            'membership_commission_items' => [],
            'manual_deductions' => 0,
            'cash_advance_deduction' => 0,
            'net_amount' => 89500,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-01&period_end=2026-03-15&gross_amount=20000&bonus=1500&manual_deductions=0&cash_advance_deduction=0")
            ->assertOk()
            ->assertJsonPath('remaining_bonus_exemption', 500)
            ->assertJsonPath('bonus_non_taxable_amount', 500)
            ->assertJsonPath('bonus_taxable_amount', 1000)
            ->assertJsonPath('taxable_earnings', 21000)
            ->assertJsonPath('income_tax', 1804.1)
            ->assertJsonPath('net_amount_preview', 19695.9);
    }

    public function test_philippines_monthly_payroll_uses_monthly_income_tax_table(): void
    {
        $branch = $this->createBranch('Legazpi');
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Maria Santos', Branch::PAYROLL_FREQUENCY_MONTHLY);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-31',
                'gross_amount' => 50000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('pay_frequency', Branch::PAYROLL_FREQUENCY_MONTHLY)
            ->assertJsonPath('income_tax', 5208.4)
            ->assertJsonPath('employee_deductions_total', 5208.4)
            ->assertJsonPath('net_amount', 44791.6);

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'pay_frequency' => Branch::PAYROLL_FREQUENCY_MONTHLY,
            'income_tax' => '5208.40',
            'net_amount' => '44791.60',
        ]);
    }

    public function test_non_ph_branches_default_to_zero_income_tax(): void
    {
        $branch = $this->createBranch('Singapore', 'SG');
        $manager = $this->createEmployeeWithRole('manager', [$branch->id], 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', [$branch->id], 'Taylor Cruz');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 50000,
                'bonus' => 0,
                'manual_deductions' => 200,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('income_tax', 0)
            ->assertJsonPath('employee_deductions_total', 200)
            ->assertJsonPath('net_amount', 49800);
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

    private function createBranch(string $name, string $countryCode = Branch::COUNTRY_PHILIPPINES): Branch
    {
        return Branch::create([
            'name' => $name,
            'status' => Branch::STATUS_OPEN,
            'country_code' => $countryCode,
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
            'pay_frequency' => $payFrequency ?? Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
        ]);

        $user->assignRole($role);
        $user->branches()->sync($branchIds);

        return $user;
    }
}
