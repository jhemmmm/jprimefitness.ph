<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\Profiles\NullPayrollTaxProfile;
use App\Services\Payroll\Profiles\PhilippinesPayrollTaxProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhilippinesPayrollTaxProfileTest extends TestCase
{
    #[DataProvider('incomeTaxProvider')]
    public function test_it_calculates_income_tax_for_supported_pay_frequencies(
        string $payFrequency,
        float $taxableEarnings,
        float $expectedIncomeTax
    ): void {
        $profile = new PhilippinesPayrollTaxProfile();

        $this->assertSame($expectedIncomeTax, $profile->calculateIncomeTax($payFrequency, $taxableEarnings));
    }

    public function test_it_returns_zero_for_unsupported_pay_frequency(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $this->assertSame(0.0, $profile->calculateIncomeTax('weekly', 50000));
    }

    public function test_it_calculates_sss_contributions_below_the_mpf_threshold(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => true,
            'sss_monthly_compensation' => 20000,
            'philhealth_covered' => false,
            'pagibig_covered' => false,
        ]);

        $this->assertSame(1000.0, $result['employee_contributions']['sss']['total']);
        $this->assertSame(2030.0, $result['employer_contributions']['sss']['total']);
        $this->assertSame(1000.0, $result['employee_contributions']['sss']['lines']['regular_ss']['amount']);
        $this->assertSame(0.0, $result['employee_contributions']['sss']['lines']['mpf']['amount']);
        $this->assertSame(2000.0, $result['employer_contributions']['sss']['lines']['regular_ss']['amount']);
        $this->assertSame(0.0, $result['employer_contributions']['sss']['lines']['mpf']['amount']);
        $this->assertSame(30.0, $result['employer_contributions']['sss']['lines']['ec']['amount']);
        $this->assertSame(1000.0, $result['employee_contributions_total']);
        $this->assertSame(2030.0, $result['employer_contributions_total']);
    }

    public function test_it_calculates_sss_contributions_above_the_mpf_threshold(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => true,
            'sss_monthly_compensation' => 20250,
            'philhealth_covered' => false,
            'pagibig_covered' => false,
        ]);

        $this->assertSame(1025.0, $result['employee_contributions']['sss']['total']);
        $this->assertSame(2080.0, $result['employer_contributions']['sss']['total']);
        $this->assertSame(1000.0, $result['employee_contributions']['sss']['lines']['regular_ss']['amount']);
        $this->assertSame(25.0, $result['employee_contributions']['sss']['lines']['mpf']['amount']);
        $this->assertSame(2000.0, $result['employer_contributions']['sss']['lines']['regular_ss']['amount']);
        $this->assertSame(50.0, $result['employer_contributions']['sss']['lines']['mpf']['amount']);
        $this->assertSame(30.0, $result['employer_contributions']['sss']['lines']['ec']['amount']);
    }

    public function test_it_calculates_philhealth_floor_contributions(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => false,
            'philhealth_covered' => true,
            'philhealth_monthly_basic_salary' => 9000,
            'pagibig_covered' => false,
        ]);

        $this->assertSame(250.0, $result['employee_contributions']['philhealth']['total']);
        $this->assertSame(250.0, $result['employer_contributions']['philhealth']['total']);
    }

    public function test_it_calculates_philhealth_ceiling_contributions(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => false,
            'philhealth_covered' => true,
            'philhealth_monthly_basic_salary' => 120000,
            'pagibig_covered' => false,
        ]);

        $this->assertSame(2500.0, $result['employee_contributions']['philhealth']['total']);
        $this->assertSame(2500.0, $result['employer_contributions']['philhealth']['total']);
    }

    public function test_it_favors_the_employer_share_when_philhealth_split_has_an_odd_cent(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => false,
            'philhealth_covered' => true,
            'philhealth_monthly_basic_salary' => 33333.33,
            'pagibig_covered' => false,
        ]);

        $this->assertSame(833.33, $result['employee_contributions']['philhealth']['lines']['premium']['amount']);
        $this->assertSame(833.34, $result['employer_contributions']['philhealth']['lines']['premium']['amount']);
    }

    public function test_it_calculates_pagibig_contributions_at_the_threshold_rate(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => false,
            'philhealth_covered' => false,
            'pagibig_covered' => true,
            'pagibig_monthly_compensation' => 1500,
        ]);

        $this->assertSame(15.0, $result['employee_contributions']['pagibig']['total']);
        $this->assertSame(30.0, $result['employer_contributions']['pagibig']['total']);
    }

    public function test_it_caps_pagibig_contributions_at_the_statutory_base(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => false,
            'philhealth_covered' => false,
            'pagibig_covered' => true,
            'pagibig_monthly_compensation' => 10000,
        ]);

        $this->assertSame(200.0, $result['employee_contributions']['pagibig']['total']);
        $this->assertSame(200.0, $result['employer_contributions']['pagibig']['total']);
    }

    public function test_null_profile_returns_zero_government_contributions(): void
    {
        $profile = new NullPayrollTaxProfile();

        $result = $profile->calculateGovernmentContributions('monthly', [
            'apply' => true,
            'sss_covered' => true,
            'sss_monthly_compensation' => 25000,
            'philhealth_covered' => true,
            'philhealth_monthly_basic_salary' => 25000,
            'pagibig_covered' => true,
            'pagibig_monthly_compensation' => 25000,
        ]);

        $this->assertSame([], $result['employee_contributions']);
        $this->assertSame([], $result['employer_contributions']);
        $this->assertSame(0.0, $result['employee_contributions_total']);
        $this->assertSame(0.0, $result['employer_contributions_total']);
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: float}>
     */
    public static function incomeTaxProvider(): array
    {
        return [
            'semi-monthly exempt ceiling' => ['semi_monthly', 10417.0, 0.0],
            'semi-monthly next bracket floor' => ['semi_monthly', 16667.0, 937.50],
            'semi-monthly centavo rounding' => ['semi_monthly', 10417.5, 0.08],
            'monthly exempt ceiling' => ['monthly', 20833.0, 0.0],
            'monthly next bracket floor' => ['monthly', 33333.0, 1875.00],
            'monthly top bracket floor' => ['monthly', 666667.0, 183541.80],
        ];
    }
}
