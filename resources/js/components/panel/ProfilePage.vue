<template>
   <div class="profile-page">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Profile</h4>
            <p class="text-muted small mb-0">Update your contact details, personal information, and password</p>
         </div>
      </div>

      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Profile saved.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-4">
         <div class="col-lg-3">
            <div class="list-group">
               <button type="button" class="list-group-item list-group-item-action" :class="{ active: activeSection === section.id }" v-for="section in sections" :key="section.id" @click="activeSection = section.id">
                  <div class="fw-semibold">{{ section.label }}<i class="bi bi-exclamation-circle-fill text-danger ms-2" v-if="sectionHasErrors(section)"></i></div>
                  <div class="small opacity-75">{{ section.note }}</div>
               </button>
            </div>
         </div>

         <div class="col-lg-9">
            <section class="panel-card business-settings-section" v-show="activeSection === 'account'">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Account</div>
                     <div class="panel-card-sub">Your email is managed by an administrator</div>
                  </div>
               </div>
               <div class="panel-card-body">
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" v-model="form.name" />
                        <div class="invalid-feedback" v-if="errors.name">{{ errors.name[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Email</label>
                        <input type="email" class="form-control" :value="user.email" disabled />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': errors.phone }" v-model="form.phone" />
                        <div class="invalid-feedback" v-if="errors.phone">{{ errors.phone[0] }}</div>
                     </div>
                  </div>
               </div>
            </section>

            <section class="panel-card business-settings-section" v-show="activeSection === 'password'">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Change Password</div>
                     <div class="panel-card-sub">Leave blank to keep your current password.</div>
                  </div>
               </div>
               <div class="panel-card-body">
                  <div class="row g-3">
                     <div class="col-md-12">
                        <label class="form-label form-label-sm fw-semibold">Current Password</label>
                        <input type="password" class="form-control" :class="{ 'is-invalid': errors.current_password }" v-model="form.current_password" autocomplete="current-password" />
                        <div class="invalid-feedback" v-if="errors.current_password">{{ errors.current_password[0] }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">New Password</label>
                        <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" v-model="form.password" autocomplete="new-password" />
                        <div class="invalid-feedback" v-if="errors.password">{{ errors.password[0] }}</div>
                        <div class="form-text" v-else>At least 8 characters with upper and lower case letters and a symbol.</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Confirm New Password</label>
                        <input type="password" class="form-control" v-model="form.password_confirmation" autocomplete="new-password" />
                     </div>
                  </div>
               </div>
            </section>

            <section class="panel-card business-settings-section" v-if="user.employee_profile" v-show="activeSection === 'employee'">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Employee Information</div>
                     <div class="panel-card-sub">Your date hired is set by your manager</div>
                  </div>
               </div>
               <div class="panel-card-body">
                  <div class="row g-3">
                     <employee-details-fields :form="form" :errors="errors" label-class="form-label form-label-sm fw-semibold" hired-readonly />
                     <div class="col-md-6" v-for="(label, key) in GOVERNMENT_IDS" :key="key">
                        <label class="form-label form-label-sm fw-semibold">{{ label }}</label>
                        <input type="text" class="form-control" :class="{ 'is-invalid': errors[`employee_profile.${key}`] }" v-model="form.employee_profile[key]" />
                        <div class="invalid-feedback" v-if="errors[`employee_profile.${key}`]">{{ errors[`employee_profile.${key}`][0] }}</div>
                     </div>
                  </div>
               </div>
            </section>

            <div class="d-flex justify-content-end mt-3">
               <button type="button" class="btn btn-danger btn-sm px-4" @click="save" :disabled="saving">
                  <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                  Save Changes
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import EmployeeDetailsFields from "./vendor/EmployeeDetailsFields.vue";

const GOVERNMENT_IDS = { tin: "TIN", sss_number: "SSS No.", philhealth_number: "PhilHealth No.", pagibig_number: "Pag-IBIG No." };

const SECTIONS = [
   { id: "account", label: "Account", note: "Name and phone.", fields: ["name", "phone"] },
   { id: "password", label: "Password", note: "Change your sign-in password.", fields: ["current_password", "password"] },
   { id: "employee", label: "Employee information", note: "Address, birthday, emergency contact, and government IDs.", fields: ["address", "employee_profile"] },
];

export default {
   components: { EmployeeDetailsFields },
   props: {
      user: { type: Object, required: true },
   },
   data: function () {
      return {
         activeSection: SECTIONS[0].id,
         form: {
            name: this.user.name || "",
            phone: this.user.phone || "",
            address: this.user.address || "",
            employee_profile: Object.fromEntries((window.JPrime?.employeeDetailColumns || []).map((key) => [key, this.user.employee_profile?.[key] ?? ""])),
            current_password: "",
            password: "",
            password_confirmation: "",
         },
         errors: {},
         saving: false,
         saved: false,
         generalError: "",
      };
   },
   computed: {
      GOVERNMENT_IDS: () => GOVERNMENT_IDS,
      sections: function () {
         return this.user.employee_profile ? SECTIONS : SECTIONS.filter((section) => section.id !== "employee");
      },
   },
   methods: {
      sectionHasErrors: function (section) {
         return Object.keys(this.errors).some((key) => section.fields.includes(key.split(".")[0]));
      },
      save: function () {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};

         axios
            .put("/panel/profile", this.form)
            .then(() => {
               this.saved = true;
               this.form.current_password = "";
               this.form.password = "";
               this.form.password_confirmation = "";
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.errors = err.response.data.errors || {};
                  this.activeSection = this.sections.find((section) => this.sectionHasErrors(section))?.id || this.activeSection;
               } else {
                  this.generalError = err.response?.data?.message || "Failed to save your profile.";
               }
            })
            .finally(() => (this.saving = false));
      },
   },
};
</script>
