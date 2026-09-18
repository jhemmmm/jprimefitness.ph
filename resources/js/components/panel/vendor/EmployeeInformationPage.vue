<template>
   <div class="p-4">
      <div class="fw-semibold mb-3">Employee Information</div>
      <div class="row g-3">
         <div class="col-md-6" v-for="field in fields" :key="field.label">
            <div class="small text-muted">{{ field.label }}</div>
            <div class="fw-semibold small">{{ field.value }}</div>
         </div>
      </div>
   </div>
</template>

<script>
import { formatDate } from "../../../dates";

export default {
   props: {
      employee: { type: Object, required: true },
   },

   computed: {
      fields: function () {
         const profile = this.employee.employee_profile || {};

         return [
            { label: "Address", value: this.employee.address || "-" },
            { label: "Date of Birth", value: formatDate(profile.date_of_birth) },
            { label: "Date Hired", value: formatDate(profile.hired_at) },
            { label: "Emergency Contact", value: profile.emergency_contact_name || "-" },
            { label: "Emergency Phone", value: profile.emergency_contact_phone || "-" },
            { label: "TIN", value: profile.tin || "-" },
            { label: "SSS No.", value: profile.sss_number || "-" },
            { label: "PhilHealth No.", value: profile.philhealth_number || "-" },
            { label: "Pag-IBIG No.", value: profile.pagibig_number || "-" },
         ];
      },
   },
};
</script>
