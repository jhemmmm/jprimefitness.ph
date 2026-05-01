<?php

namespace App\Services\Payroll\Contracts;

interface PayrollTaxProfile
{
    public function calculateIncomeTax(?string $payFrequency, float $taxableEarnings): float;

    /**
     * @param  array{
     *     apply?: bool,
     *     sss_covered?: bool,
     *     sss_monthly_compensation?: float|int|string|null,
     *     philhealth_covered?: bool,
     *     philhealth_monthly_basic_salary?: float|int|string|null,
     *     pagibig_covered?: bool,
     *     pagibig_monthly_compensation?: float|int|string|null
     * }  $inputs
     * @return array{
     *     employee_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employer_contributions: array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>,
     *     employee_contributions_total: float,
     *     employer_contributions_total: float
     * }
     */
    public function calculateGovernmentContributions(?string $payFrequency, array $inputs = []): array;
}
