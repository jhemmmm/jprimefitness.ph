<template>
   <div>
      <div class="d-flex align-items-center justify-content-between mb-4">
         <div>
            <h4 class="fw-bold mb-0">Employee Details</h4>
            <div class="text-muted small">Overview, activity, payouts, and settings</div>
         </div>
         <a href="/panel/employees" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
      </div>

      <div class="panel-card p-4 mb-4">
         <div class="d-flex align-items-start gap-3 flex-wrap">
            <div class="member-avatar employee-avatar-xl flex-shrink-0">{{ $filters.getNameInitials(localEmployee.name) }}</div>
            <div class="flex-grow-1 min-w-0">
               <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <h4 class="fw-bold mb-0">{{ localEmployee.name }}</h4>
                  <span v-for="role in localEmployee.roles || []" :key="role.id" :class="['m-badge', $filters.roleBadge(role.name)]">
                     {{ $filters.capitalize(role.name) }}
                  </span>
                  <span :class="['m-badge', $filters.statusBadge(localEmployee.status)]">{{ $filters.capitalize(localEmployee.status) }}</span>
               </div>
               <div class="text-muted small mb-2" v-if="localEmployee.branches && localEmployee.branches.length"><i class="bi bi-geo-alt me-1"></i>{{ localEmployee.branches.map((b) => b.name).join(", ") }}</div>
               <div class="d-flex gap-3 flex-wrap small text-muted">
                  <span v-if="localEmployee.email"><i class="bi bi-envelope me-1"></i>{{ localEmployee.email }}</span>
                  <span v-if="localEmployee.phone"><i class="bi bi-telephone me-1"></i>{{ localEmployee.phone }}</span>
                  <span v-if="localEmployee.address"><i class="bi bi-house me-1"></i>{{ localEmployee.address }}</span>
                  <span v-if="localEmployee.daily_rate > 0"><i class="bi bi-currency-exchange me-1"></i>₱{{ $filters.formatMoney(localEmployee.daily_rate) }}/day</span>
                  <span><i class="bi bi-calendar2-week me-1"></i>{{ $filters.capitalize(localEmployee.pay_frequency) }}</span>
               </div>
            </div>
         </div>
      </div>

      <ul class="nav nav-tabs mb-0" style="border-bottom: none">
         <li class="nav-item" v-for="tab in tabs" :key="tab.key">
            <button class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key"><i class="bi me-1" :class="tab.icon"></i>{{ tab.label }}</button>
         </li>
      </ul>
      <div class="panel-card" style="border-top-left-radius: 0">
         <component :is="activeComponent" :employee="localEmployee" :branches-data="branchesData" :roles-data="rolesData" @updated="onEmployeeUpdated" />
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
   components: {
      EmployeeAttendancePage,
      EmployeePayrollPage,
      EmployeePayoutPage,
      EmployeeCashAdvancePage,
      EmployeeSettingsPage,
   },

   props: {
      employee: { type: Object, required: true },
      branchesData: { type: Array, default: () => [] },
      rolesData: { type: Array, default: () => [] },
   },

   data: function () {
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
      activeComponent: function () {
         return {
            attendance: "EmployeeAttendancePage",
            payroll: "EmployeePayrollPage",
            payout: "EmployeePayoutPage",
            cashAdvance: "EmployeeCashAdvancePage",
            settings: "EmployeeSettingsPage",
         }[this.activeTab];
      },
   },

   methods: {
      onEmployeeUpdated: function (updated) {
         this.localEmployee = { ...this.localEmployee, ...updated };
      },
   },
};
</script>
