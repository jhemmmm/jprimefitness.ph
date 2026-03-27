<template>
   <div class="p-4">
      <div class="row justify-content-center">
         <div class="col-xl-11">
            <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Government contribution settings saved successfully.</div>
            <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>
            <div class="alert alert-secondary py-2 small" v-if="!canManage"><i class="bi bi-lock me-1"></i>Only super admins can edit government contribution settings.</div>

            <div class="row g-3 mb-4">
               <div class="col-md-4">
                  <label class="form-label form-label-sm fw-semibold">Pay Frequency</label>
                  <select class="form-select" v-model="form.pay_frequency" :disabled="!canManage">
                     <option value="semi_monthly">Semi-Monthly</option>
                     <option value="monthly">Monthly</option>
                  </select>
               </div>
               <div class="col-md-8">
                  <label class="form-label form-label-sm fw-semibold">Income Tax</label>
                  <input type="text" class="form-control" value="Manual on payroll" disabled />
                  <div class="form-text">Income tax is still entered on each payroll so semi-monthly and country-specific withholding can stay flexible.</div>
               </div>
            </div>

            <div class="border rounded p-3 bg-light-subtle">
               <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <div>
                     <div class="fw-semibold">Contribution Rules</div>
                     <div class="text-muted small">Configure employee and employer shares by name. Minimum amounts are monthly minimums before payroll frequency is split.</div>
                  </div>
                  <button type="button" class="btn btn-outline-secondary btn-sm" @click="addContribution" :disabled="!canManage">
                     <i class="bi bi-plus-lg me-1"></i>Add Contribution
                  </button>
               </div>

               <div v-if="errors['payroll_settings.contributions']" class="alert alert-danger py-2 small mb-3">{{ errors["payroll_settings.contributions"][0] }}</div>

               <div v-if="!form.contributions.length" class="text-muted small">No contribution rules configured for this branch yet.</div>

               <div v-for="(contribution, index) in form.contributions" :key="'contribution-' + index" class="border rounded p-3 bg-white mb-3">
                  <div class="row g-3 align-items-end">
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Name</label>
                        <input type="text" class="form-control" v-model="contribution.name" :disabled="!canManage" />
                     </div>
                     <div class="col-md-2">
                        <label class="form-label form-label-sm">Employee %</label>
                        <input type="number" class="form-control" v-model="contribution.employee_rate" min="0" step="0.01" :disabled="!canManage" />
                     </div>
                     <div class="col-md-2">
                        <label class="form-label form-label-sm">Employer %</label>
                        <input type="number" class="form-control" v-model="contribution.employer_rate" min="0" step="0.01" :disabled="!canManage" />
                     </div>
                     <div class="col-md-2">
                        <label class="form-label form-label-sm">Employee Min</label>
                        <input type="number" class="form-control" v-model="contribution.employee_min_amount" min="0" step="0.01" :disabled="!canManage" placeholder="Optional" />
                     </div>
                     <div class="col-md-2">
                        <label class="form-label form-label-sm">Employer Min</label>
                        <input type="number" class="form-control" v-model="contribution.employer_min_amount" min="0" step="0.01" :disabled="!canManage" placeholder="Optional" />
                     </div>
                     <div class="col-md-1 d-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" @click="removeContribution(index)" :disabled="!canManage">
                           <i class="bi bi-trash"></i>
                        </button>
                     </div>

                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Salary Floor</label>
                        <input type="number" class="form-control" v-model="contribution.salary_floor" min="0" step="0.01" :disabled="!canManage" placeholder="Optional" />
                     </div>
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Salary Ceiling</label>
                        <input type="number" class="form-control" v-model="contribution.salary_ceiling" min="0" step="0.01" :disabled="!canManage" placeholder="Optional" />
                     </div>
                     <div class="col-md-3">
                        <div class="form-check pt-4">
                           <input class="form-check-input" type="checkbox" :id="'government-contribution-enabled-' + index" v-model="contribution.enabled" :disabled="!canManage" />
                           <label class="form-check-label" :for="'government-contribution-enabled-' + index">Enabled</label>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="mt-4 d-flex justify-content-end" v-if="canManage">
               <button class="btn btn-danger px-4" @click="save" :disabled="saving">
                  <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                  Save Contribution Rules
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
export default {
   props: {
      branch: { type: Object, required: true },
   },

   emits: ["updated"],

   data() {
      return {
         saving: false,
         saved: false,
         generalError: "",
         errors: {},
         form: this.getForm(this.branch),
      };
   },

   watch: {
      branch(value) {
         this.form = this.getForm(value);
      },
   },

   computed: {
      canManage() {
         return this.is("super admin");
      },
   },

   methods: {
      getForm(branch) {
         const settings = branch.payroll_settings || {};
         const contributions = Array.isArray(settings.contributions) ? settings.contributions : [];

         return {
            pay_frequency: ["monthly", "semi_monthly"].includes(settings.pay_frequency) ? settings.pay_frequency : "semi_monthly",
            contributions: contributions.map((contribution) => ({
               name: contribution.name || "",
               employee_rate: contribution.employee_rate ?? 0,
               employer_rate: contribution.employer_rate ?? 0,
               employee_min_amount: contribution.employee_min_amount ?? "",
               employer_min_amount: contribution.employer_min_amount ?? "",
               salary_floor: contribution.salary_floor ?? "",
               salary_ceiling: contribution.salary_ceiling ?? "",
               enabled: contribution.enabled !== false,
            })),
         };
      },

      buildPayload(overrides = {}) {
         return {
            name: this.branch.name || "",
            status: this.branch.status || "open",
            country_code: this.branch.country_code || "PH",
            city: this.branch.city || "",
            province: this.branch.province || "",
            address: this.branch.address || "",
            phone: this.branch.phone || "",
            email: this.branch.email || "",
            timezone: this.branch.timezone || "Asia/Manila",
            amenities: Array.isArray(this.branch.amenities) ? this.branch.amenities : [],
            opening_time: this.branch.opening_time ? String(this.branch.opening_time).slice(0, 5) : "",
            closing_time: this.branch.closing_time ? String(this.branch.closing_time).slice(0, 5) : "",
            facebook_url: this.branch.facebook_url || "",
            messenger_url: this.branch.messenger_url || "",
            whatsapp_url: this.branch.whatsapp_url || "",
            map_url: this.branch.map_url || "",
            payroll_settings: {
               pay_frequency: this.form.pay_frequency,
               income_tax_mode: "manual",
               contributions: this.form.contributions
                  .map((contribution) => ({
                     name: String(contribution.name || "").trim(),
                     employee_rate: contribution.employee_rate === "" ? 0 : contribution.employee_rate,
                     employer_rate: contribution.employer_rate === "" ? 0 : contribution.employer_rate,
                     employee_min_amount: contribution.employee_min_amount === "" ? null : contribution.employee_min_amount,
                     employer_min_amount: contribution.employer_min_amount === "" ? null : contribution.employer_min_amount,
                     salary_floor: contribution.salary_floor === "" ? null : contribution.salary_floor,
                     salary_ceiling: contribution.salary_ceiling === "" ? null : contribution.salary_ceiling,
                     enabled: contribution.enabled !== false,
                  }))
                  .filter((contribution) => contribution.name !== ""),
            },
            ...overrides,
         };
      },

      addContribution() {
         this.form.contributions.push({
            name: "",
            employee_rate: 0,
            employer_rate: 0,
            employee_min_amount: "",
            employer_min_amount: "",
            salary_floor: "",
            salary_ceiling: "",
            enabled: true,
         });
      },

      removeContribution(index) {
         this.form.contributions.splice(index, 1);
      },

      async save() {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};

         try {
            const response = await axios.put(`/panel/branches/${this.branch.id}`, this.buildPayload());
            this.saved = true;
            this.$emit("updated", response.data);
            setTimeout(() => (this.saved = false), 3000);
         } catch (error) {
            if (error.response?.status === 422) {
               this.errors = error.response.data.errors || {};
            } else {
               this.generalError = error.response?.data?.message || "Something went wrong.";
            }
         } finally {
            this.saving = false;
         }
      },
   },
};
</script>
