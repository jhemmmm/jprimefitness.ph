<?php

namespace App\Services\Payroll\Profiles;

use App\Services\Payroll\Contracts\PayrollTaxProfile;

class NullPayrollTaxProfile implements PayrollTaxProfile
{
    public function calculateIncomeTax(?string $payFrequency, float $taxableEarnings): float
    {
        return 0.0;
    }
}
