<template>
   <div>
      <div class="fw-semibold mb-1">Philippine Government Contributions</div>
      <div class="text-muted small mb-2">Set the monthly pay used for SSS, PhilHealth, and Pag-IBIG. Pick a preset to fill everything, then check the exact cost below.</div>

      <div class="d-flex gap-2 mb-3 flex-wrap">
         <button type="button" class="btn btn-sm" :class="basis === 'minimum' ? 'btn-danger' : 'btn-outline-secondary'" @click="applyMinimum"><i class="bi bi-arrow-down-circle me-1"></i>Legal minimum</button>
         <button type="button" class="btn btn-sm" :class="basis === 'actual' ? 'btn-danger' : 'btn-outline-secondary'" :disabled="!actualMonthly" @click="applyActual">
            <i class="bi bi-cash-coin me-1"></i>Actual pay{{ actualMonthly ? ` (₱${$filters.formatMoney(actualMonthly)}/mo)` : "" }}
         </button>
         <span v-if="basis === 'custom'" class="align-self-center text-muted small">Custom values</span>
      </div>

      <div class="row g-3">
         <div class="col-md-4">
            <div class="form-check form-switch mb-2">
               <input class="form-check-input" type="checkbox" :id="idPrefix + '-sss-covered'" v-model="profile.sss_covered" />
               <label class="form-check-label fw-semibold" :for="idPrefix + '-sss-covered'">SSS Covered</label>
            </div>
            <label class="form-label form-label-sm fw-semibold">SSS Monthly Compensation (₱)</label>
            <input type="number" class="form-control" :class="{ 'is-invalid': errorFor('sss_monthly_compensation') }" v-model="profile.sss_monthly_compensation" min="0" step="0.01" placeholder="0.00" :disabled="!profile.sss_covered" />
            <div class="form-text small">Lowest allowed is ₱5,000. Anything lower is still charged as ₱5,000.</div>
            <div class="invalid-feedback" v-if="errorFor('sss_monthly_compensation')">{{ errorFor("sss_monthly_compensation") }}</div>
         </div>
         <div class="col-md-4">
            <div class="form-check form-switch mb-2">
               <input class="form-check-input" type="checkbox" :id="idPrefix + '-philhealth-covered'" v-model="profile.philhealth_covered" />
               <label class="form-check-label fw-semibold" :for="idPrefix + '-philhealth-covered'">PhilHealth Covered</label>
            </div>
            <label class="form-label form-label-sm fw-semibold">PhilHealth Monthly Basic Salary (₱)</label>
            <input type="number" class="form-control" :class="{ 'is-invalid': errorFor('philhealth_monthly_basic_salary') }" v-model="profile.philhealth_monthly_basic_salary" min="0" step="0.01" placeholder="0.00" :disabled="!profile.philhealth_covered" />
            <div class="form-text small">Lowest allowed is ₱10,000. Anything lower is still charged as ₱10,000.</div>
            <div class="invalid-feedback" v-if="errorFor('philhealth_monthly_basic_salary')">{{ errorFor("philhealth_monthly_basic_salary") }}</div>
         </div>
         <div class="col-md-4">
            <div class="form-check form-switch mb-2">
               <input class="form-check-input" type="checkbox" :id="idPrefix + '-pagibig-covered'" v-model="profile.pagibig_covered" />
               <label class="form-check-label fw-semibold" :for="idPrefix + '-pagibig-covered'">Pag-IBIG Covered</label>
            </div>
            <label class="form-label form-label-sm fw-semibold">Pag-IBIG Monthly Compensation (₱)</label>
            <input type="number" class="form-control" :class="{ 'is-invalid': errorFor('pagibig_monthly_compensation') }" v-model="profile.pagibig_monthly_compensation" min="0" step="0.01" placeholder="0.00" :disabled="!profile.pagibig_covered" />
            <div class="form-text small">Capped at ₱10,000, so ₱200 max each side.</div>
            <div class="invalid-feedback" v-if="errorFor('pagibig_monthly_compensation')">{{ errorFor("pagibig_monthly_compensation") }}</div>
         </div>
      </div>

      <div v-if="anyCovered" class="border rounded-3 p-3 mt-3 bg-body">
         <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold small"><i class="bi bi-calculator me-1"></i>What these settings cost per month</span>
            <span v-if="loadingPreview" class="spinner-border spinner-border-sm text-muted"></span>
         </div>
         <template v-if="preview">
            <table class="table table-sm small mb-2">
               <thead>
                  <tr>
                     <th></th>
                     <th class="text-end">Deducted from employee</th>
                     <th class="text-end">Paid by the gym (on top)</th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="row in previewRows" :key="row.key">
                     <td>{{ row.label }}</td>
                     <td class="text-end">₱{{ $filters.formatMoney(row.employee) }}</td>
                     <td class="text-end">₱{{ $filters.formatMoney(row.employer) }}</td>
                  </tr>
                  <tr class="fw-bold">
                     <td>Total per month</td>
                     <td class="text-end text-danger">₱{{ $filters.formatMoney(preview.employee_contributions_total) }}</td>
                     <td class="text-end">₱{{ $filters.formatMoney(preview.employer_contributions_total) }}</td>
                  </tr>
               </tbody>
            </table>
            <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Deducted once a month on the month's last payroll cutoff.</div>
         </template>
      </div>
   </div>
