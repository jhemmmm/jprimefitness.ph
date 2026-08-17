<?php

namespace Tests\Feature;

use Tests\TestCase;

class SingleBusinessUiTest extends TestCase
{
    public function test_panel_bootstrap_registers_expected_panel_components(): void
    {
        $contents = file_get_contents(resource_path('js/app.js'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('global-search', $contents);
        $this->assertStringContainsString('panel-notifications', $contents);
    }

    public function test_panel_pages_receive_business_profile_directly(): void
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
            resource_path('views/panel/reports/payroll.blade.php'),
        ];

        foreach ($pages as $page) {
            $contents = file_get_contents($page);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('businesses-data', $contents, $page);
        }
    }

    public function test_panel_components_do_not_define_business_collection_props(): void
    {
        $components = [
            resource_path('js/components/panel/AttendancePage.vue'),
            resource_path('js/components/panel/EmployeeDetailPage.vue'),
            resource_path('js/components/panel/EmployeesPage.vue'),
            resource_path('js/components/panel/InventoryPage.vue'),
            resource_path('js/components/panel/MemberDetailPage.vue'),
            resource_path('js/components/panel/MembersPage.vue'),
            resource_path('js/components/panel/vendor/EmployeeSettingsPage.vue'),
            resource_path('js/components/panel/vendor/MemberAttendancePage.vue'),
            resource_path('js/components/panel/vendor/MemberSettingsPage.vue'),
        ];

        foreach ($components as $component) {
            $contents = file_get_contents($component);

            $this->assertNotFalse($contents);
            $this->assertStringNotContainsString('businessesData', $contents, $component);
        }
    }

    public function test_panel_components_do_not_reference_removed_location_payload_fields(): void
    {
        $components = [
            resource_path('js/components/panel/AttendancePage.vue'),
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
            $this->assertStringNotContainsString('location_id', $contents, $component);
            $this->assertStringNotContainsString('location_name', $contents, $component);
            $this->assertStringNotContainsString('location_breakdown', $contents, $component);
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
            $this->assertStringNotContainsString('currentLocationName', $contents, $component);
        }
    }

