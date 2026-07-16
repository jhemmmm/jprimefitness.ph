<template>
   <div class="p-4">
      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Changes saved successfully.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-4 align-items-start">
         <div class="col-xl-8">
            <section class="h-100">
               <div class="fw-semibold mb-1">Employee Information</div>

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
                     <label class="form-label form-label-sm fw-semibold">Daily Rate (₱)</label>
                     <input
                        type="number"
                        class="form-control"
                        :class="{ 'is-invalid': errors['employee_profile.daily_rate'] }"
                        v-model="form.employee_profile.daily_rate"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                     />
                     <div class="invalid-feedback" v-if="errors['employee_profile.daily_rate']">{{ errors["employee_profile.daily_rate"][0] }}</div>
                  </div>
                  <div class="col-md-6">
                     <label class="form-label form-label-sm fw-semibold">Pay Frequency</label>
                     <select
                        class="form-select"
                        :class="{ 'is-invalid': errors['employee_profile.pay_frequency'] }"
                        v-model="form.employee_profile.pay_frequency"
                     >
                        <option value="semi_monthly">Semi-Monthly</option>
                        <option value="monthly">Monthly</option>
                     </select>
                     <div class="form-text small">Set the payroll schedule directly on the employee contract.</div>
                     <div class="invalid-feedback" v-if="errors['employee_profile.pay_frequency']">{{ errors["employee_profile.pay_frequency"][0] }}</div>
                  </div>
                  <div class="col-md-6">
                     <label class="form-label form-label-sm fw-semibold">PT Commission Rate (%)</label>
                     <input
                        type="number"
                        class="form-control"
                        :class="{ 'is-invalid': errors['employee_profile.pt_commission_rate'] }"
                        v-model="form.employee_profile.pt_commission_rate"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="0"
                     />
                     <div class="form-text small">Share of every PT plan sold with this coach. Leave 0 for non-coaches.</div>
                     <div class="invalid-feedback" v-if="errors['employee_profile.pt_commission_rate']">{{ errors["employee_profile.pt_commission_rate"][0] }}</div>
                  </div>
                  <div class="col-md-6">
                     <label class="form-label form-label-sm fw-semibold">
                        New Password
                        <span class="text-muted small">(leave blank to keep current)</span>
                     </label>
                     <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" v-model="form.password" autocomplete="new-password" />
                     <div class="invalid-feedback" v-if="errors.password">{{ errors.password[0] }}</div>
                  </div>
               </div>
            </section>
         </div>

         <div class="col-xl-4">
            <div class="d-flex flex-column gap-3">
               <section class="border rounded-3 p-3 bg-light">
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                     <div>
                        <div class="fw-semibold">Biometric Fingerprint</div>
                        <div class="text-muted small">Enroll a fingerprint so this employee can scan for check-in and check-out.</div>
                     </div>
                     <span
                        v-if="biometricDisplayStatus"
                        :class="['m-badge', biometricStatusClass(biometricDisplayStatus)]"
                     >
                        {{ biometricStatusLabel(biometricDisplayStatus) }}
                     </span>
                  </div>

                  <div class="small text-muted mt-3">
                     <div v-if="employeeProfile?.hikvision_employee_no"><i class="bi bi-person-badge me-1"></i>{{ employeeProfile.hikvision_employee_no }}</div>
                     <div v-if="employeeProfile?.biometric_enrolled_at" class="mt-1"><i class="bi bi-clock me-1"></i>Enrolled {{ formatShortDateTime(employeeProfile.biometric_enrolled_at) }}</div>
                     <div v-if="employeeProfile?.biometric_last_error" class="mt-2 text-danger">{{ employeeProfile.biometric_last_error }}</div>
                  </div>

                  <div class="d-flex gap-2 flex-wrap mt-3">
                     <button class="btn btn-danger btn-sm" @click="openBiometricModal" :disabled="biometricSubmitting || biometricRemoving">
                        <span class="spinner-border spinner-border-sm me-1" v-if="biometricSubmitting"></span>
                        {{ hasEnrolledFingerprint ? "Re-enroll Fingerprint" : "Enroll Fingerprint" }}
                     </button>
                     <button
                        v-if="hasEnrolledFingerprint"
                        class="btn btn-outline-secondary btn-sm"
                        @click="removeFingerprint"
                        :disabled="biometricSubmitting || biometricRemoving"
                     >
                        <span class="spinner-border spinner-border-sm me-1" v-if="biometricRemoving"></span>
                        Remove Fingerprint
                     </button>
                  </div>
               </section>

               <section class="border rounded-3 p-3 bg-light" v-if="isPhilippinesPayroll">
                  <contribution-settings-fields :profile="form.employee_profile" :errors="errors" id-prefix="employee-settings" />
               </section>
            </div>
         </div>
      </div>

      <div class="mt-4 d-flex justify-content-end">
         <button class="btn btn-danger px-4" @click="save" :disabled="saving">
            <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
            Save Changes
         </button>
      </div>

      <div class="modal fade" tabindex="-1" ref="biometricModal" data-bs-backdrop="static" data-bs-keyboard="false">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Fingerprint Enrollment</h5>
                  <button v-if="canCloseBiometricModal" type="button" class="btn-close" @click="closeBiometricModal"></button>
               </div>
               <div class="modal-body p-4">
                  <div v-if="biometricError" class="alert alert-danger py-2 small mb-3">{{ biometricError }}</div>

                  <div class="text-center mb-4">
                     <div class="display-5 mb-3" :class="isBiometricBusy ? 'text-danger' : 'text-muted'">
                        <i class="bi" :class="isBiometricBusy ? 'bi-fingerprint' : 'bi-shield-check'"></i>
                     </div>
                     <h5 class="fw-bold mb-1">{{ biometricHeadline }}</h5>
                     <p class="text-muted small mb-0">{{ biometricDescription }}</p>
                  </div>

                  <div class="panel-card p-3">
                     <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                           <div class="small text-muted">Employee</div>
                           <div class="fw-semibold">{{ employee.name }}</div>
                        </div>
                        <span
                           v-if="biometricDisplayStatus"
                           :class="['m-badge', biometricStatusClass(biometricDisplayStatus)]"
                        >
                           {{ biometricStatusLabel(biometricDisplayStatus) }}
                        </span>
                     </div>

                     <div class="small text-muted mt-3">
                        <div v-if="employeeProfile?.hikvision_employee_no"><i class="bi bi-person-badge me-1"></i>{{ employeeProfile.hikvision_employee_no }}</div>
                        <div v-if="biometricSession?.error_message" class="text-danger mt-2">{{ biometricSession.error_message }}</div>
                        <div v-else-if="employeeProfile?.biometric_last_error" class="text-danger mt-2">{{ employeeProfile.biometric_last_error }}</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button
                     v-if="showBiometricStartButton"
                     type="button"
                     class="btn btn-danger"
                     @click="startBiometricEnrollment"
                     :disabled="isBiometricBusy"
                  >
                     <span class="spinner-border spinner-border-sm me-1" v-if="biometricSubmitting"></span>
                     {{ biometricActionLabel }}
                  </button>
                  <button v-if="canCloseBiometricModal" type="button" class="btn btn-outline-secondary" @click="closeBiometricModal">Close</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDateTime } from "../../../dates";
