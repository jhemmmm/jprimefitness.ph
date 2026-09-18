<template>
   <div class="modal fade" tabindex="-1" ref="modal">
      <div class="modal-dialog">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title fw-bold">Log PT Session</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
               <div v-if="generalError" class="alert alert-danger py-2 small mb-3">{{ generalError }}</div>
               <div class="row g-3">
                  <div class="col-12">
                     <label class="form-label form-label-sm fw-semibold">Trainee</label>
                     <select class="form-select" v-model="form.member_pt_package_id" :class="{ 'is-invalid': errors.member_pt_package_id }">
                        <option disabled value="">Select a trainee...</option>
                        <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.member_name }} • {{ pkg.plan_name }} • {{ pkg.remaining_sessions }}/{{ pkg.total_sessions }} left</option>
                     </select>
                     <div class="invalid-feedback" v-if="errors.member_pt_package_id">{{ errors.member_pt_package_id }}</div>
                  </div>
                  <div class="col-md-4">
                     <label class="form-label form-label-sm fw-semibold">Sessions Used</label>
                     <input type="number" min="1" class="form-control" v-model.number="form.sessions_used" :class="{ 'is-invalid': errors.sessions_used }" />
                     <div class="invalid-feedback" v-if="errors.sessions_used">{{ errors.sessions_used }}</div>
                  </div>
                  <div class="col-md-8">
                     <label class="form-label form-label-sm fw-semibold">Used At</label>
                     <input type="datetime-local" class="form-control" v-model="form.used_at" :class="{ 'is-invalid': errors.used_at }" />
                     <div class="invalid-feedback" v-if="errors.used_at">{{ errors.used_at }}</div>
                  </div>
                  <div class="col-12">
                     <label class="form-label form-label-sm fw-semibold">Notes</label>
                     <input type="text" class="form-control" v-model="form.notes" :class="{ 'is-invalid': errors.notes }" placeholder="Optional session note" />
                     <div class="invalid-feedback" v-if="errors.notes">{{ errors.notes }}</div>
                  </div>
               </div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-outline-success btn-sm" @click="submit" :disabled="saving || !form.member_pt_package_id">
                  <span class="spinner-border spinner-border-sm me-1" v-if="saving"></span>
                  Log Session
               </button>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { toDateTimeInputValue } from "../../../dates";
import { firstErrors } from "../../../http";

/**
 * Coach-side "log a session" modal. `packages` are the coach's active packages
 * ({id, member_name, plan_name, remaining_sessions, total_sessions}); parents
 * call `open(packageId)` via a ref and refetch on `logged`.
 */
export default {
   props: {
      packages: { type: Array, default: () => [] },
   },
   emits: ["logged"],
   data: function () {
      return {
         modal: null,
         form: { member_pt_package_id: "", sessions_used: 1, used_at: "", notes: "" },
         errors: {},
         generalError: "",
         saving: false,
      };
   },
   methods: {
      open: function (packageId) {
         this.generalError = "";
         this.errors = {};
         this.form = { member_pt_package_id: packageId || "", sessions_used: 1, used_at: toDateTimeInputValue(), notes: "" };
         if (!this.modal) {
            this.modal = new Modal(this.$refs.modal);
         }
         this.modal.show();
      },
      submit: function () {
         this.saving = true;
         this.generalError = "";
         this.errors = {};

         axios
            .post("/panel/pt-sessions", this.form)
            .then((res) => {
               this.modal.hide();
               this.$emit("logged", res.data);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  this.errors = firstErrors(err.response.data.errors);
               } else {
                  this.generalError = err.response?.data?.message || "Failed to log the session.";
               }
            })
            .finally(() => (this.saving = false));
      },
   },
};
</script>