</template>

<script>
export const MINIMUM_BASES = {
   sss_monthly_compensation: 5000,
   philhealth_monthly_basic_salary: 10000,
   pagibig_monthly_compensation: 1500,
};

const WORKING_DAYS_PER_MONTH = 26;

export default {
   props: {
      // the form's employee_profile object; contribution keys are edited in place
      profile: { type: Object, required: true },
      errors: { type: Object, default: () => ({}) },
      idPrefix: { type: String, default: "contribution" },
   },

   data: function () {
      return {
         preview: null,
         loadingPreview: false,
         previewFetchHandle: null,
      };
   },

   mounted: function () {
      this.queuePreviewFetch();
   },

   watch: {
      contributionInputs: function () {
         this.queuePreviewFetch();
      },
   },

   computed: {
      actualMonthly: function () {
         const dailyRate = Number(this.profile.daily_rate || 0);
         return dailyRate > 0 ? Math.round(dailyRate * WORKING_DAYS_PER_MONTH * 100) / 100 : 0;
      },
      anyCovered: function () {
         return Boolean(this.profile.sss_covered || this.profile.philhealth_covered || this.profile.pagibig_covered);
      },
      basis: function () {
         const matches = (values) => Object.entries(values).every(([key, value]) => Number(this.profile[key] || 0) === value);
         if (this.anyCovered && matches(MINIMUM_BASES)) return "minimum";
         if (this.anyCovered && this.actualMonthly && matches({ sss_monthly_compensation: this.actualMonthly, philhealth_monthly_basic_salary: this.actualMonthly, pagibig_monthly_compensation: this.actualMonthly })) return "actual";
         return "custom";
      },
      contributionInputs: function () {
         return {
            pay_frequency: this.profile.pay_frequency || "semi_monthly",
            sss_covered: this.profile.sss_covered ? 1 : 0,
            sss_monthly_compensation: this.profile.sss_monthly_compensation || 0,
            philhealth_covered: this.profile.philhealth_covered ? 1 : 0,
            philhealth_monthly_basic_salary: this.profile.philhealth_monthly_basic_salary || 0,
            pagibig_covered: this.profile.pagibig_covered ? 1 : 0,
            pagibig_monthly_compensation: this.profile.pagibig_monthly_compensation || 0,
         };
      },
      previewRows: function () {
         const programs = { ...(this.preview?.employee_contributions || {}), ...(this.preview?.employer_contributions || {}) };
         return Object.keys(programs).map((key) => ({
            key,
            label: programs[key].label,
            employee: this.preview?.employee_contributions?.[key]?.total || 0,
            employer: this.preview?.employer_contributions?.[key]?.total || 0,
         }));
      },
   },

   methods: {
      errorFor: function (field) {
         const error = this.errors[`employee_profile.${field}`];
         return Array.isArray(error) ? error[0] : error || "";
      },
      applyMinimum: function () {
         this.setCovered();
         Object.entries(MINIMUM_BASES).forEach(([key, value]) => {
            this.profile[key] = value;
         });
      },
      applyActual: function () {
         if (!this.actualMonthly) return;
         this.setCovered();
         this.profile.sss_monthly_compensation = this.actualMonthly;
         this.profile.philhealth_monthly_basic_salary = this.actualMonthly;
         this.profile.pagibig_monthly_compensation = this.actualMonthly;
      },
      setCovered: function () {
         this.profile.sss_covered = true;
         this.profile.philhealth_covered = true;
         this.profile.pagibig_covered = true;
      },
      queuePreviewFetch: function () {
         if (!this.anyCovered) {
            this.preview = null;
            return;
         }

         window.clearTimeout(this.previewFetchHandle);
         this.previewFetchHandle = window.setTimeout(() => {
            this.fetchPreview();
         }, 300);
      },
      fetchPreview: function () {
         this.loadingPreview = true;
         axios
            .get("/panel/employees/contribution-preview", { params: this.contributionInputs })
            .then((res) => (this.preview = res.data))
            .catch(() => (this.preview = null))
            .finally(() => (this.loadingPreview = false));
      },
   },

   beforeUnmount: function () {
      window.clearTimeout(this.previewFetchHandle);
   },
};
</script>
