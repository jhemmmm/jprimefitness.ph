<?php

use App\Services\Payroll\Profiles\NullPayrollTaxProfile;
use App\Services\Payroll\Profiles\PhilippinesPayrollTaxProfile;

return [
    'tax_profiles' => [
        'PH' => PhilippinesPayrollTaxProfile::class,
    ],

    'default_tax_profile' => NullPayrollTaxProfile::class,
];
