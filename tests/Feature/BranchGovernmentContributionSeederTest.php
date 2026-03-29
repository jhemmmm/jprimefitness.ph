<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BranchGovernmentContributionSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_branches_table_no_longer_has_payroll_settings_column(): void
    {
        $this->assertFalse(Schema::hasColumn('branches', 'payroll_settings'));
    }
}
