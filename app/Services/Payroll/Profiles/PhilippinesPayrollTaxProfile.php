<?php

namespace App\Services\Payroll\Profiles;

use App\Services\Payroll\Contracts\PayrollTaxProfile;

class PhilippinesPayrollTaxProfile implements PayrollTaxProfile
{
    private const SSS_EMPLOYER_RATE = 0.10;

    private const SSS_EMPLOYEE_RATE = 0.05;

    private const SSS_REGULAR_MSC_CAP = 20000.0;

    private const SSS_TOTAL_MSC_CAP = 35000.0;

    private const SSS_MIN_TOTAL_MSC = 5000.0;

    private const SSS_FIRST_BRACKET_FLOOR = 5250.0;

    private const SSS_MPF_BRACKET_FLOOR = 20250.0;

    private const SSS_EC_SMALL = 10.0;

    private const SSS_EC_LARGE = 30.0;

    private const SSS_EC_LARGE_THRESHOLD = 15000.0;

    private const PHILHEALTH_RATE = 0.05;

    private const PHILHEALTH_FLOOR = 10000.0;

    private const PHILHEALTH_CEILING = 100000.0;

    private const PAGIBIG_EMPLOYEE_LOWER_RATE = 0.01;

    private const PAGIBIG_STANDARD_RATE = 0.02;

    private const PAGIBIG_LOWER_THRESHOLD = 1500.0;

    private const PAGIBIG_COMPENSATION_CAP = 10000.0;

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
        $apply = (bool) ($inputs['apply'] ?? true);
        $employeeContributions = [];
        $employerContributions = [];

        if ((bool) ($inputs['sss_covered'] ?? false)) {
            $sss = $this->sssContributionPrograms(
                $this->normalizeAmount($inputs['sss_monthly_compensation'] ?? 0),
                $apply
            );
            $employeeContributions['sss'] = $sss['employee'];
            $employerContributions['sss'] = $sss['employer'];
        }

        if ((bool) ($inputs['philhealth_covered'] ?? false)) {
            $philhealth = $this->philhealthContributionPrograms(
                $this->normalizeAmount($inputs['philhealth_monthly_basic_salary'] ?? 0),
                $apply
            );
            $employeeContributions['philhealth'] = $philhealth['employee'];
            $employerContributions['philhealth'] = $philhealth['employer'];
        }

        if ((bool) ($inputs['pagibig_covered'] ?? false)) {
            $pagibig = $this->pagibigContributionPrograms(
                $this->normalizeAmount($inputs['pagibig_monthly_compensation'] ?? 0),
                $apply
            );
            $employeeContributions['pagibig'] = $pagibig['employee'];
            $employerContributions['pagibig'] = $pagibig['employer'];
        }

