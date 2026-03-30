<?php

namespace Tests\Feature;

use Tests\TestCase;

class SidebarBranchSelectorUiTest extends TestCase
{
    public function test_filter_pages_do_not_render_local_all_branches_dropdowns(): void
    {
        $pages = [
            resource_path('js/components/panel/MembersPage.vue'),
            resource_path('js/components/panel/AttendancePage.vue'),
            resource_path('js/components/panel/WalkInsPage.vue'),
            resource_path('js/components/panel/EmployeesPage.vue'),
            resource_path('js/components/panel/InventoryPage.vue'),
        ];

        foreach ($pages as $page) {
            $contents = file_get_contents($page);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('<option value="">All Branches</option>', $contents, $page);
        }
    }

    public function test_filter_pages_do_not_render_clear_buttons(): void
    {
        $pages = [
            resource_path('js/components/panel/MembersPage.vue'),
            resource_path('js/components/panel/AttendancePage.vue'),
            resource_path('js/components/panel/WalkInsPage.vue'),
            resource_path('js/components/panel/EmployeesPage.vue'),
            resource_path('js/components/panel/InventoryPage.vue'),
            resource_path('js/components/panel/BranchesPage.vue'),
            resource_path('js/components/panel/vendor/EmployeeAttendancePage.vue'),
            resource_path('js/components/panel/vendor/MemberAttendancePage.vue'),
        ];

        foreach ($pages as $page) {
            $contents = file_get_contents($page);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('>Clear</button>', $contents, $page);
            $this->assertStringNotContainsString('clearFilters:', $contents, $page);
        }
    }
}
