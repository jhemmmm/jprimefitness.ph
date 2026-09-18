<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\Profiles\NullPayrollTaxProfile;
use App\Services\Payroll\Profiles\PhilippinesPayrollTaxProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhilippinesPayrollTaxProfileTest extends TestCase
{
    #[DataProvider('incomeTaxProvider')]
    public function test_it_calculates_withholding_tax_for_supported_pay_frequencies(
        string $payFrequency,
        float $taxableEarnings,
        float $expectedWithholdingTax
    ): void {
        $profile = new PhilippinesPayrollTaxProfile();

        $this->assertSame($expectedWithholdingTax, $profile->calculateWithholdingTax($payFrequency, $taxableEarnings));
    }

    public function test_it_returns_zero_for_unsupported_pay_frequency(): void
    {
        $profile = new PhilippinesPayrollTaxProfile();

        $this->assertSame(0.0, $profile->calculateWithholdingTax('weekly', 50000));
    }

    public function test_it_uses_the_stored_monthly_amounts_for_monthly_payroll(): void
    {
        $result = (new PhilippinesPayrollTaxProfile())->calculateGovernmentContributions('monthly', self::LEGAL_MINIMUM_INPUTS);

        $this->assertSame([
            'sss' => ['label' => 'SSS', 'total' => 250.0],
            'philhealth' => ['label' => 'PhilHealth', 'total' => 250.0],
            'pagibig' => ['label' => 'Pag-IBIG', 'total' => 100.0],
        ], $result['employee_contributions']);
        $this->assertSame([
            'sss' => ['label' => 'SSS', 'total' => 500.0],
            'philhealth' => ['label' => 'PhilHealth', 'total' => 250.0],
            'pagibig' => ['label' => 'Pag-IBIG', 'total' => 100.0],
        ], $result['employer_contributions']);
        $this->assertSame(600.0, $result['employee_contributions_total']);
        $this->assertSame(850.0, $result['employer_contributions_total']);
    }

    public function test_it_halves_the_monthly_amounts_for_semi_monthly_payroll(): void
    {
        $result = (new PhilippinesPayrollTaxProfile())->calculateGovernmentContributions('semi_monthly', self::LEGAL_MINIMUM_INPUTS);

        $this->assertSame(125.0, $result['employee_contributions']['sss']['total']);
        $this->assertSame(125.0, $result['employee_contributions']['philhealth']['total']);
        $this->assertSame(50.0, $result['employee_contributions']['pagibig']['total']);
        $this->assertSame(250.0, $result['employer_contributions']['sss']['total']);
        $this->assertSame(125.0, $result['employer_contributions']['philhealth']['total']);
        $this->assertSame(50.0, $result['employer_contributions']['pagibig']['total']);
        $this->assertSame(300.0, $result['employee_contributions_total']);
        $this->assertSame(425.0, $result['employer_contributions_total']);
    }

    public function test_it_omits_uncovered_programs(): void
    {
        $result = (new PhilippinesPayrollTaxProfile())->calculateGovernmentContributions('monthly', [
            ...self::LEGAL_MINIMUM_INPUTS,
            'philhealth_covered' => false,
            'pagibig_covered' => false,
        ]);

        $this->assertSame(['sss'], array_keys($result['employee_contributions']));
        $this->assertSame(250.0, $result['employee_contributions_total']);
        $this->assertSame(500.0, $result['employer_contributions_total']);

        $none = (new PhilippinesPayrollTaxProfile())->calculateGovernmentContributions('monthly', []);

        $this->assertSame([], $none['employee_contributions']);
        $this->assertSame(0.0, $none['employer_contributions_total']);
    }

    public function test_null_profile_returns_no_contributions(): void
    {
        $result = (new NullPayrollTaxProfile())->calculateGovernmentContributions('monthly', self::LEGAL_MINIMUM_INPUTS);

        $this->assertSame([], $result['employee_contributions']);
        $this->assertSame([], $result['employer_contributions']);
        $this->assertSame(0.0, $result['employee_contributions_total']);
        $this->assertSame(0.0, $result['employer_contributions_total']);
    }

    private const LEGAL_MINIMUM_INPUTS = [
        'sss_covered' => true,
        'sss_employee_share' => 250,
        'sss_employer_share' => 500,
        'philhealth_covered' => true,
        'philhealth_employee_share' => 250,
        'philhealth_employer_share' => 250,
        'pagibig_covered' => true,
        'pagibig_employee_share' => 100,
        'pagibig_employer_share' => 100,
    ];

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
