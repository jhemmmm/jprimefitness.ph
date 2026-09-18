<template>
   <div class="profile-page">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Profile</h4>
            <p class="text-muted small mb-0">Update your name, phone, and password</p>
         </div>
      </div>

      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Profile saved.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-3">
         <div class="col-12 col-xl-8">
            <div class="panel-card">
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

                  <hr class="my-4" />

                  <div class="fw-semibold mb-1">Change Password</div>
                  <p class="text-muted small mb-3">Leave blank to keep your current password.</p>
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

                  <div class="d-flex justify-content-end mt-4">
                     <button type="button" class="btn btn-danger btn-sm px-4" @click="save" :disabled="saving">
                        <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                        Save Changes
                     </button>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
export default {
   props: {
      user: { type: Object, required: true },
   },
   data: function () {
      return {
         form: {
            name: this.user.name || "",
            phone: this.user.phone || "",
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
   methods: {
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
               } else {
                  this.generalError = err.response?.data?.message || "Failed to save your profile.";
               }
            })
            .finally(() => (this.saving = false));
      },
   },
};
</script>
