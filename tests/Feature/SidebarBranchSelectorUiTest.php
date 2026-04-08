<?php

namespace Tests\Feature;

use Tests\TestCase;

class SidebarBranchSelectorUiTest extends TestCase
{
    public function test_panel_bootstrap_no_longer_registers_branch_selector_components(): void
    {
        $contents = file_get_contents(resource_path('js/app.js'));

        $this->assertNotFalse($contents);
        $this->assertStringNotContainsString('branch-selector', $contents);
        $this->assertStringNotContainsString('BranchSelector', $contents);
        $this->assertStringNotContainsString('BranchSelectorCollapsed', $contents);
    }

    public function test_single_location_pages_receive_business_profile_instead_of_branches_data(): void
    {
        $pages = [
            resource_path('views/panel/dashboard.blade.php'),
            resource_path('views/panel/reports/attendance.blade.php'),
            resource_path('views/panel/reports/sales.blade.php'),
            resource_path('views/panel/reports/financial.blade.php'),
            resource_path('views/panel/reports/payroll.blade.php'),
        ];

        foreach ($pages as $page) {
            $contents = file_get_contents($page);

            $this->assertNotFalse($contents);
            $this->assertStringContainsString('business-profile', $contents, $page);
            $this->assertStringNotContainsString('branches-data', $contents, $page);
        }
    }

    public function test_removed_branch_selector_files_are_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/components/panel/_vendor/BranchSelector.vue'));
        $this->assertFileDoesNotExist(resource_path('js/components/panel/_vendor/BranchSelectorCollapsed.vue'));
        $this->assertFileDoesNotExist(resource_path('js/components/panel/BranchesPage.vue'));
    }
}
