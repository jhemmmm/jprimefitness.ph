<template>
   <div class="my-record-page">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">{{ current.title }}</h4>
            <p class="text-muted small mb-0">{{ current.description }}</p>
         </div>
      </div>

      <div class="panel-card">
         <component :is="current.component" :employee="employee" />
      </div>
   </div>
</template>

<script>
import EmployeeAttendancePage from "./vendor/EmployeeAttendancePage.vue";
import EmployeeCashAdvancePage from "./vendor/EmployeeCashAdvancePage.vue";
import EmployeePayoutPage from "./vendor/EmployeePayoutPage.vue";
import EmployeePayrollPage from "./vendor/EmployeePayrollPage.vue";
import EmployeeSchedulePage from "./vendor/EmployeeSchedulePage.vue";

const SECTIONS = {
   attendance: { title: "My Attendance", description: "Your check-ins and check-outs, with monthly totals and hours worked", component: "EmployeeAttendancePage" },
   schedule: { title: "My Schedule", description: "Your weekly shift schedule", component: "EmployeeSchedulePage" },
   payroll: { title: "My Payroll", description: "Your payroll runs with gross pay, deductions, net pay, and downloadable payslips", component: "EmployeePayrollPage" },
   payouts: { title: "My Payouts", description: "Amounts released to you per payroll, by cash, bank transfer, or online payment", component: "EmployeePayoutPage" },
   "cash-advances": { title: "My Cash Advances", description: "Your cash advances and what is still to be repaid", component: "EmployeeCashAdvancePage" },
};

export default {
   components: {
      EmployeeAttendancePage,
      EmployeeCashAdvancePage,
      EmployeePayoutPage,
      EmployeePayrollPage,
      EmployeeSchedulePage,
   },
   props: {
      employee: { type: Object, required: true },
      section: { type: String, required: true },
   },
   computed: {
      current: function () {
         return SECTIONS[this.section] || SECTIONS.attendance;
      },
   },
};
</script>
