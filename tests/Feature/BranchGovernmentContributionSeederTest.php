<?php

namespace Tests\Feature;

use App\Models\Branch;
use Database\Seeders\BranchGovernmentContributionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BranchGovernmentContributionSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_missing_philippine_government_contributions_without_overwriting_existing_values(): void
    {
        $branch = Branch::create([
            'name' => 'Naga',
            'status' => Branch::STATUS_OPEN,
            'country_code' => Branch::COUNTRY_PHILIPPINES,
            'city' => 'Naga City',
            'payroll_settings' => [
                'pay_frequency' => Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY,
                'income_tax_mode' => 'manual',
                'contributions' => [
                    [
                        'name' => 'SSS',
                        'employee_rate' => 4.7,
                        'employer_rate' => 9.7,
                        'enabled' => true,
                    ],
                ],
            ],
        ]);

        $this->seed(BranchGovernmentContributionSeeder::class);

        $branch->refresh();

        $this->assertSame(Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY, $branch->payroll_settings['pay_frequency']);
        $this->assertCount(3, $branch->payroll_settings['contributions']);
        $this->assertSame('SSS', $branch->payroll_settings['contributions'][0]['name']);
        $this->assertSame(4.7, (float) $branch->payroll_settings['contributions'][0]['employee_rate']);
        $this->assertSame('PhilHealth', $branch->payroll_settings['contributions'][1]['name']);
        $this->assertSame('PAG-IBIG', $branch->payroll_settings['contributions'][2]['name']);
        $this->assertSame(50.0, (float) $branch->payroll_settings['contributions'][2]['employee_min_amount']);
        $this->assertSame(50.0, (float) $branch->payroll_settings['contributions'][2]['employer_min_amount']);
    }
}
