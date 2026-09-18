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

    public function test_philippines_semi_monthly_payroll_deducts_half_of_the_monthly_contributions_on_each_cutoff(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Juan Dela Cruz',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        foreach ([['2026-03-01', '2026-03-15'], ['2026-03-16', '2026-03-31']] as [$periodStart, $periodEnd]) {
            $this->actingAs($manager)
                ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start={$periodStart}&period_end={$periodEnd}&gross_amount=20000&manual_deductions=200")
                ->assertOk()
                ->assertJsonMissingPath('bonus_non_taxable_amount')
                ->assertJsonPath('employee_contributions.sss.total', 125)
                ->assertJsonPath('employee_contributions.philhealth.total', 125)
                ->assertJsonPath('employee_contributions.pagibig.total', 50)
                ->assertJsonPath('employee_contributions_total', 300)
                ->assertJsonPath('employer_contributions.sss.total', 250)
                ->assertJsonPath('employer_contributions.philhealth.total', 125)
                ->assertJsonPath('employer_contributions.pagibig.total', 50)
                ->assertJsonPath('employer_contributions_total', 425)
                ->assertJsonPath('taxable_earnings', 19700)
                ->assertJsonPath('withholding_tax', 1544.1)
                ->assertJsonPath('employee_deductions_total', 2044.1)
                ->assertJsonPath('net_amount_preview', 17955.9);

            $response = $this->actingAs($manager)
                ->postJson("/panel/employees/{$employee->id}/payrolls", [
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'gross_amount' => 20000,
                    'manual_deductions' => 200,
                ])
                ->assertCreated()
                ->assertJsonPath('withholding_tax', 1544.1)
                ->assertJsonPath('employee_contributions_total', 300)
                ->assertJsonPath('employer_contributions_total', 425)
                ->assertJsonPath('employee_deductions_total', 2044.1)
                ->assertJsonPath('total_earnings', 20000)
                ->assertJsonPath('net_amount', 17955.9);

            $this->assertDatabaseHas('payrolls', [
                'id' => $response->json('id'),
                'employee_id' => $employee->id,
                'withholding_tax' => '1544.10',
                'manual_deductions' => '200.00',
                'net_amount' => '17955.90',
            ]);

            $payroll = Payroll::findOrFail($response->json('id'));

            $this->assertSame('SSS', data_get($payroll->employee_contributions, 'sss.label'));
            $this->assertSame(125.0, (float) data_get($payroll->employee_contributions, 'sss.total'));
            $this->assertSame(250.0, (float) data_get($payroll->employer_contributions, 'sss.total'));
        }
    }

    public function test_philippines_monthly_payroll_deducts_the_full_monthly_contributions(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Ana Rivera',
            'monthly',
            $this->philippinesContributionProfile()
        );

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions.sss.total', 250)
            ->assertJsonPath('employee_contributions.philhealth.total', 250)
            ->assertJsonPath('employee_contributions.pagibig.total', 100)
            ->assertJsonPath('employee_contributions_total', 600)
            ->assertJsonPath('employer_contributions.sss.total', 500)
            ->assertJsonPath('employer_contributions_total', 850)
            ->assertJsonPath('withholding_tax', 0)
            ->assertJsonPath('employee_deductions_total', 600)
            ->assertJsonPath('net_amount', 19400);
    }

    public function test_changing_contribution_amounts_does_not_recalculate_approved_payrolls(): void
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
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-15',
                'gross_amount' => 20000,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions_total', 300)
            ->json('id');

        $approvedPayroll = Payroll::findOrFail($approvedPayrollId);
        $approvedPayroll->update([
            'status' => Payroll::STATUS_APPROVED,
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $employee->employeeProfile()->update([
            'sss_employee_share' => 500,
            'sss_employer_share' => 1000,
        ]);
        $employee->unsetRelation('employeeProfile');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('employee_contributions.sss.total', 250)
            ->assertJsonPath('employee_contributions_total', 425)
            ->assertJsonPath('employer_contributions_total', 675);

        $approvedPayroll->refresh();

        $this->assertSame(Payroll::STATUS_APPROVED, $approvedPayroll->status);
        $this->assertSame(300.0, $approvedPayroll->employeeContributionsTotal());
        $this->assertSame(425.0, $approvedPayroll->employerContributionsTotal());
        $this->assertSame(18155.9, (float) $approvedPayroll->net_amount);
    }

    public function test_philippines_monthly_payroll_uses_monthly_withholding_tax_table(): void
    {
        $this->setBusinessProfile('Legazpi', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole('staff', 'Maria Santos', 'monthly');

        $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-01',
                'period_end' => '2026-03-31',
                'gross_amount' => 50000,
                'manual_deductions' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('pay_frequency', 'monthly')
            ->assertJsonPath('withholding_tax', 5208.4)
            ->assertJsonPath('employee_deductions_total', 5208.4)
            ->assertJsonPath('net_amount', 44791.6);

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee->id,
            'pay_frequency' => 'monthly',
            'withholding_tax' => '5208.40',
            'net_amount' => '44791.60',
        ]);
    }

    public function test_business_toggle_can_disable_payroll_wage_tax(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES, [
            'payroll_withholding_tax_enabled' => false,
        ]);
        $manager = $this->createEmployeeWithRole('manager', 'Payroll Manager');
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Cora Villanueva',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&manual_deductions=200")
            ->assertOk()
            ->assertJsonPath('withholding_tax', 0)
            ->assertJsonPath('employee_contributions_total', 300)
            ->assertJsonPath('employee_deductions_total', 500)
            ->assertJsonPath('net_amount_preview', 19500);
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

        $this->actingAs($manager)
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&manual_deductions=200")
            ->assertOk()
            ->assertJsonPath('withholding_tax', 1604.1)
            ->assertJsonPath('employee_contributions', [])
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions', [])
            ->assertJsonPath('employer_contributions_total', 0)
            ->assertJsonPath('employee_deductions_total', 1804.1)
            ->assertJsonPath('net_amount_preview', 18195.9);
    }

    public function test_business_toggles_can_disable_wage_tax_and_government_contributions_together(): void
    {
        $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES, [
            'payroll_withholding_tax_enabled' => false,
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
            ->getJson("/panel/employees/{$employee->id}/payrolls/suggest?period_start=2026-03-16&period_end=2026-03-31&gross_amount=20000&manual_deductions=200")
            ->assertOk()
            ->assertJsonPath('withholding_tax', 0)
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions_total', 0)
            ->assertJsonPath('employee_deductions_total', 200)
            ->assertJsonPath('net_amount_preview', 19800);
    }

    public function test_non_ph_business_profiles_default_to_zero_withholding_tax(): void
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
                'manual_deductions' => 200,
            ])
            ->assertCreated()
            ->assertJsonPath('withholding_tax', 0)
            ->assertJsonPath('employee_contributions_total', 0)
            ->assertJsonPath('employer_contributions_total', 0)
            ->assertJsonPath('employee_deductions_total', 200)
            ->assertJsonPath('net_amount', 49800);
    }

    public function test_updating_business_toggles_does_not_recalculate_existing_approved_payroll_snapshots(): void
    {
        $profile = $this->setBusinessProfile('Naga', BusinessProfile::COUNTRY_PHILIPPINES);
        $manager = $this->createEmployeeWithRole('admin', 'Payroll Admin'); // business settings are admin-only
        $employee = $this->createEmployeeWithRole(
            'staff',
            'Noel Santos',
            'semi_monthly',
            $this->philippinesContributionProfile()
        );

        $payrollId = $this->actingAs($manager)
            ->postJson("/panel/employees/{$employee->id}/payrolls", [
                'period_start' => '2026-03-16',
                'period_end' => '2026-03-31',
                'gross_amount' => 20000,
                'manual_deductions' => 200,
            ])
            ->assertCreated()
            ->assertJsonPath('withholding_tax', 1544.1)
            ->assertJsonPath('employee_contributions_total', 300)
            ->assertJsonPath('employer_contributions_total', 425)
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
                'payroll_withholding_tax_enabled' => false,
                'payroll_government_contributions_enabled' => false,
                'city' => $profile->city,
                'province' => $profile->province,
                'address' => $profile->address,
                'timezone' => $profile->timezone,
                'amenities' => $profile->amenities ?? [],
                'opening_time' => $profile->opening_time,
                'closing_time' => $profile->closing_time,
                'operating_hours' => $profile->operating_hours ?? BusinessProfile::defaultOperatingHours(),
            ])
            ->assertOk()
            ->assertJsonPath('payroll_withholding_tax_enabled', false)
            ->assertJsonPath('payroll_government_contributions_enabled', false);

        $payroll->refresh();

        $this->assertSame(Payroll::STATUS_APPROVED, $payroll->status);
        $this->assertSame(1544.1, (float) $payroll->withholding_tax);
        $this->assertSame(300.0, $payroll->employeeContributionsTotal());
        $this->assertSame(425.0, $payroll->employerContributionsTotal());
        $this->assertSame(17955.9, (float) $payroll->net_amount);
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
                'manual_deductions' => 0,
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
            'payroll_withholding_tax_enabled' => true,
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
     * Covered on all programs; the factory supplies the legal-minimum amounts.
     *
     * @return array<string, bool>
     */
    private function philippinesContributionProfile(): array
    {
        return [
            'sss_covered' => true,
            'philhealth_covered' => true,
            'pagibig_covered' => true,
        ];
    }
}
