<?php

namespace App\Services\Payroll\Contracts;

interface PayrollTaxProfile
{
    public function calculateWithholdingTax(?string $payFrequency, float $taxableEarnings): float;

    /**
     * Monthly amounts per program; semi-monthly payrolls get half per cutoff.
     *
     * @param  array{
     *     sss_covered?: bool,
     *     sss_employee_share?: float|int|string|null,
     *     sss_employer_share?: float|int|string|null,
     *     philhealth_covered?: bool,
     *     philhealth_employee_share?: float|int|string|null,
     *     philhealth_employer_share?: float|int|string|null,
     *     pagibig_covered?: bool,
     *     pagibig_employee_share?: float|int|string|null,
     *     pagibig_employer_share?: float|int|string|null
     * }  $inputs
     * @return array{
     *     employee_contributions: array<string, array{label: string, total: float}>,
     *     employer_contributions: array<string, array{label: string, total: float}>,
     *     employee_contributions_total: float,
     *     employer_contributions_total: float
     * }
     */
    public function calculateGovernmentContributions(?string $payFrequency, array $inputs = []): array;
}
