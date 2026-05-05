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
                  <label class="form-label form-label-sm fw-semibold">Status <span class="text-danger">*</span></label>
                  <select class="form-select" :class="{ 'is-invalid': errors.status }" v-model="form.status">
                     <option v-for="status in statusOptions" :key="status" :value="status">{{ $filters.capitalize(status) }}</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.status">{{ errors.status[0] }}</div>
               </div>
               <div class="col-12"><hr class="my-1" /></div>

               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Date of Birth</label>
                  <input type="date" class="form-control" :class="{ 'is-invalid': errors.date_of_birth }" v-model="form.date_of_birth" />
                  <div class="invalid-feedback" v-if="errors.date_of_birth">{{ errors.date_of_birth[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Gender</label>
                  <select class="form-select" :class="{ 'is-invalid': errors.gender }" v-model="form.gender">
                     <option disabled value="">Select a gender...</option>
                     <option value="male">Male</option>
                     <option value="female">Female</option>
                     <option value="other">Other</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.gender">{{ errors.gender[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Emergency Contact Name</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.emergency_contact_name }" v-model="form.emergency_contact_name" />
                  <div class="invalid-feedback" v-if="errors.emergency_contact_name">{{ errors.emergency_contact_name[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">Emergency Contact Phone</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.emergency_contact_phone }" v-model="form.emergency_contact_phone" />
                  <div class="invalid-feedback" v-if="errors.emergency_contact_phone">{{ errors.emergency_contact_phone[0] }}</div>
               </div>
               <div class="col-md-6">
                  <label class="form-label form-label-sm fw-semibold">ID Discount</label>
                  <select class="form-select" :class="{ 'is-invalid': errors.discount_type }" v-model="form.discount_type">
                     <option :value="''">None - regular rate</option>
                     <option value="student">Student - 20% off</option>
                     <option value="senior">Senior - 20% off</option>
                  </select>
                  <div class="form-text">Verify a valid student / senior ID before saving.</div>
                  <div class="invalid-feedback" v-if="errors.discount_type">{{ errors.discount_type[0] }}</div>
               </div>
               <div class="col-12">
                  <label class="form-label form-label-sm fw-semibold">Notes</label>
                  <textarea class="form-control" rows="3" :class="{ 'is-invalid': errors.notes }" v-model="form.notes"></textarea>
                  <div class="invalid-feedback" v-if="errors.notes">{{ errors.notes[0] }}</div>
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
export default {
   props: {
      member: { type: Object, required: true },
   },

   emits: ["updated"],

   data: function () {
      return {
         saving: false,
         saved: false,
         generalError: "",
         errors: {},
         form: this.getForm(this.member),
      };
   },

   watch: {
      member: function (value) {
         this.form = this.getForm(value);
      },
   },

   computed: {
      statusOptions: function () {
         return ["active", "inactive", "suspended"];
      },
   },

   methods: {
      getForm: function (member) {
         return {
            name: member.name || "",
            email: member.email || "",
            phone: member.phone || "",
            status: member.status || "active",
            date_of_birth: member.profile?.date_of_birth || "",
            gender: member.profile?.gender || "",
            emergency_contact_name: member.profile?.emergency_contact_name || "",
            emergency_contact_phone: member.profile?.emergency_contact_phone || "",
            notes: member.profile?.notes || "",
            discount_type: member.profile?.discount_type || "",
         };
      },

      save: function () {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};
         axios
            .put(`/panel/members/${this.member.id}`, this.form)
            .then((res) => {
               this.saved = true;
               this.$emit("updated", res.data);
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.errors = err.response.data.errors || {};
               } else {
                  this.generalError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.saving = false));
      },
   },
};
</script>
