<?php

namespace Tests\Unit\Payroll;

use App\Models\Branch;
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

    /**
     * @return array<string, array{0: string, 1: float, 2: float}>
     */
    public static function incomeTaxProvider(): array
    {
        return [
            'semi-monthly exempt ceiling' => [Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY, 10417.0, 0.0],
            'semi-monthly next bracket floor' => [Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY, 16667.0, 937.50],
            'semi-monthly centavo rounding' => [Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY, 10417.5, 0.08],
            'monthly exempt ceiling' => [Branch::PAYROLL_FREQUENCY_MONTHLY, 20833.0, 0.0],
            'monthly next bracket floor' => [Branch::PAYROLL_FREQUENCY_MONTHLY, 33333.0, 1875.00],
            'monthly top bracket floor' => [Branch::PAYROLL_FREQUENCY_MONTHLY, 666667.0, 183541.80],
        ];
    }
}
