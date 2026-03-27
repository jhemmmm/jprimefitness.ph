<template>
   <div class="p-4">
      <div class="row justify-content-center">
         <div class="col-lg-8">
            <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Changes saved successfully.</div>
            <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

            <div class="row g-3">
               <div class="col-md-12">
                  <label class="form-label form-label-sm fw-semibold">Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" v-model="form.name" />
                  <div class="invalid-feedback" v-if="errors.name">{{ errors.name[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Email <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" v-model="form.email" />
                  <div class="invalid-feedback" v-if="errors.email">{{ errors.email[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Phone</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.phone }" v-model="form.phone" />
                  <div class="invalid-feedback" v-if="errors.phone">{{ errors.phone[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Role <span class="text-danger">*</span></label>
                  <div :class="{ 'is-invalid': errors.role_ids }">
                     <MultiSelect v-model="form.role_ids" :options="allowedRoles" placeholder="Select roles..." searchable />
                  </div>
                  <div class="invalid-feedback" v-if="errors.role_ids">{{ errors.role_ids[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Status <span class="text-danger">*</span></label>
                  <select class="form-select" :class="{ 'is-invalid': errors.status }" v-model="form.status">
                     <option v-for="status in statusOptions" :key="status" :value="status">{{ $filters.capitalize(status) }}</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.status">{{ errors.status[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Branches</label>
                  <div :class="{ 'is-invalid': errors.branch_ids }">
                     <MultiSelect v-model="form.branch_ids" :options="branchesData" placeholder="Select branches..." searchable />
                  </div>
                  <div class="form-text small">Super admin/admin can assign multiple branches. Staff/coach should select one.</div>
                  <div class="invalid-feedback" v-if="errors.branch_ids">{{ errors.branch_ids[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Daily Rate (₱)</label>
                  <input type="number" class="form-control" :class="{ 'is-invalid': errors.daily_rate }" v-model="form.daily_rate" min="0" step="0.01" placeholder="0.00" />
                  <div class="invalid-feedback" v-if="errors.daily_rate">{{ errors.daily_rate[0] }}</div>
               </div>

               <div class="col-12"><hr class="my-1" /></div>

               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">
                     New Password
                     <span class="text-muted small">(leave blank to keep current)</span>
                  </label>
                  <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" v-model="form.password" autocomplete="new-password" />
                  <div class="invalid-feedback" v-if="errors.password">{{ errors.password[0] }}</div>
               </div>
            </div>

            <div class="mt-4 d-flex justify-content-end">
               <button class="btn btn-danger px-4" @click="save" :disabled="saving">
                  <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                  Save Changes
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import MultiSelect from "../_vendor/MultiSelect.vue";

export default {
   components: {
      MultiSelect,
   },
   props: {
      employee: { type: Object, required: true },
      branchesData: { type: Array, default: () => [] },
      rolesData: { type: Array, default: () => [] },
   },

   emits: ["updated"],

   data() {
      return {
         saving: false,
         saved: false,
         generalError: "",
         errors: {},
         form: this.getForm(this.employee),
      };
   },

   watch: {
      employee(val) {
         this.form = this.getForm(val);
      },
   },

   computed: {
      allowedRoles() {
         const allowed = ["super admin", "admin", "manager", "staff", "coach", "employee"];
         const roleRestrictions = {
            "super admin": [],
            admin: ["super admin"],
            default: ["super admin", "admin"],
         };
         const currentRole = this.is("super admin") ? "super admin" : this.is("admin") ? "admin" : "default";

         return this.rolesData
            .filter((r) => allowed.includes(r.name) && !roleRestrictions[currentRole].includes(r.name))
            .map((r) => ({
               id: r.id,
               name: this.$filters.capitalize(r.name),
            }));
      },
      statusOptions() {
         return ["active", "inactive", "suspended"];
      },
   },

   methods: {
      getForm(employee) {
         return {
            name: employee.name || "",
            email: employee.email,
            phone: employee.phone || "",
            status: employee.status,
            role_ids: employee.roles ? employee.roles.map((r) => r.id) : [],
            branch_ids: employee.branches ? employee.branches.map((b) => b.id) : [],
            daily_rate: employee.daily_rate || "",
            password: "",
         };
      },
      async save() {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};
         try {
            const payload = { ...this.form, branch_ids: this.form.branch_ids, role_ids: this.form.role_ids };
            const res = await axios.put(`/panel/employees/${this.employee.id}`, payload);
            this.saved = true;
            this.form.password = "";
            this.$emit("updated", res.data);
            setTimeout(() => (this.saved = false), 3000);
         } catch (err) {
            if (err.response?.status === 422) {
               this.errors = err.response.data.errors || {};
            } else {
               this.generalError = err.response?.data?.message || "Something went wrong.";
            }
         } finally {
            this.saving = false;
         }
      },
   },
};
</script>
