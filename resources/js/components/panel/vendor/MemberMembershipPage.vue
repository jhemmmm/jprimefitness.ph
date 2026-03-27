<template>
   <div class="p-3">
      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Membership updated successfully.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-3 mb-4">
         <div class="col-md-4" v-for="stat in statCards" :key="stat.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="stat.iconBg"><i class="bi" :class="[stat.icon, stat.iconColor]"></i></div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ stat.label }}</div>
                  <div class="stat-card-value">{{ stat.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <div class="d-flex flex-wrap justify-content-end gap-2 mb-4" v-if="canManageMembership">
               <button class="btn btn-danger btn-sm" @click="openPlanModal">
                  <i class="bi bi-arrow-repeat me-1"></i>
                  Change Plan
               </button>
               <button v-if="currentMembership && currentMembership.status === 'active'" class="btn btn-outline-warning btn-sm" @click="updateStatus('paused')" :disabled="savingStatus">
                  <span class="spinner-border spinner-border-sm me-1" v-if="savingStatus"></span>
                  Pause
               </button>
               <button v-if="currentMembership && currentMembership.status === 'paused'" class="btn btn-outline-success btn-sm" @click="updateStatus('active')" :disabled="savingStatus">
                  <span class="spinner-border spinner-border-sm me-1" v-if="savingStatus"></span>
                  Resume
               </button>
               <button v-if="currentMembership && ['active', 'paused'].includes(currentMembership.status)" class="btn btn-outline-danger btn-sm" @click="updateStatus('cancelled')" :disabled="savingStatus">
                  <span class="spinner-border spinner-border-sm me-1" v-if="savingStatus"></span>
                  Cancel
               </button>
      </div>

      <div class="modal fade" tabindex="-1" ref="planModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Change Membership Plan</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="row g-3">
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Plan</label>
                        <select class="form-select" v-model="form.rate_plan_id">
                           <option value="">Select a plan...</option>
                           <option v-for="plan in ratePlansData" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                        </select>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Start Date</label>
                        <input type="date" class="form-control" v-model="form.start_date" />
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" @click="savePlanChange" :disabled="savingPlan || !form.rate_plan_id">
                  <span class="spinner-border spinner-border-sm me-1" v-if="savingPlan"></span>
                  Change Plan
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div v-if="memberships.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-postcard fs-1 d-block mb-2 opacity-25"></i>
         <div>No membership history found.</div>
      </div>

      <div v-else class="d-none d-md-block">
         <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
               <tr>
                  <th>Plan</th>
                  <th>Status</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Assigned</th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="membership in memberships" :key="membership.id">
                  <td class="fw-semibold">{{ membership.rate_plan.name }}</td>
                  <td><span class="m-badge" :class="planStatusClass(membership.status)">{{ $filters.capitalize(membership.status) }}</span></td>
                  <td class="small">{{ $filters.formatDate(membership.start_date) }}</td>
                  <td class="small">{{ $filters.formatDate(membership.end_date) }}</td>
                  <td class="small text-muted">{{ $filters.formatDateTime(membership.created_at) }}</td>
               </tr>
            </tbody>
         </table>
      </div>

      <div class="d-md-none" v-if="memberships.length">
         <div class="member-card" v-for="membership in memberships" :key="'mm' + membership.id">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold">{{ membership.rate_plan.name }}</div>
                  <div class="text-muted small">Start {{ $filters.formatDate(membership.start_date) }}</div>
               </div>
               <span class="m-badge" :class="planStatusClass(membership.status)">{{ $filters.capitalize(membership.status) }}</span>
            </div>
            <div class="member-card-footer">
               <span><i class="bi bi-calendar3 me-1"></i>{{ $filters.formatDate(membership.end_date) }}</span>
               <span class="text-muted small">{{ $filters.formatDateTime(membership.created_at) }}</span>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";

export default {
   props: {
      member: { type: Object, required: true },
      ratePlansData: { type: Array, default: () => [] },
   },

   emits: ["updated"],

   data() {
      return {
         savingPlan: false,
         savingStatus: false,
         saved: false,
         generalError: "",
         planModalInst: null,
         form: {
            rate_plan_id: "",
            start_date: new Date().toISOString().slice(0, 10),
         },
      };
   },

   mounted() {
      this.planModalInst = new Modal(this.$refs.planModal);
   },

   watch: {
      member: {
         immediate: true,
         handler() {
            this.form = {
               rate_plan_id: this.currentMembership?.rate_plan_id || "",
               start_date: this.currentMembership?.start_date || new Date().toISOString().slice(0, 10),
            };
         },
      },
   },

   computed: {
      currentMembership() {
         return this.memberships.find((membership) => ["active", "paused"].includes(membership.status)) || null;
      },

      canManageMembership() {
         return this.is("super admin") || this.is("admin") || this.is("manager");
      },

      memberships() {
         const statusPriority = {
            active: 0,
            paused: 1,
            expired: 2,
            cancelled: 3,
         };

         return [...(this.member.member_subscriptions || [])].sort((left, right) => {
            const leftPriority = statusPriority[left.status] ?? 99;
            const rightPriority = statusPriority[right.status] ?? 99;

            if (leftPriority !== rightPriority) {
               return leftPriority - rightPriority;
            }

            const leftDate = left.start_date || left.created_at || "";
            const rightDate = right.start_date || right.created_at || "";
            return String(rightDate).localeCompare(String(leftDate));
         });
      },

      statCards() {
         return [
            { label: "Total Plans", value: this.memberships.length, icon: "bi-postcard", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Active", value: this.memberships.filter((membership) => membership.status === "active").length, icon: "bi-check-circle", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Past", value: this.memberships.filter((membership) => membership.status !== "active").length, icon: "bi-clock-history", iconBg: "bg-warning-soft", iconColor: "text-warning" },
         ];
      },
   },

   methods: {
      openPlanModal() {
         this.generalError = "";
         this.form = {
            rate_plan_id: this.currentMembership?.rate_plan_id || "",
            start_date: this.currentMembership?.start_date || new Date().toISOString().slice(0, 10),
         };
         this.planModalInst.show();
      },

      savePlanChange() {
         this.savingPlan = true;
         this.saved = false;
         this.generalError = "";
         axios
            .put(`/panel/members/${this.member.id}/membership`, this.form)
            .then((res) => {
               this.saved = true;
               this.$emit("updated", res.data);
               this.planModalInst.hide();
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((err) => (this.generalError = err.response?.data?.message || "Failed to update membership plan."))
            .finally(() => (this.savingPlan = false));
      },

      updateStatus(status) {
         this.savingStatus = true;
         this.saved = false;
         this.generalError = "";
         axios
            .put(`/panel/members/${this.member.id}/membership/status`, { status })
            .then((res) => {
               this.saved = true;
               this.$emit("updated", res.data);
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((err) => (this.generalError = err.response?.data?.message || "Failed to update membership status."))
            .finally(() => (this.savingStatus = false));
      },

      planStatusClass(status) {
         return {
            active: "m-badge--plan-active",
            expired: "m-badge--plan-expired",
            cancelled: "m-badge--plan-cancelled",
            paused: "m-badge--plan-paused",
         }[status] || "m-badge--plan-expired";
      },
   },
};
</script>
