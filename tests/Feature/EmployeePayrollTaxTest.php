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
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Juan Dela Cruz',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

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
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions_total', 0)
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
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions_total', 0)
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

    public function test_philippines_second_half_semi_monthly_payroll_snapshots_government_contributions(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Ana Rivera',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => 1000,
            'remaining_amount' => 1000,
            'status' => CashAdvance::STATUS_RELEASED,
            'requested_at' => '2026-03-15 09:00:00',
            'released_at' => '2026-03-15 10:00:00',
            'released_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&bonus=500&manual_deductions=200&cash_advance_deduction=1000")
            ->assertOk()
            ->assertJsonPath('employee_contributions.sss.total', 1025)
            ->assertJsonPath('employee_contributions.philhealth.total', 500)
            ->assertJsonPath('employee_contributions.pagibig.total', 200)
            ->assertJsonPath('employee_contributions_total', 1725)
            ->assertJsonPath('employer_contributions.sss.total', 2080)
            ->assertJsonPath('employer_contributions.philhealth.total', 500)
            ->assertJsonPath('employer_contributions.pagibig.total', 200)
            ->assertJsonPath('employer_contributions_total', 2780)
            ->assertJsonPath('taxable_earnings', 18275)
            ->assertJsonPath('income_tax', 1259.1)
            ->assertJsonPath('employee_deductions_total', 3184.1)
            ->assertJsonPath('net_amount_preview', 16315.9);

        $response = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 500,
                'manual_deductions' => 200,
                'cash_advance_deduction' => 1000,
                'notes' => 'Second half with statutory deductions.',
            ])
            ->assertCreated()
            ->assertJsonPath('income_tax', 1259.1)
            ->assertJsonPath('employee_contributions_total', 1725)
            ->assertJsonPath('employer_contributions_total', 2780)
            ->assertJsonPath('employee_deductions_total', 3184.1)
            ->assertJsonPath('net_amount', 16315.9);

        $payroll = Payroll::findOrFail($response->json('id'));

        $this->assertSame(1725.0, $payroll->employeeContributionsTotal());
        $this->assertSame(2780.0, $payroll->employerContributionsTotal());
        $this->assertSame(1000.0, (float) data_get($payroll->employee_contributions, 'sss.lines.regular_ss.amount'));
        $this->assertSame(25.0, (float) data_get($payroll->employee_contributions, 'sss.lines.mpf.amount'));
        $this->assertSame(30.0, (float) data_get($payroll->employer_contributions, 'sss.lines.ec.amount'));
    }

    public function test_philippines_semi_monthly_payroll_posts_contributions_only_to_the_later_payroll_in_the_month(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Carlo Dizon',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $firstHalfPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 0)
            ->json('id');

        $secondHalfPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->json('id');

        $firstHalfPayroll = Payroll::findOrFail($firstHalfPayrollId);
        $secondHalfPayroll = Payroll::findOrFail($secondHalfPayrollId);

        $this->assertSame(0.0, $firstHalfPayroll->employeeContributionsTotal());
        $this->assertSame(0.0, $firstHalfPayroll->employerContributionsTotal());
        $this->assertSame(1725.0, $secondHalfPayroll->employeeContributionsTotal());
        $this->assertSame(2780.0, $secondHalfPayroll->employerContributionsTotal());
    }

    public function test_philippines_semi_monthly_contributions_move_to_remaining_draft_when_later_draft_is_cancelled(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Nico Santos',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $firstHalfPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 0)
            ->json('id');

        $secondHalfPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$secondHalfPayrollId}/cancel")
            ->assertOk();

        $firstHalfPayroll = Payroll::findOrFail($firstHalfPayrollId);

        $this->assertSame(Payroll::STATUS_DRAFT, $firstHalfPayroll->status);
        $this->assertSame(1725.0, $firstHalfPayroll->employeeContributionsTotal());
        $this->assertSame(2780.0, $firstHalfPayroll->employerContributionsTotal());
        $this->assertSame(17015.9, (float) $firstHalfPayroll->net_amount);

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$firstHalfPayrollId}/approve")
            ->assertOk()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->assertJsonPath('employer_contributions_total', 2780)
            ->assertJsonPath('net_amount', 17015.9);
    }

    public function test_philippines_replacement_second_half_draft_skips_contributions_when_fallback_payroll_is_finalized(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Ramon Cruz',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $firstHalfPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 0)
            ->json('id');

        $secondHalfPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->json('id');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$secondHalfPayrollId}/cancel")
            ->assertOk();

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls/{$firstHalfPayrollId}/approve")
            ->assertOk()
            ->assertJsonPath('employee_contributions_total', 1725);

        $replacementPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions_total', 0)
            ->json('id');

        $firstHalfPayroll = Payroll::findOrFail($firstHalfPayrollId);
        $replacementPayroll = Payroll::findOrFail($replacementPayrollId);

        $this->assertSame(Payroll::STATUS_APPROVED, $firstHalfPayroll->status);
        $this->assertSame(1725.0, $firstHalfPayroll->employeeContributionsTotal());
        $this->assertSame(2780.0, $firstHalfPayroll->employerContributionsTotal());
        $this->assertSame(0.0, $replacementPayroll->employeeContributionsTotal());
        $this->assertSame(0.0, $replacementPayroll->employerContributionsTotal());
    }

    public function test_philippines_monthly_contribution_resync_does_not_recalculate_finalized_payrolls(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Lara Cruz',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $approvedPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->json('id');

        $approvedPayroll = Payroll::findOrFail($approvedPayrollId);
        $approvedPayroll->update([
            'status' => Payroll::STATUS_APPROVED,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $employee->employeeProfile()->update([
            'sss_monthly_compensation' => 10000,
            'philhealth_monthly_basic_salary' => 10000,
            'pagibig_monthly_compensation' => 10000,
        ]);
        $employee->unsetRelation('employeeProfile');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 0);

        $approvedPayroll->refresh();

        $this->assertSame(Payroll::STATUS_APPROVED, $approvedPayroll->status);
        $this->assertSame(1725.0, $approvedPayroll->employeeContributionsTotal());
        $this->assertSame(2780.0, $approvedPayroll->employerContributionsTotal());
        $this->assertSame(17015.9, (float) $approvedPayroll->net_amount);
    }

    public function test_philippines_same_day_draft_payrolls_allocate_contributions_to_the_latest_draft(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Dina Reyes',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $firstPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->json('id');

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&bonus=0&manual_deductions=0&cash_advance_deduction=0")
            ->assertOk()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->assertJsonPath('employer_contributions_total', 2780);

        $secondPayrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 0,
                'manual_deductions' => 0,
                'cash_advance_deduction' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->json('id');

        $firstPayroll = Payroll::findOrFail($firstPayrollId);
        $secondPayroll = Payroll::findOrFail($secondPayrollId);

        $this->assertSame(0.0, $firstPayroll->employeeContributionsTotal());
        $this->assertSame(0.0, $firstPayroll->employerContributionsTotal());
        $this->assertSame(1725.0, $secondPayroll->employeeContributionsTotal());
        $this->assertSame(2780.0, $secondPayroll->employerContributionsTotal());
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

    public function test_business_toggle_can_disable_payroll_wage_tax(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES, [
            'payroll_income_tax_enabled' => false,
        ]);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Cora Villanueva',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&bonus=500&manual_deductions=200&cash_advance_deduction=1000")
            ->assertOk()
            ->assertJsonPath('income_tax', 0)
            ->assertJsonPath('employee_contributions_total', 1725)
            ->assertJsonPath('employee_deductions_total', 1925)
            ->assertJsonPath('net_amount_preview', 17575);
    }

    public function test_business_toggle_can_disable_government_contributions(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES, [
            'payroll_government_contributions_enabled' => false,
        ]);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Ella Bautista',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => 20000,
            'remaining_amount' => 20000,
            'status' => CashAdvance::STATUS_RELEASED,
            'requested_at' => '2026-03-15 09:00:00',
            'released_at' => '2026-03-15 10:00:00',
            'released_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&bonus=500&manual_deductions=200&cash_advance_deduction=1000")
            ->assertOk()
            ->assertJsonPath('income_tax', 1604.1)
            ->assertJsonPath('employee_contributions', [])
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions', [])
            ->assertJsonPath('employer_contributions_total', 0)
            ->assertJsonPath('employee_deductions_total', 1804.1)
            ->assertJsonPath('net_amount_preview', 17695.9)
            ->assertJsonPath('pending_ca_total', 20000)
            ->assertJsonPath('max_cash_advance_deduction', 18695.9)
            ->assertJsonPath('suggested_ca', 18695.9);
    }

    public function test_business_toggles_can_disable_wage_tax_and_government_contributions_together(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES, [
            'payroll_income_tax_enabled' => false,
            'payroll_government_contributions_enabled' => false,
        ]);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Mia Gonzales',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&bonus=500&manual_deductions=200&cash_advance_deduction=1000")
            ->assertOk()
            ->assertJsonPath('income_tax', 0)
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions_total', 0)
            ->assertJsonPath('employee_deductions_total', 200)
            ->assertJsonPath('net_amount_preview', 19300);
    }

    public function test_non_ph_business_profiles_default_to_zero_income_tax(): void
    {
        $this->setBusinessProfile('Singapore', 'SG');
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Taylor Cruz',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

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
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions_total', 0)
            ->assertJsonPath('employee_deductions_total', 200)
            ->assertJsonPath('net_amount', 49800);
    }

    public function test_updating_business_toggles_does_not_recalculate_existing_approved_payroll_snapshots(): void
    {
        $profile = $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Noel Santos',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => 1000,
            'remaining_amount' => 1000,
            'status' => CashAdvance::STATUS_RELEASED,
            'requested_at' => '2026-03-15 09:00:00',
            'released_at' => '2026-03-15 10:00:00',
            'released_by' => $manager->id,
        ]);

        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'bonus' => 500,
                'manual_deductions' => 200,
                'cash_advance_deduction' => 1000,
            ])
            ->assertCreated()
            ->assertJsonPath('income_tax', 1259.1)
            ->assertJsonPath('employee_contributions_total', 1725)
            ->assertJsonPath('employer_contributions_total', 2780)
            ->json('id');

        $payroll = Payroll::findOrFail($payrollId);
        $payroll->update([
            'status' => Payroll::STATUS_APPROVED,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($manager)
            ->putJson('/panel/business/settings', [
                'name' => $profile->name,
                'country_code' => $profile->country_code,
                'pay_overwork_hours' => (bool) $profile->pay_overwork_hours,
                'payroll_income_tax_enabled' => false,
                'payroll_government_contributions_enabled' => false,
                'city' => $profile->city,
                'province' => $profile->province,
                'address' => $profile->address,
                'timezone' => $profile->timezone,
                'amenities' => $profile->amenities ?? [],
                'opening_time' => $profile->opening_time,
                'closing_time' => $profile->closing_time,
            ])
            ->assertOk()
            ->assertJsonPath('payroll_income_tax_enabled', false)
            ->assertJsonPath('payroll_government_contributions_enabled', false);

        $payroll->refresh();

        $this->assertSame(Payroll::STATUS_APPROVED, $payroll->status);
        $this->assertSame(1259.1, (float) $payroll->income_tax);
        $this->assertSame(1725.0, $payroll->employeeContributionsTotal());
        $this->assertSame(2780.0, $payroll->employerContributionsTotal());
        $this->assertSame(16315.9, (float) $payroll->net_amount);
    }

    public function test_cash_advance_max_deduction_accounts_for_employee_government_contributions(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Mila Cruz',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => 20000,
            'remaining_amount' => 20000,
            'status' => CashAdvance::STATUS_RELEASED,
            'requested_at' => '2026-03-10 09:00:00',
            'released_at' => '2026-03-10 10:00:00',
            'released_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&bonus=0&manual_deductions=0&cash_advance_deduction=0")
            ->assertOk()
            ->assertJsonPath('employee_contributions_total', 1725)
            ->assertJsonPath('employee_deductions_total', 2984.1)
            ->assertJsonPath('max_cash_advance_deduction', 17015.9)
            ->assertJsonPath('suggested_ca', 17015.9);
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

    private function setBusinessProfile(string $name, string $countryCode, array $overrides = []): BusinessProfile
    {
        return BusinessProfile::factory()->create([
            'name' => $name,
            'country_code' => $countryCode,
            'payroll_income_tax_enabled' => true,
            'payroll_government_contributions_enabled' => true,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $profileOverrides
     */
    private function createEmployeeWithRole(string $role, string $name, ?string $payFrequency = null, array $profileOverrides = []): User
    {
        $user = User::factory()->withEmployeeProfile([
            'daily_rate' => 500,
            'pay_frequency' => $payFrequency ?? 'semi_monthly',
            ...$profileOverrides,
        ])->create([
            'name' => $name,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function philippinesContributionProfile(): array
    {
        return [
            'sss_covered' => true,
            'sss_monthly_compensation' => 20250,
            'philhealth_covered' => true,
            'philhealth_monthly_basic_salary' => 20000,
            'pagibig_covered' => true,
            'pagibig_monthly_compensation' => 20000,
        ];
    }
}
