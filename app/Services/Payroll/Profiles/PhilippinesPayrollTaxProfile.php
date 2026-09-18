<?php

namespace App\Services\Payroll\Profiles;

use App\Services\Payroll\Contracts\PayrollTaxProfile;

class PhilippinesPayrollTaxProfile implements PayrollTaxProfile
{
    private const PROGRAMS = [
        'sss' => 'SSS',
        'philhealth' => 'PhilHealth',
        'pagibig' => 'Pag-IBIG',
    ];

    /**
     * @var array<string, array<int, array{threshold: float, base: float, rate: float}>>
     */
    private const BRACKETS = [
        'semi_monthly' => [
            ['threshold' => 333333.0, 'base' => 91770.70, 'rate' => 0.35],
            ['threshold' => 83333.0, 'base' => 16770.70, 'rate' => 0.30],
            ['threshold' => 33333.0, 'base' => 4270.70, 'rate' => 0.25],
            ['threshold' => 16667.0, 'base' => 937.50, 'rate' => 0.20],
            ['threshold' => 10417.0, 'base' => 0.0, 'rate' => 0.15],
            ['threshold' => 0.0, 'base' => 0.0, 'rate' => 0.0],
        ],
        'monthly' => [
            ['threshold' => 666667.0, 'base' => 183541.80, 'rate' => 0.35],
            ['threshold' => 166667.0, 'base' => 33541.80, 'rate' => 0.30],
            ['threshold' => 66667.0, 'base' => 8541.80, 'rate' => 0.25],
            ['threshold' => 33333.0, 'base' => 1875.00, 'rate' => 0.20],
            ['threshold' => 20833.0, 'base' => 0.0, 'rate' => 0.15],
            ['threshold' => 0.0, 'base' => 0.0, 'rate' => 0.0],
        ],
    ];

    public function calculateWithholdingTax(?string $payFrequency, float $taxableEarnings): float
    {
        if (! is_string($payFrequency) || ! array_key_exists($payFrequency, self::BRACKETS)) {
            return 0.0;
        }

        $normalizedTaxableEarnings = round(max(0, $taxableEarnings), 2);

        foreach (self::BRACKETS[$payFrequency] as $bracket) {
            if ($normalizedTaxableEarnings < $bracket['threshold']) {
                continue;
            }

            return round($bracket['base'] + (($normalizedTaxableEarnings - $bracket['threshold']) * $bracket['rate']), 2);
        }

        return 0.0;
    }

    public function calculateGovernmentContributions(?string $payFrequency, array $inputs = []): array
    {
        $divisor = $payFrequency === 'semi_monthly' ? 2 : 1;
        $employeeContributions = [];
        $employerContributions = [];

        foreach (self::PROGRAMS as $key => $label) {
            if (! (bool) ($inputs[$key.'_covered'] ?? false)) {
                continue;
            }

            $employeeContributions[$key] = $this->program($label, $inputs[$key.'_employee_share'] ?? 0, $divisor);
            $employerContributions[$key] = $this->program($label, $inputs[$key.'_employer_share'] ?? 0, $divisor);
        }

        return [
            'employee_contributions' => $employeeContributions,
            'employer_contributions' => $employerContributions,
            'employee_contributions_total' => $this->sumPrograms($employeeContributions),
            'employer_contributions_total' => $this->sumPrograms($employerContributions),
        ];
    }

    /**
     * @return array{label: string, total: float}
     */
    private function program(string $label, float|int|string|null $monthlyAmount, int $divisor): array
    {
        return [
            'label' => $label,
            'total' => round(max(0, (float) $monthlyAmount) / $divisor, 2),
        ];
    }

    /**
     * @param  array<string, array{label: string, total: float}>  $programs
     */
    private function sumPrograms(array $programs): float
    {
        return round(collect($programs)->sum(fn (array $program): float => (float) ($program['total'] ?? 0)), 2);
    }
}
