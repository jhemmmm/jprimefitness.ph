<?php

namespace App\Services\Payroll\Contracts;

interface PayrollTaxProfile
{
    public function calculateIncomeTax(?string $payFrequency, float $taxableEarnings): float;
}
