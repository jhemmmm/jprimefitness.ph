<template>
   <div class="col-12">
      <label :class="labelClass">Address <span class="text-danger">*</span></label>
      <input type="text" class="form-control" :class="{ 'is-invalid': errors.address }" v-model="form.address" />
      <div class="invalid-feedback" v-if="errors.address">{{ errors.address[0] }}</div>
   </div>
   <div class="col-md-6">
      <label :class="labelClass">Date of Birth <span class="text-danger">*</span></label>
      <input type="date" class="form-control" :class="{ 'is-invalid': errors['employee_profile.date_of_birth'] }" v-model="form.employee_profile.date_of_birth" />
      <div class="invalid-feedback" v-if="errors['employee_profile.date_of_birth']">{{ errors["employee_profile.date_of_birth"][0] }}</div>
   </div>
   <div class="col-md-6">
      <label :class="labelClass">Date Hired</label>
      <input type="date" class="form-control" :class="{ 'is-invalid': errors['employee_profile.hired_at'] }" v-model="form.employee_profile.hired_at" :disabled="hiredReadonly" />
      <div class="invalid-feedback" v-if="errors['employee_profile.hired_at']">{{ errors["employee_profile.hired_at"][0] }}</div>
   </div>
   <div class="col-md-6">
      <label :class="labelClass">Emergency Contact Name <span class="text-danger">*</span></label>
      <input type="text" class="form-control" :class="{ 'is-invalid': errors['employee_profile.emergency_contact_name'] }" v-model="form.employee_profile.emergency_contact_name" placeholder="Full name" />
      <div class="invalid-feedback" v-if="errors['employee_profile.emergency_contact_name']">{{ errors["employee_profile.emergency_contact_name"][0] }}</div>
   </div>
   <div class="col-md-6">
      <label :class="labelClass">Emergency Contact Phone <span class="text-danger">*</span></label>
      <input type="text" class="form-control" :class="{ 'is-invalid': errors['employee_profile.emergency_contact_phone'] }" v-model="form.employee_profile.emergency_contact_phone" placeholder="Phone number" />
      <div class="invalid-feedback" v-if="errors['employee_profile.emergency_contact_phone']">{{ errors["employee_profile.emergency_contact_phone"][0] }}</div>
   </div>
</template>

<script>
// Personal details shared by the employee create modal and the settings tab; drop inside a `row g-3`.
export default {
   props: {
      // the employee form: `address` at the root, the rest under `employee_profile` (edited in place)
      form: { type: Object, required: true },
      errors: { type: Object, default: () => ({}) },
      labelClass: { type: String, default: "form-label fw-semibold" },
      // employees may edit their own details on the profile page, but not when they were hired
      hiredReadonly: { type: Boolean, default: false },
   },
};
</script>
