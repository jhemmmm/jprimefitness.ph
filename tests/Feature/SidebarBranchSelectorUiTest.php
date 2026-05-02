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

    public function test_business_and_notifications_pages_use_standard_panel_page_headers(): void
    {
        $auditHistoryContents = file_get_contents(resource_path('js/components/panel/AuditHistoryPage.vue'));
        $notificationsContents = file_get_contents(resource_path('js/components/panel/NotificationsPage.vue'));
        $settingsContents = file_get_contents(resource_path('js/components/panel/BusinessSettingsPage.vue'));

        $this->assertNotFalse($auditHistoryContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Audit History</h4>', $auditHistoryContents);
        $this->assertStringContainsString('/panel/audit-history/list', $auditHistoryContents);
        $this->assertStringContainsString('/panel/audit-history/${this.restoreTarget.id}/restore', $auditHistoryContents);
        $this->assertStringContainsString('table table-hover table-striped align-middle mb-0 panel-table', $auditHistoryContents);
        $this->assertStringContainsString("['m-badge', auditEventBadgeClass(event.event)]", $auditHistoryContents);
        $this->assertStringNotContainsString('class="dropdown d-flex justify-content-end"', $auditHistoryContents);
        $this->assertStringContainsString('bi bi-box-arrow-up-right tbl-icon', $auditHistoryContents);
        $this->assertStringContainsString('bi bi-arrow-counterclockwise tbl-icon', $auditHistoryContents);
        $this->assertStringContainsString('btn-icon-sm" data-bs-toggle="dropdown"', $auditHistoryContents);
        $this->assertStringContainsString('dropdown-menu dropdown-menu-end', $auditHistoryContents);
        $this->assertStringContainsString('hasEventActionMenu: function (event)', $auditHistoryContents);
        $this->assertStringContainsString('class="d-md-none"', $auditHistoryContents);
        $this->assertStringContainsString('class="member-card" v-for="event in events"', $auditHistoryContents);
        $this->assertStringContainsString('toggleSort', $auditHistoryContents);
        $this->assertStringContainsString('Restore Record', $auditHistoryContents);
        $this->assertStringContainsString('Not recoverable', $auditHistoryContents);

        $this->assertNotFalse($notificationsContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Notifications</h4>', $notificationsContents);
        $this->assertStringContainsString('v-if="pageError"', $notificationsContents);

        $this->assertNotFalse($settingsContents);
        $this->assertStringContainsString('<h4 class="panel-page-title mb-0">Business Settings</h4>', $settingsContents);
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
        $this->assertStringContainsString('background-color: #eef2f7;', $styles);
        $this->assertStringContainsString('-webkit-text-fill-color: #475569;', $styles);
        $this->assertStringContainsString('.form-control[readonly]:focus', $styles);
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
        $this->assertStringContainsString('Payroll wage tax', $contents);
        $this->assertStringContainsString('Government contributions', $contents);
        $this->assertStringContainsString('business-settings-shortcut', $contents);
        $this->assertStringContainsString('payroll_income_tax_enabled', $contents);
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
        $this->assertStringContainsString('<div class="sidebar-menu-heading">Business</div>', $contents);
        $this->assertStringNotContainsString("route('panel.business.cash-ledger')", $contents);
        $this->assertStringNotContainsString('<span class="sidebar-nav-label">Cash Ledger</span>', $contents);
        $this->assertStringContainsString("route('panel.business.settings')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Business Settings</span>', $contents);
        $this->assertStringNotContainsString("route('panel.business.photos')", $contents);
        $this->assertStringNotContainsString('<span class="sidebar-nav-label">Photos</span>', $contents);
        $this->assertStringContainsString('<div class="sidebar-menu-heading">System</div>', $contents);
        $this->assertStringContainsString("route('panel.audit-history')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Audit History</span>', $contents);
        $this->assertStringContainsString("route('panel.settings')", $contents);
        $this->assertStringContainsString('<span class="sidebar-nav-label">Settings</span>', $contents);
    }

    public function test_cash_advance_page_uses_shared_audit_history_endpoint(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/vendor/EmployeeCashAdvancePage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('/panel/audit-history/list', $contents);
        $this->assertStringContainsString('Open in Audit History', $contents);
        $this->assertStringNotContainsString('audit_data', $contents);
    }

    public function test_employee_payroll_page_relies_on_automatic_cash_advance_suggestions(): void
    {
        $contents = file_get_contents(resource_path('js/components/panel/vendor/EmployeePayrollPage.vue'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('Cash Advance Deduction (₱)', $contents);
        $this->assertStringNotContainsString('Auto-fill', $contents);
        $this->assertStringNotContainsString('loadSuggestedCa', $contents);
        $this->assertStringNotContainsString('loadingCa', $contents);
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