        return [
            'employee_contributions' => $employeeContributions,
            'employer_contributions' => $employerContributions,
            'employee_contributions_total' => $this->sumPrograms($employeeContributions),
            'employer_contributions_total' => $this->sumPrograms($employerContributions),
        ];
    }

    /**
     * @return array{employee: array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}, employer: array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}}
     */
    private function sssContributionPrograms(float $monthlyCompensation, bool $apply): array
    {
        $totalMonthlySalaryCredit = $this->sssTotalMonthlySalaryCredit($monthlyCompensation);
        $regularMonthlySalaryCredit = min($totalMonthlySalaryCredit, self::SSS_REGULAR_MSC_CAP);
        $mpfMonthlySalaryCredit = max(0, $totalMonthlySalaryCredit - $regularMonthlySalaryCredit);

        $employeeLines = [
            'regular_ss' => [
                'label' => 'Regular SS',
                'amount' => $apply ? round($regularMonthlySalaryCredit * self::SSS_EMPLOYEE_RATE, 2) : 0.0,
            ],
            'mpf' => [
                'label' => 'MPF',
                'amount' => $apply ? round($mpfMonthlySalaryCredit * self::SSS_EMPLOYEE_RATE, 2) : 0.0,
            ],
        ];
        $employerLines = [
            'regular_ss' => [
                'label' => 'Regular SS',
                'amount' => $apply ? round($regularMonthlySalaryCredit * self::SSS_EMPLOYER_RATE, 2) : 0.0,
            ],
            'mpf' => [
                'label' => 'MPF',
                'amount' => $apply ? round($mpfMonthlySalaryCredit * self::SSS_EMPLOYER_RATE, 2) : 0.0,
            ],
            'ec' => [
                'label' => 'EC',
                'amount' => $apply ? $this->sssEmployeesCompensationAmount($totalMonthlySalaryCredit) : 0.0,
            ],
        ];

        return [
            'employee' => $this->program('SSS', $employeeLines),
            'employer' => $this->program('SSS', $employerLines),
        ];
    }

    /**
     * @return array{employee: array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}, employer: array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}}
     */
    private function philhealthContributionPrograms(float $monthlyBasicSalary, bool $apply): array
    {
        $totalPremium = 0.0;

        if ($monthlyBasicSalary > 0) {
            $premiumBase = min(max($monthlyBasicSalary, self::PHILHEALTH_FLOOR), self::PHILHEALTH_CEILING);
            $totalPremium = round($premiumBase * self::PHILHEALTH_RATE, 2);
        }

        $employeeShare = $apply ? floor(($totalPremium / 2) * 100) / 100 : 0.0;
        $employerShare = $apply ? round($totalPremium - $employeeShare, 2) : 0.0;

        return [
            'employee' => $this->program('PhilHealth', [
                'premium' => [
                    'label' => 'Premium',
                    'amount' => round($employeeShare, 2),
                ],
            ]),
            'employer' => $this->program('PhilHealth', [
                'premium' => [
                    'label' => 'Premium',
                    'amount' => round($employerShare, 2),
                ],
            ]),
        ];
    }

    /**
     * @return array{employee: array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}, employer: array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}}
     */
    private function pagibigContributionPrograms(float $monthlyCompensation, bool $apply): array
    {
        $compensationBase = $monthlyCompensation > 0
            ? min($monthlyCompensation, self::PAGIBIG_COMPENSATION_CAP)
            : 0.0;
        $employeeRate = $monthlyCompensation <= self::PAGIBIG_LOWER_THRESHOLD
            ? self::PAGIBIG_EMPLOYEE_LOWER_RATE
            : self::PAGIBIG_STANDARD_RATE;

        return [
            'employee' => $this->program('Pag-IBIG', [
                'contribution' => [
                    'label' => 'Contribution',
                    'amount' => $apply ? round($compensationBase * $employeeRate, 2) : 0.0,
                ],
            ]),
            'employer' => $this->program('Pag-IBIG', [
                'contribution' => [
                    'label' => 'Contribution',
                    'amount' => $apply ? round($compensationBase * self::PAGIBIG_STANDARD_RATE, 2) : 0.0,
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, array{label: string, amount: float}>  $lines
     * @return array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}
     */
    private function program(string $label, array $lines): array
    {
        return [
            'label' => $label,
            'total' => round(collect($lines)->sum(fn (array $line): float => (float) ($line['amount'] ?? 0)), 2),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, array{label: string, total: float, lines: array<string, array{label: string, amount: float}>}>  $programs
     */
    private function sumPrograms(array $programs): float
    {
        return round(collect($programs)->sum(fn (array $program): float => (float) ($program['total'] ?? 0)), 2);
    }

    private function sssTotalMonthlySalaryCredit(float $monthlyCompensation): float
    {
        if ($monthlyCompensation <= 0) {
            return 0.0;
        }

        if ($monthlyCompensation < self::SSS_FIRST_BRACKET_FLOOR) {
            return self::SSS_MIN_TOTAL_MSC;
        }

        if ($monthlyCompensation < self::SSS_MPF_BRACKET_FLOOR) {
            return self::SSS_MIN_TOTAL_MSC
                + ((floor(($monthlyCompensation - self::SSS_FIRST_BRACKET_FLOOR) / 500) + 1) * 500);
        }

        return min(
            self::SSS_TOTAL_MSC_CAP,
            20500.0 + (floor(($monthlyCompensation - self::SSS_MPF_BRACKET_FLOOR) / 500) * 500)
        );
    }

    private function sssEmployeesCompensationAmount(float $totalMonthlySalaryCredit): float
    {
        if ($totalMonthlySalaryCredit <= 0) {
            return 0.0;
        }

        return $totalMonthlySalaryCredit < self::SSS_EC_LARGE_THRESHOLD
            ? self::SSS_EC_SMALL
            : self::SSS_EC_LARGE;
    }

    private function normalizeAmount(float|int|string|null $amount): float
    {
        if ($amount === null || $amount === '') {
            return 0.0;
        }

        return round(max(0, (float) $amount), 2);
    }
}