import MultiSelect from "./MultiSelect.vue";
import ContributionSettingsFields from "./ContributionSettingsFields.vue";

export default {
   components: {
      ContributionSettingsFields,
      MultiSelect,
   },
   props: {
      employee: { type: Object, required: true },
      rolesData: { type: Array, default: () => [] },
   },
   emits: ["updated"],
   data: function () {
      return {
         saving: false,
         saved: false,
         generalError: "",
         errors: {},
         form: this.getForm(this.employee),
         biometricModalInst: null,
         biometricSession: null,
         biometricError: "",
         biometricSubmitting: false,
         biometricRemoving: false,
         biometricPollHandle: null,
      };
   },
   mounted: function () {
      this.biometricModalInst = new Modal(this.$refs.biometricModal);
      this.$refs.biometricModal?.addEventListener("hide.bs.modal", this.onBiometricModalHide);
   },
   beforeUnmount: function () {
      this.clearBiometricPolling();
      this.$refs.biometricModal?.removeEventListener("hide.bs.modal", this.onBiometricModalHide);
      this.biometricModalInst?.dispose();
   },
   watch: {
      employee: function (val) {
         this.form = this.getForm(val);
      },
   },
   computed: {
      allowedRoles: function () {
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
      statusOptions: function () {
         return ["active", "inactive", "suspended"];
      },
      isPhilippinesPayroll: function () {
         return (window.JPrime?.profile?.country_code || "PH") === "PH";
      },
      employeeProfile: function () {
         return this.employee.employee_profile || null;
      },
      hasEnrolledFingerprint: function () {
         return this.employeeProfile?.biometric_fingerprint_id !== null;
      },
      biometricDisplayStatus: function () {
         return this.biometricSession?.status || this.employeeProfile?.biometric_status || "";
      },
      hasActiveBiometricSession: function () {
         return ["pending", "capturing", "uploading"].includes(this.biometricSession?.status || "");
      },
      isBiometricBusy: function () {
         return this.biometricSubmitting || this.hasActiveBiometricSession;
      },
      canCloseBiometricModal: function () {
         return !this.isBiometricBusy;
      },
      showBiometricStartButton: function () {
         if (this.isBiometricBusy) {
            return false;
         }

         if (!this.biometricSession) {
            return true;
         }

         return ["failed", "expired", "cancelled"].includes(this.biometricSession.status || "");
      },
      biometricActionLabel: function () {
         if (["failed", "expired", "cancelled"].includes(this.biometricSession?.status || "")) {
            return "Start Collecting Again";
         }

         return "Start Collecting";
      },
      biometricHeadline: function () {
         const status = this.biometricDisplayStatus;

         if (this.biometricSubmitting && !this.biometricSession) {
            return "Starting fingerprint collection";
         }

         if (this.hasActiveBiometricSession) {
            return "Please press your fingerprint";
         }

         if (!this.biometricSession) {
            return "Fingerprint enrollment";
         }

         if (status === "succeeded" || status === "enrolled") {
            return "Fingerprint enrolled";
         }

         if (status === "failed") {
            return "Fingerprint enrollment failed";
         }

         if (status === "expired") {
            return "Fingerprint enrollment timed out";
         }

         if (status === "cancelled") {
            return "Fingerprint enrollment cancelled";
         }

         return "Fingerprint enrollment";
      },
      biometricDescription: function () {
         if (this.biometricSubmitting && !this.biometricSession) {
            return "Please wait while fingerprint collection is being started.";
         }

         if (this.hasActiveBiometricSession) {
            return "Please press your fingerprint on the Hikvision device.";
         }

         if (!this.biometricSession) {
            return "Click Start Collecting when the employee is at the device.";
         }

         if (this.biometricSession?.error_message) {
            return this.biometricSession.error_message;
         }

         if (this.employeeProfile?.biometric_last_error) {
            return this.employeeProfile.biometric_last_error;
         }

         if (this.biometricDisplayStatus === "succeeded" || this.biometricDisplayStatus === "enrolled") {
            return "This employee can now use the device for biometric attendance.";
         }

         return "Collection failed or timed out. Try again when the employee is ready at the device.";
      },
   },
   methods: {
      employeeProfileForm: function (profile) {
         return {
            daily_rate: profile?.daily_rate ?? "",
            pt_commission_rate: profile?.pt_commission_rate ?? "",
            pay_frequency: profile?.pay_frequency || "semi_monthly",
            sss_covered: profile ? Boolean(profile.sss_covered) : this.isPhilippinesPayroll,
            sss_monthly_compensation: profile?.sss_monthly_compensation ?? "",
            philhealth_covered: profile ? Boolean(profile.philhealth_covered) : this.isPhilippinesPayroll,
            philhealth_monthly_basic_salary: profile?.philhealth_monthly_basic_salary ?? "",
            pagibig_covered: profile ? Boolean(profile.pagibig_covered) : this.isPhilippinesPayroll,
            pagibig_monthly_compensation: profile?.pagibig_monthly_compensation ?? "",
         };
      },
      getForm: function (employee) {
          return {
             name: employee.name || "",
             email: employee.email,
             phone: employee.phone || "",
             status: employee.status,
             role_ids: employee.roles ? employee.roles.map((r) => r.id) : [],
             employee_profile: this.employeeProfileForm(employee.employee_profile || null),
             password: "",
          };
       },
      save: function () {
         this.saving = true;
         this.saved = false;
         this.generalError = "";
         this.errors = {};

         axios
            .put(`/panel/employees/${this.employee.id}`, { ...this.form, role_ids: this.form.role_ids })
            .then((res) => {
               this.saved = true;
               this.form.password = "";
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
            .finally(() => {
               this.saving = false;
            });
      },
      openBiometricModal: function () {
         this.biometricError = "";
         this.biometricSession = null;
         this.clearBiometricPolling();
         this.biometricModalInst.show();
      },
      closeBiometricModal: function () {
         if (!this.canCloseBiometricModal) {
            return;
         }

         this.clearBiometricPolling();
         this.biometricModalInst.hide();
         this.biometricSession = null;
         this.biometricError = "";
         this.biometricSubmitting = false;
      },
      onBiometricModalHide: function (event) {
         if (this.isBiometricBusy) {
            event.preventDefault();
         }
      },
      startBiometricEnrollment: function () {
         if (this.isBiometricBusy) {
            return;
         }

         this.biometricSubmitting = true;
         this.biometricError = "";

         axios
            .post(`/panel/employees/${this.employee.id}/biometric/enroll`)
            .then((res) => {
               this.applyBiometricPayload(res.data);
            })
            .catch((err) => {
               this.biometricError = err.response?.data?.message || "Failed to start fingerprint enrollment.";
            })
            .finally(() => {
               this.biometricSubmitting = false;

               if (this.hasActiveBiometricSession) {
                  this.pollBiometricSession();
               }
            });
      },
      pollBiometricSession: function () {
         if (!this.biometricSession?.id || this.biometricSubmitting) {
            return;
         }

         this.biometricPollHandle = window.setTimeout(() => {
            axios
               .get(`/panel/employees/${this.employee.id}/biometric/sessions/${this.biometricSession.id}`)
               .then((res) => {
                  this.applyBiometricPayload(res.data);
               })
               .catch((err) => {
                  this.clearBiometricPolling();
                  this.biometricSession = null;
                  this.biometricError = err.response?.data?.message || "Failed to refresh fingerprint enrollment status.";
               });
         }, 1500);
      },
      removeFingerprint: function () {
         this.biometricRemoving = true;
         this.biometricError = "";
         this.generalError = "";

         axios
            .delete(`/panel/employees/${this.employee.id}/biometric/fingerprint`)
            .then((res) => {
               this.syncEmployeeProfile(res.data.employee_profile || null);
            })
            .catch((err) => {
               this.generalError = err.response?.data?.message || "Failed to remove fingerprint.";
            })
            .finally(() => {
               this.biometricRemoving = false;
            });
      },
      applyBiometricPayload: function (payload) {
         this.biometricSession = payload.session || this.biometricSession;
         this.syncEmployeeProfile(payload.employee_profile || null);
         this.clearBiometricPolling();

         if (this.hasActiveBiometricSession) {
            this.pollBiometricSession();
         }
      },
      syncEmployeeProfile: function (employeeProfile) {
         this.$emit("updated", {
            employee_profile: employeeProfile,
         });
      },
      clearBiometricPolling: function () {
         if (this.biometricPollHandle) {
            window.clearTimeout(this.biometricPollHandle);
            this.biometricPollHandle = null;
         }
      },
      biometricStatusLabel: function (status) {
         return {
            not_enrolled: "Not Enrolled",
            enrolling: "Enrolling",
            pending: "Waiting For Scan",
            capturing: "Capturing",
            uploading: "Uploading",
            enrolled: "Enrolled",
            succeeded: "Enrolled",
            failed: "Failed",
            expired: "Timed Out",
            cancelled: "Cancelled",
         }[status] || this.$filters.capitalize(status || "unknown");
      },
      biometricStatusClass: function (status) {
         return {
            not_enrolled: "m-badge--draft",
            enrolling: "m-badge--pending",
            pending: "m-badge--pending",
            capturing: "m-badge--pending",
            uploading: "m-badge--pending",
            enrolled: "m-badge--active",
            succeeded: "m-badge--active",
            failed: "m-badge--suspended",
            expired: "m-badge--suspended",
            cancelled: "m-badge--inactive",
         }[status] || "m-badge--draft";
      },
      formatShortDateTime: function (value) {
         return value ? formatDateTime(value) : "";
      },
   },
};
</script>
