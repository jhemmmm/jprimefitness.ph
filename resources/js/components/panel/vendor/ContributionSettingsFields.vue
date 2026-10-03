<template>
   <div>
      <div class="fw-semibold mb-1">Philippine Government Contributions</div>
      <div class="text-muted small mb-3">Monthly amounts. Semi-monthly employees have each amount split equally across the two cutoffs.</div>

      <div class="row g-3 mb-3">
         <div class="col-12">
            <label class="form-label form-label-sm fw-semibold mb-1">TIN <span class="text-danger" v-if="payroll.payroll_withholding_tax_enabled">*</span></label>
            <input type="text" class="form-control" :class="{ 'is-invalid': errorFor('tin') }" v-model="profile.tin" placeholder="123-456-789-000" />
            <div class="invalid-feedback d-block" v-if="errorFor('tin')">{{ errorFor("tin") }}</div>
         </div>
      </div>

      <div class="row g-3">
         <div class="col-md-4" v-for="program in programs" :key="program.key">
            <label class="form-label form-label-sm fw-semibold mb-1">{{ program.label }} No. <span class="text-danger" v-if="payroll.payroll_government_contributions_enabled && profile[`${program.key}_covered`]">*</span></label>
            <input type="text" class="form-control mb-2" :class="{ 'is-invalid': errorFor(`${program.key}_number`) }" v-model="profile[`${program.key}_number`]" :placeholder="program.numberPlaceholder" />
            <div class="invalid-feedback d-block" v-if="errorFor(`${program.key}_number`)">{{ errorFor(`${program.key}_number`) }}</div>
            <div class="form-check form-switch mb-2">
               <input class="form-check-input" type="checkbox" :id="`${idPrefix}-${program.key}-covered`" v-model="profile[`${program.key}_covered`]" />
               <label class="form-check-label fw-semibold" :for="`${idPrefix}-${program.key}-covered`">{{ program.label }} Covered</label>
            </div>
            <label class="form-label form-label-sm fw-semibold mb-1">Deducted from employee (₱/mo)</label>
            <input type="number" class="form-control mb-2" :class="{ 'is-invalid': errorFor(`${program.key}_employee_share`) }" v-model="profile[`${program.key}_employee_share`]" min="0" step="0.01" placeholder="0.00" :disabled="!profile[`${program.key}_covered`]" />
            <div class="invalid-feedback d-block" v-if="errorFor(`${program.key}_employee_share`)">{{ errorFor(`${program.key}_employee_share`) }}</div>
            <label class="form-label form-label-sm fw-semibold mb-1">Paid by the gym (₱/mo)</label>
            <input type="number" class="form-control" :class="{ 'is-invalid': errorFor(`${program.key}_employer_share`) }" v-model="profile[`${program.key}_employer_share`]" min="0" step="0.01" placeholder="0.00" :disabled="!profile[`${program.key}_covered`]" />
            <div class="invalid-feedback d-block" v-if="errorFor(`${program.key}_employer_share`)">{{ errorFor(`${program.key}_employer_share`) }}</div>
            <div class="form-text small">Legal minimum: ₱{{ $filters.formatMoney(minimums[`${program.key}_employee_share`]) }} employee / ₱{{ $filters.formatMoney(minimums[`${program.key}_employer_share`]) }} gym</div>
         </div>
      </div>

      <div v-if="rows.length" class="border rounded-3 p-3 mt-3 bg-body">
         <div class="fw-semibold small mb-2"><i class="bi bi-calculator me-1"></i>What these settings cost per month</div>
         <table class="table table-sm small mb-0">
            <thead>
               <tr>
                  <th>Program</th>
                  <th class="text-end">Deducted from employee</th>
                  <th class="text-end">Paid by the gym</th>
                  <th class="text-end">Total</th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="row in rows" :key="row.key">
                  <td>{{ row.label }}</td>
                  <td class="text-end">₱{{ $filters.formatMoney(row.employee) }}</td>
                  <td class="text-end">₱{{ $filters.formatMoney(row.employer) }}</td>
                  <td class="text-end">₱{{ $filters.formatMoney(row.employee + row.employer) }}</td>
               </tr>
               <tr class="fw-bold">
                  <td>Total per month</td>
                  <td class="text-end text-danger">₱{{ $filters.formatMoney(totals.employee) }}</td>
                  <td class="text-end">₱{{ $filters.formatMoney(totals.employer) }}</td>
                  <td class="text-end">₱{{ $filters.formatMoney(totals.employee + totals.employer) }}</td>
               </tr>
            </tbody>
         </table>
      </div>
   </div>
</template>

<script>
const PROGRAMS = [
   { key: "sss", label: "SSS", numberPlaceholder: "34-1234567-8" },
   { key: "philhealth", label: "PhilHealth", numberPlaceholder: "12-345678901-2" },
   { key: "pagibig", label: "Pag-IBIG", numberPlaceholder: "1234-5678-9012" },
];

// Form state for an employee profile: new PH employees start covered at the legal minimum.
export function employeeProfileForm(profile, isPhilippinesPayroll) {
   return {
      daily_rate: profile?.daily_rate ?? "",
      pt_commission_rate: profile?.pt_commission_rate ?? "",
      pay_frequency: profile?.pay_frequency || "semi_monthly",
      ...Object.fromEntries((window.JPrime?.employeeDetailColumns || []).map((key) => [key, profile?.[key] ?? ""])),
      ...Object.fromEntries(PROGRAMS.map(({ key }) => [`${key}_covered`, profile ? Boolean(profile[`${key}_covered`]) : isPhilippinesPayroll])),
      ...Object.fromEntries(Object.entries(window.JPrime?.contributionMinimums || {}).map(([key, minimum]) => [key, profile?.[key] ?? minimum])),
   };
}

export default {
   props: {
      // the form's employee_profile object; contribution keys are edited in place
      profile: { type: Object, required: true },
      errors: { type: Object, default: () => ({}) },
      idPrefix: { type: String, default: "contribution" },
   },

   computed: {
      programs: function () {
         return PROGRAMS;
      },
      minimums: function () {
         return window.JPrime?.contributionMinimums || {};
      },
      payroll: function () {
         return window.JPrime?.profile || {};
      },
      rows: function () {
         return PROGRAMS.filter((program) => this.profile[`${program.key}_covered`]).map((program) => ({
            ...program,
            employee: Number(this.profile[`${program.key}_employee_share`]) || 0,
            employer: Number(this.profile[`${program.key}_employer_share`]) || 0,
         }));
      },
      totals: function () {
         return this.rows.reduce((sum, row) => ({ employee: sum.employee + row.employee, employer: sum.employer + row.employer }), { employee: 0, employer: 0 });
      },
   },

   methods: {
      errorFor: function (field) {
         const error = this.errors[`employee_profile.${field}`];
         return Array.isArray(error) ? error[0] : error || "";
      },
   },
};
</script>
