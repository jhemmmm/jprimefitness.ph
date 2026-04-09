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
            resource_path('views/panel/attendance.blade.php'),
            resource_path('views/panel/dashboard.blade.php'),
            resource_path('views/panel/employees/index.blade.php'),
            resource_path('views/panel/employees/show.blade.php'),
            resource_path('views/panel/inventory.blade.php'),
            resource_path('views/panel/members.blade.php'),
            resource_path('views/panel/members/show.blade.php'),
            resource_path('views/panel/reports/attendance.blade.php'),
            resource_path('views/panel/reports/sales.blade.php'),
            resource_path('views/panel/reports/financial.blade.php'),
            resource_path('views/panel/reports/payroll.blade.php'),
            resource_path('views/panel/walk-ins.blade.php'),
        ];

        foreach ($pages as $page) {
            $contents = file_get_contents($page);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('branches-data', $contents, $page);
        }
    }

    public function test_panel_components_no_longer_define_legacy_branches_data_props(): void
    {
        $components = [
            resource_path('js/components/panel/AttendancePage.vue'),
            resource_path('js/components/panel/EmployeeDetailPage.vue'),
            resource_path('js/components/panel/EmployeesPage.vue'),
            resource_path('js/components/panel/InventoryPage.vue'),
            resource_path('js/components/panel/MemberDetailPage.vue'),
            resource_path('js/components/panel/MembersPage.vue'),
            resource_path('js/components/panel/WalkInsPage.vue'),
            resource_path('js/components/panel/vendor/EmployeeSettingsPage.vue'),
            resource_path('js/components/panel/vendor/MemberAttendancePage.vue'),
            resource_path('js/components/panel/vendor/MemberSettingsPage.vue'),
        ];

        foreach ($components as $component) {
            $contents = file_get_contents($component);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('branchesData', $contents, $component);
        }
    }

    public function test_single_location_ui_components_no_longer_reference_branch_payload_fields(): void
    {
        $components = [
            resource_path('js/components/panel/AttendancePage.vue'),
            resource_path('js/components/panel/WalkInsPage.vue'),
            resource_path('js/components/panel/InventoryPage.vue'),
            resource_path('js/components/panel/NotificationsPage.vue'),
            resource_path('js/components/panel/vendor/PanelNotifications.vue'),
            resource_path('js/components/panel/vendor/MemberAttendancePage.vue'),
            resource_path('js/components/panel/vendor/EmployeeAttendancePage.vue'),
            resource_path('js/components/panel/vendor/MemberMembershipPage.vue'),
            resource_path('js/components/panel/vendor/MemberPtSessionsPage.vue'),
            resource_path('js/components/panel/vendor/EmployeePayrollPage.vue'),
        ];

        foreach ($components as $component) {
            $contents = file_get_contents($component);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('branch_id', $contents, $component);
            $this->assertStringNotContainsString('branch_name', $contents, $component);
            $this->assertStringNotContainsString('branch_country_code', $contents, $component);
            $this->assertStringNotContainsString('.branch', $contents, $component);
        }
    }

    public function test_detail_components_do_not_access_window_jprime_in_templates(): void
    {
        $components = [
            resource_path('js/components/panel/EmployeeDetailPage.vue'),
            resource_path('js/components/panel/MemberDetailPage.vue'),
        ];

        foreach ($components as $component) {
            $contents = file_get_contents($component);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('{{ window.JPrime', $contents, $component);
            $this->assertStringContainsString('currentLocationName', $contents, $component);
        }
    }

    public function test_business_cash_ledger_page_uses_standard_panel_table_layout(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/vendor/BusinessCashLedgerPage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Cash Ledger</h4>', $contents);
        $this->assertStringContainsString('table table-hover table-striped align-middle mb-0 panel-table', $contents);
        $this->assertStringNotContainsString('>Clear</button>', $contents);
    }

    public function test_business_and_notifications_pages_use_standard_panel_page_headers(): void
    {
        $notificationsContents = file_get_contents(resource_path('js/components/panel/NotificationsPage.vue'));
        $settingsContents = file_get_contents(resource_path('js/components/panel/BusinessSettingsPage.vue'));
        $galleryContents = file_get_contents(resource_path('js/components/panel/vendor/BusinessGalleryPage.vue'));

        $this->assertNotFalse($notificationsContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Notifications</h4>', $notificationsContents);
        $this->assertStringContainsString('v-if="pageError"', $notificationsContents);

        $this->assertNotFalse($settingsContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Business Settings</h4>', $settingsContents);

        $this->assertNotFalse($galleryContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Business Gallery</h4>', $galleryContents);
        $this->assertStringContainsString('v-if="pageError"', $galleryContents);
        $this->assertStringContainsString('panel-card-title', $galleryContents);
    }

    public function test_panel_sidebar_groups_business_and_system_navigation_items(): void
    {
        $contents = file_get_contents(resource_path('views/panel/layouts/app.blade.php'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('<div class="sidebar-menu-heading">Business</div>', $contents);
        $this->assertStringContainsString("route('panel.business.cash-ledger')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Cash Ledger</span>', $contents);
        $this->assertStringContainsString("route('panel.business.settings')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Business Settings</span>', $contents);
        $this->assertStringContainsString("route('panel.business.photos')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Photos</span>', $contents);
        $this->assertStringContainsString('<div class="sidebar-menu-heading">System</div>', $contents);
        $this->assertStringContainsString("route('panel.settings')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Settings</span>', $contents);
    }

    public function test_removed_branch_selector_files_are_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/components/panel/_vendor/businessFormOptions.js'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/AsyncSearchSelect.vue'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/GlobalSearch.vue'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/MultiSelect.vue'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/PanelNotifications.vue'));
        $this->assertDirectoryDoesNotExist(resource_path('js/components/panel/_vendor'));
        $this->assertFileDoesNotExist(resource_path('js/components/panel/_vendor/BranchSelector.vue'));
        $this->assertFileDoesNotExist(resource_path('js/components/panel/_vendor/BranchSelectorCollapsed.vue'));
        $this->assertFileDoesNotExist(resource_path('js/components/panel/BranchesPage.vue'));
        $this->assertFileDoesNotExist(resource_path('js/components/panel/SettingsPage.vue'));
        $this->assertFileDoesNotExist(resource_path('views/panel/settings.blade.php'));
    }
}
