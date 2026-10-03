<?php

namespace App\Services\Payroll\Profiles;

use App\Services\Payroll\Contracts\PayrollTaxProfile;

class NullPayrollTaxProfile implements PayrollTaxProfile
{
    public function calculateWithholdingTax(?string $payFrequency, float $taxableEarnings): float
    {
        return 0.0;
    }

    public function calculateGovernmentContributions(?string $payFrequency, array $inputs = []): array
    {
        return [
            'employee_contributions' => [],
            'employer_contributions' => [],
            'employee_contributions_total' => 0.0,
            'employer_contributions_total' => 0.0,
        ];
    }
}
