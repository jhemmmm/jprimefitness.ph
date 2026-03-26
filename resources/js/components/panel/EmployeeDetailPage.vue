<template>
    <div>
        <!-- Employee Info Card -->
        <div class="panel-card p-4 mb-4">
            <div class="d-flex align-items-start gap-4 flex-wrap">
                <div class="member-avatar employee-avatar-xl flex-shrink-0">
                    {{ initials }}
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h4 class="fw-bold mb-0">{{ localEmployee.first_name }} {{ localEmployee.last_name }}</h4>
                        <span class="m-badge" :class="roleBadge">{{ roleLabel }}</span>
                        <span class="m-badge" :class="statusBadge">{{ localEmployee.status }}</span>
                    </div>
                    <div class="text-muted small mb-2" v-if="localEmployee.branches && localEmployee.branches.length"><i class="bi bi-geo-alt me-1"></i>{{ localEmployee.branches.map((b) => b.name).join(", ") }}</div>
                    <div class="d-flex gap-3 flex-wrap small text-muted">
                        <span v-if="localEmployee.email"><i class="bi bi-envelope me-1"></i>{{ localEmployee.email }}</span>
                        <span v-if="localEmployee.phone"><i class="bi bi-telephone me-1"></i>{{ localEmployee.phone }}</span>
                        <span v-if="localEmployee.address"><i class="bi bi-house me-1"></i>{{ localEmployee.address }}</span>
                        <span v-if="localEmployee.daily_rate > 0"><i class="bi bi-currency-exchange me-1"></i>₱{{ formatMoney(localEmployee.daily_rate) }}/day</span>
                    </div>
                </div>
                <a href="/panel/employees" class="btn btn-sm btn-outline-secondary flex-shrink-0"><i class="bi bi-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-0" style="border-bottom: none">
            <li class="nav-item" v-for="tab in tabs" :key="tab.key">
                <button class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key"><i class="bi me-1" :class="tab.icon"></i>{{ tab.label }}</button>
            </li>
        </ul>
        <div class="panel-card" style="border-top-left-radius: 0">
            <component :is="activeComponent" :employee="localEmployee" :branches-data="branchesData" @updated="onEmployeeUpdated" />
        </div>
    </div>
</template>

<script>
import EmployeeAttendancePage from "./vendor/EmployeeAttendancePage.vue";
import EmployeePayrollPage from "./vendor/EmployeePayrollPage.vue";
import EmployeePayoutPage from "./vendor/EmployeePayoutPage.vue";
import EmployeeCashAdvancePage from "./vendor/EmployeeCashAdvancePage.vue";
import EmployeeSettingsPage from "./vendor/EmployeeSettingsPage.vue";

export default {
    components: { EmployeeAttendancePage, EmployeePayrollPage, EmployeePayoutPage, EmployeeCashAdvancePage, EmployeeSettingsPage },

    props: {
        employee: { type: Object, required: true },
        branchesData: { type: Array, default: () => [] },
    },

    data() {
        return {
            localEmployee: { ...this.employee },
            activeTab: "attendance",
            tabs: [
                { key: "attendance", label: "Attendance", icon: "bi-calendar-check" },
                { key: "payroll", label: "Payrolls", icon: "bi-receipt" },
                { key: "payout", label: "Payouts", icon: "bi-cash-stack" },
                { key: "cashAdvance", label: "Cash Advances", icon: "bi-wallet2" },
                { key: "settings", label: "Settings", icon: "bi-gear" },
            ],
        };
    },

    computed: {
        activeComponent() {
            return {
                attendance: "EmployeeAttendancePage",
                payroll: "EmployeePayrollPage",
                payout: "EmployeePayoutPage",
                cashAdvance: "EmployeeCashAdvancePage",
                settings: "EmployeeSettingsPage",
            }[this.activeTab];
        },
        initials() {
            return ((this.localEmployee.first_name || "").charAt(0) + (this.localEmployee.last_name || "").charAt(0)).toUpperCase();
        },
        roleLabel() {
            return { super_admin: "Super Admin", admin: "Admin", manager: "Manager", staff: "Staff", coach: "Coach" }[this.localEmployee.role] ?? this.localEmployee.role;
        },
        roleBadge() {
            return { super_admin: "m-badge--inactive", admin: "m-badge--active", manager: "m-badge--plan", staff: "m-badge--plan", coach: "m-badge--plan" }[this.localEmployee.role] ?? "";
        },
        statusBadge() {
            return { active: "m-badge--active", inactive: "m-badge--inactive", suspended: "m-badge--inactive" }[this.localEmployee.status] ?? "";
        },
    },

    methods: {
        onEmployeeUpdated(updated) {
            this.localEmployee = { ...this.localEmployee, ...updated };
        },
        formatMoney(v) {
            return parseFloat(v || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    },
};
</script>
