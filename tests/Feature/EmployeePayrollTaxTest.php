<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
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
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Juan Dela Cruz');

        CashAdvance::create([
            'employee_id' => $employee->id,
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
            'income_tax' => '1604.10',
            'manual_deductions' => '200.00',
            'cash_advance_deduction' => '1000.00',
            'net_amount' => '17695.90',
        ]);
    }

    public function test_philippines_bonus_only_taxes_the_amount_above_the_annual_exemption_cap(): void
    {
        $this->setBusinessProfile('Daet', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Paula Reyes');

        Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => 'semi_monthly',
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
        $this->setBusinessProfile('Legazpi', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Maria Santos', 'monthly');

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
            ->assertJsonPath('pay_frequency', 'monthly')
            ->assertJsonPath('income_tax', 5208.4)
            ->assertJsonPath('employee_deductions_total', 5208.4)
            ->assertJsonPath('net_amount', 44791.6);

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'pay_frequency' => 'monthly',
            'income_tax' => '5208.40',
            'net_amount' => '44791.60',
        ]);
    }

    public function test_non_ph_business_profiles_default_to_zero_income_tax(): void
    {
        $this->setBusinessProfile('Singapore', 'SG');
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Taylor Cruz');

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
        $this->setBusinessProfile('Legazpi', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Maria Santos', 'monthly');

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
            ->assertJsonPath('pay_frequency', 'monthly');

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'pay_frequency' => 'monthly',
        ]);
    }

    private function setBusinessProfile(string $name, string $countryCode): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'country_code' => $countryCode,
            'status' => BusinessProfile::STATUS_OPEN,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
        ]);
    }

    private function createEmployeeWithRole(string $role, string $name, ?string $payFrequency = null): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
            'daily_rate' => 500,
            'pay_frequency' => $payFrequency ?? 'semi_monthly',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