    public function test_business_and_notifications_pages_use_standard_panel_page_headers(): void
    {
        $systemActivityContents = file_get_contents(resource_path('js/components/panel/SystemActivityPage.vue'));
        $notificationsContents = file_get_contents(resource_path('js/components/panel/NotificationsPage.vue'));
        $settingsContents = file_get_contents(resource_path('js/components/panel/BusinessSettingsPage.vue'));

        $this->assertNotFalse($systemActivityContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">System Activity</h4>', $systemActivityContents);
        $this->assertStringContainsString('/panel/system-activity/list', $systemActivityContents);
        $this->assertStringContainsString('/panel/system-activity/${this.restoreTarget.id}/restore', $systemActivityContents);
        $this->assertStringContainsString('table table-hover table-striped align-middle mb-0 panel-table', $systemActivityContents);
        $this->assertStringContainsString("['m-badge', systemActivityBadgeClass(event.event)]", $systemActivityContents);
        $this->assertStringNotContainsString('class="dropdown d-flex justify-content-end"', $systemActivityContents);
        $this->assertStringContainsString('bi bi-box-arrow-up-right tbl-icon', $systemActivityContents);
        $this->assertStringContainsString('bi bi-arrow-counterclockwise tbl-icon', $systemActivityContents);
        $this->assertStringContainsString('btn-icon-sm" data-bs-toggle="dropdown"', $systemActivityContents);
        $this->assertStringContainsString('dropdown-menu dropdown-menu-end', $systemActivityContents);
        $this->assertStringContainsString('hasEventActionMenu: function (event)', $systemActivityContents);
        $this->assertStringContainsString('class="d-md-none"', $systemActivityContents);
        $this->assertStringContainsString('class="member-card" v-for="event in events"', $systemActivityContents);
        $this->assertStringContainsString('toggleSort', $systemActivityContents);
        $this->assertStringContainsString('Restore Record', $systemActivityContents);
        $this->assertStringContainsString('Not recoverable', $systemActivityContents);

        $this->assertNotFalse($notificationsContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Notifications</h4>', $notificationsContents);
        $this->assertStringContainsString('v-if="pageError"', $notificationsContents);

        $this->assertNotFalse($settingsContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Settings</h4>', $settingsContents);
        $this->assertStringNotContainsString('Current Profile Snapshot', $settingsContents);
        $this->assertStringNotContainsString('BusinessInformationPage', $settingsContents);
    }

    public function test_members_mobile_cards_keep_link_styling_and_remove_view_details_menu_action(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/MembersPage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('class="member-card-identity text-decoration-none"', $contents);
        $this->assertStringNotContainsString('text-decoration-none text-reset', $contents);
        $this->assertStringNotContainsString('View details', $contents);
        $this->assertStringContainsString('<i class="bi bi-pencil me-2"></i>Edit', $contents);
    }

    public function test_panel_styles_make_readonly_and_disabled_text_inputs_visually_distinct(): void
    {
        $styles = file_get_contents(resource_path('sass/panel.scss'));

        $this->assertNotFalse($styles);
        $this->assertStringContainsString('.form-control:disabled,', $styles);
        $this->assertStringContainsString('.form-control[readonly]', $styles);
        $this->assertStringContainsString('background-color: rgba(var(--bs-light-rgb)) !important;', $styles);
        $this->assertStringContainsString('.form-control[readonly]:focus', $styles);
        $this->assertStringContainsString('background-color: #eef2f7;', $styles);
        $this->assertStringContainsString('box-shadow: none;', $styles);
        $this->assertStringContainsString('background-color: #1f2937;', $styles);
    }

    public function test_business_settings_form_groups_related_fields_into_sections(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/vendor/BusinessSettingsForm.vue'));
        $styles = file_get_contents(resource_path('sass/panel.scss'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('Business identity', $contents);
        $this->assertStringContainsString('Location & hours', $contents);
        $this->assertStringNotContainsString('Contact & social', $contents);
        $this->assertStringNotContainsString('Amenities & media', $contents);
        $this->assertStringNotContainsString('Website copy', $contents);
        $this->assertStringNotContainsString('Manage Photos', $contents);
        $this->assertStringContainsString('Withholding tax', $contents);
        $this->assertStringContainsString('Government contributions', $contents);
        $this->assertStringContainsString('business-settings-shortcut', $contents);
        $this->assertStringContainsString('payroll_withholding_tax_enabled', $contents);
        $this->assertStringContainsString('payroll_government_contributions_enabled', $contents);
        $this->assertStringNotContainsString('Current Gallery Size', $contents);

        $this->assertNotFalse($styles);
        $this->assertStringContainsString('.business-settings-sidebar-sticky', $styles);
        $this->assertStringContainsString('.business-settings-shortcut', $styles);
    }

    public function test_panel_sidebar_groups_business_and_system_navigation_items(): void
    {
        $contents = file_get_contents(resource_path('views/panel/layouts/app.blade.php'));

        $this->assertNotFalse($contents);
        $this->assertStringNotContainsString('<div class="sidebar-menu-heading">Business</div>', $contents);
        $this->assertStringNotContainsString("route('panel.business.cash-ledger')", $contents);
        $this->assertStringNotContainsString('<span class="sidebar-nav-label">Cash Ledger</span>', $contents);
        $this->assertStringContainsString("route('panel.business.settings')", $contents);
        $this->assertStringNotContainsString('<span class="sidebar-nav-label">Business Settings</span>', $contents);
        $this->assertStringNotContainsString("route('panel.business.photos')", $contents);
        $this->assertStringNotContainsString('<span class="sidebar-nav-label">Photos</span>', $contents);
        $this->assertStringContainsString('<div class="sidebar-menu-heading">System</div>', $contents);
        $this->assertStringContainsString("route('panel.system-activity')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">System Activity</span>', $contents);
        $this->assertStringNotContainsString("request()->routeIs('panel.programs.*')", $contents);
        $this->assertStringNotContainsString('<span class="sidebar-nav-label">Programs</span>', $contents);
        $this->assertStringNotContainsString("request()->routeIs('panel.settings')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Settings</span>', $contents);
    }

    public function test_payroll_document_page_contains_only_current_payroll_inputs(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/PayrollDocumentPage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('Other deductions', $contents);
        $this->assertStringNotContainsString('Auto-fill', $contents);
    }

    public function test_mobile_notifications_and_payroll_document_have_responsive_layout_contracts(): void
    {
        $payrollContents = file_get_contents(resource_path('js/components/panel/PayrollDocumentPage.vue'));
        $panelStyles = file_get_contents(resource_path('sass/panel.scss'));

        $this->assertNotFalse($payrollContents);
        $this->assertStringContainsString('class="table-responsive d-none d-md-block mb-3"', $payrollContents);
        $this->assertStringContainsString('class="doc-mobile-card" v-for="day in suggestionDays"', $payrollContents);
        $this->assertStringContainsString('class="doc-mobile-card" v-for="(sale, index) in commissionSales"', $payrollContents);
        $this->assertStringContainsString('class="doc-breakdown doc-breakdown--compensation d-none d-md-table"', $payrollContents);
        $this->assertStringContainsString('class="d-md-none doc-compensation-mobile"', $payrollContents);
        $this->assertStringContainsString('for="payroll-mobile-gross"', $payrollContents);
        $this->assertStringContainsString('class="d-flex justify-content-end gap-2 border-top pt-3 doc-actions"', $payrollContents);

        $this->assertNotFalse($panelStyles);
        $this->assertStringContainsString(".notifications-dropdown {\n        position: fixed;\n        top: calc(#{\$topbar-height} + 8px);", $panelStyles);
        $this->assertStringContainsString('top: calc(#{$topbar-height} + 8px);', $panelStyles);
        $this->assertStringContainsString('right: max(12px, env(safe-area-inset-right));', $panelStyles);
        $this->assertStringContainsString('left: max(12px, env(safe-area-inset-left));', $panelStyles);
        $this->assertStringContainsString('max-height: calc(100dvh - #{$topbar-height} - 24px - env(safe-area-inset-bottom));', $panelStyles);
    }

    public function test_employee_settings_page_splits_information_and_payroll_controls(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/vendor/EmployeeSettingsPage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('<div class="col-xl-8">', $contents);
        $this->assertStringContainsString('<div class="fw-semibold mb-1">Employee Information</div>', $contents);
        $this->assertStringNotContainsString('currentLocationName', $contents);
        $this->assertStringNotContainsString('value="currentLocationName"', $contents);
        $this->assertStringNotContainsString('border rounded-3 p-3 bg-light h-100', $contents);
        $this->assertStringContainsString('New Password', $contents);
        $this->assertStringContainsString('<div class="col-xl-4">', $contents);
        $this->assertStringContainsString('<section class="border rounded-3 p-3 bg-light">', $contents);
        $this->assertStringContainsString('<div class="fw-semibold">Biometric Fingerprint</div>', $contents);
        $this->assertStringContainsString('<section class="border rounded-3 p-3 bg-light" v-if="isPhilippinesPayroll">', $contents);
        $this->assertStringContainsString('<contribution-settings-fields', $contents);
        $this->assertStringNotContainsString('Biometric Attendance', $contents);
    }

    public function test_removed_panel_files_are_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/components/panel/_vendor/businessFormOptions.js'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/AsyncSearchSelect.vue'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/GlobalSearch.vue'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/MultiSelect.vue'));
        $this->assertFileExists(resource_path('js/components/panel/vendor/PanelNotifications.vue'));
        $this->assertDirectoryDoesNotExist(resource_path('js/components/panel/_vendor'));
        $this->assertFileDoesNotExist(resource_path('js/components/panel/SettingsPage.vue'));
        $this->assertFileDoesNotExist(resource_path('views/panel/settings.blade.php'));
    }
}
