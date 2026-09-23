<template>
   <div class="p-3">
      <div class="alert alert-success py-2 small" v-if="saved"><i class="bi bi-check-circle me-1"></i>Membership updated successfully.</div>
      <div class="alert alert-danger py-2 small" v-if="generalError">{{ generalError }}</div>

      <div class="row g-3 mb-4">
         <div class="col-md-4" v-for="stat in statCards" :key="stat.label">
            <div class="stat-card">
               <div class="stat-card-icon"><i class="bi" :class="stat.icon"></i></div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ stat.label }}</div>
                  <div class="stat-card-value">{{ stat.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <!-- Plans are sold, not assigned: issuing or renewing one happens in Sales. -->
      <div class="d-flex flex-wrap justify-content-end gap-2 mb-3" v-if="canManageMembership">
         <button v-if="currentMembership && currentMembership.status === 'active'" class="btn btn-outline-warning btn-sm" @click="updateStatus('paused')" :disabled="savingStatus">
            <span class="spinner-border spinner-border-sm me-1" v-if="savingStatus"></span>
            Pause
         </button>
         <button v-if="currentMembership && currentMembership.status === 'paused'" class="btn btn-outline-success btn-sm" @click="updateStatus('active')" :disabled="savingStatus">
            <span class="spinner-border spinner-border-sm me-1" v-if="savingStatus"></span>
            Resume
         </button>
         <button v-if="currentMembership && ['active', 'paused'].includes(currentMembership.status)" class="btn btn-outline-danger btn-sm" @click="openCancelModal" :disabled="savingStatus">
            <span class="spinner-border spinner-border-sm me-1" v-if="savingStatus"></span>
            Cancel
         </button>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-12">
            <div class="border rounded-3 p-3 h-100 bg-light">
               <div class="d-flex align-items-start justify-content-between gap-3">
                  <div>
                     <div class="text-muted text-uppercase small fw-semibold">Current Membership</div>
                     <div v-if="currentMembership" class="fw-semibold fs-6 mt-1">{{ currentMembership.rate_plan?.name || "Membership Plan" }}</div>
                     <div v-else class="fw-semibold fs-6 mt-1">No current membership</div>
                  </div>
                  <span v-if="currentMembership" class="m-badge" :class="planStatusClass(currentMembership.status)">{{ $filters.capitalize(currentMembership.status) }}</span>
               </div>

               <div v-if="currentMembership" class="row g-3 mt-1 small">
                  <div class="col-sm-6">
                     <div class="text-muted">Start Date</div>
                     <div class="fw-semibold">{{ formatDate(currentMembership.start_date) }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted">End Date</div>
                     <div class="fw-semibold">{{ formatDate(currentMembership.end_date) }}</div>
                  </div>
                  <div class="col-sm-6">
                     <div class="text-muted">Recorded</div>
                     <div class="fw-semibold">{{ formatDateTime(currentMembership.created_at) }}</div>
                  </div>
               </div>
               <div v-else class="text-muted small mt-3">Sell a membership plan in Sales to start tracking membership activity here.</div>
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
                  <th class="text-end">QR</th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="membership in memberships" :key="membership.id">
                  <td class="fw-semibold">{{ membership.rate_plan.name }}</td>
                  <td>
                     <span class="m-badge" :class="planStatusClass(membership.status)">{{ $filters.capitalize(membership.status) }}</span>
                  </td>
                  <td class="small">{{ formatDate(membership.start_date) }}</td>
                  <td class="small">{{ formatDate(membership.end_date) }}</td>
                  <td class="small text-muted">{{ formatDateTime(membership.created_at) }}</td>
                  <td class="text-end">
                     <button type="button" class="btn btn-sm btn-outline-secondary" v-if="membership.qr_url" @click="openMembershipQr(membership)" title="View membership QR">
                        <i class="bi bi-qr-code tbl-icon"></i>
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
      </div>

      <div class="d-md-none" v-if="memberships.length">
         <div class="member-card" v-for="membership in memberships" :key="'mm' + membership.id">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold">{{ membership.rate_plan.name }}</div>
                  <div class="text-muted small">Start {{ formatDate(membership.start_date) }}</div>
               </div>
               <span class="m-badge" :class="planStatusClass(membership.status)">{{ $filters.capitalize(membership.status) }}</span>
            </div>
            <div class="member-card-footer">
               <span><i class="bi bi-calendar3 me-1"></i>{{ formatDate(membership.end_date) }}</span>
               <span class="text-muted small">{{ formatDateTime(membership.created_at) }}</span>
               <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" v-if="membership.qr_url" @click="openMembershipQr(membership)">QR</button>
            </div>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="cancelMembershipModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Cancel Membership</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="savingStatus"></button>
               </div>
               <div class="modal-body">
                  <div v-if="cancelError" class="alert alert-danger py-2 small">{{ cancelError }}</div>
                  <p class="small mb-3">
                     Cancel <strong>{{ currentMembership?.rate_plan?.name || "this membership" }}</strong> for
                     <strong>{{ member.name }}</strong>? They will be turned away at the kiosk from now on.
                  </p>
                  <div>
                     <label class="form-label">Reason <span class="text-danger">*</span></label>
                     <textarea class="form-control" rows="3" v-model="cancelReason" :class="{ 'is-invalid': cancelErrors.reason }" placeholder="Enter the reason this membership is being cancelled"></textarea>
                     <div class="invalid-feedback" v-if="cancelErrors.reason">{{ cancelErrors.reason }}</div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="savingStatus">Back</button>
                  <button type="button" class="btn btn-danger px-4" @click="confirmCancel" :disabled="savingStatus || !cancelReason.trim()">
                     <span v-if="savingStatus" class="spinner-border spinner-border-sm me-1"></span>
                     Cancel Membership
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="membershipQrModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Membership QR Code</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div v-if="qrError" class="alert alert-danger py-2 small">{{ qrError }}</div>
                  <div v-if="loadingQr" class="text-center py-4 text-muted">
                     <span class="spinner-border spinner-border-sm me-2"></span>
                     Loading QR code...
                  </div>
                  <div v-else-if="selectedQr" class="text-center">
                     <img :src="selectedQr.qr_data_uri" alt="Membership QR Code" class="img-fluid mb-3 membership-qr-image" />
                     <div class="fw-semibold">{{ selectedQr.member_name }}</div>
                     <div class="small text-muted">{{ selectedQr.plan_name }}</div>
                     <div class="small text-muted mt-1">Valid {{ formatDate(selectedQr.start_date) }} - {{ selectedQr.end_date ? formatDate(selectedQr.end_date) : "Open-ended" }}</div>
                  </div>
               </div>
               <div class="modal-footer" v-if="selectedQr">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="button" class="btn btn-primary" @click="printMembershipCardAction">
                     <i class="bi bi-printer me-1"></i>
                     Print
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDate, formatDateTime } from "../../../dates";
import { firstErrors } from "../../../http";
import { printMembershipCard } from "../../../print-membership-card";

export default {
   props: {
      member: { type: Object, required: true },
   },

   emits: ["updated"],

   data: function () {
      return {
         savingStatus: false,
         saved: false,
         generalError: "",
         qrModalInst: null,
         cancelModalInst: null,
         cancelReason: "",
         cancelError: "",
         cancelErrors: {},
         loadingQr: false,
         selectedQr: null,
         qrError: "",
      };
   },

   mounted: function () {
      this.qrModalInst = new Modal(this.$refs.membershipQrModal);
      this.cancelModalInst = new Modal(this.$refs.cancelMembershipModal);
   },

   computed: {
      currentMembership: function () {
         return this.memberships.find((membership) => ["active", "paused"].includes(membership.status)) || null;
      },

      canManageMembership: function () {
         return this.can("edit members");
      },

      memberships: function () {
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

      statCards: function () {
         return [
            { label: "Total Plans", value: this.memberships.length, icon: "bi-postcard" },
            { label: "Active", value: this.memberships.filter((membership) => membership.status === "active").length, icon: "bi-check-circle" },
            { label: "Past", value: this.memberships.filter((membership) => membership.status !== "active").length, icon: "bi-clock-history" },
         ];
      },
   },

   methods: {
      formatDate,
      formatDateTime,
      openCancelModal: function () {
         this.cancelReason = "";
         this.cancelError = "";
         this.cancelErrors = {};
         this.cancelModalInst.show();
      },

      confirmCancel: function () {
         this.cancelError = "";
         this.cancelErrors = {};
         this.updateStatus("cancelled", this.cancelReason);
      },

      updateStatus: function (status, reason) {
         this.savingStatus = true;
         this.saved = false;
         this.generalError = "";
         axios
            .put(`/panel/members/${this.member.id}/membership/status`, { status, reason })
            .then((res) => {
               this.saved = true;
               this.cancelModalInst.hide();
               this.$emit("updated", res.data);
               setTimeout(() => (this.saved = false), 3000);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  // Hold the modal open so a typed reason survives a validation bounce.
                  this.cancelErrors = firstErrors(err.response.data.errors);
                  if (!this.cancelErrors.reason) {
                     this.cancelError = err.response.data.message || "Failed to update membership status.";
                  }
                  return;
               }

               this.cancelModalInst.hide();
               this.generalError = err.response?.data?.message || "Failed to update membership status.";
            })
            .finally(() => (this.savingStatus = false));
      },

      openMembershipQr: function (membership) {
         if (!membership.qr_url) {
            return;
         }

         this.selectedQr = null;
         this.qrError = "";
         this.loadingQr = true;
         this.qrModalInst.show();

         axios
            .get(membership.qr_url)
            .then((response) => {
               this.selectedQr = response.data;
            })
            .catch((err) => {
               this.qrError = err.response?.data?.message || "Failed to load membership QR code.";
            })
            .finally(() => {
               this.loadingQr = false;
            });
      },

      printMembershipCardAction: function () {
         if (!this.selectedQr) {
            return;
         }

         var opened = printMembershipCard(this.selectedQr);
         if (!opened) {
            this.qrError = "Unable to open the print window. Please allow pop-ups for this site.";
         }
      },

      planStatusClass: function (status) {
         return (
            {
               active: "m-badge--plan-active",
               expired: "m-badge--plan-expired",
               cancelled: "m-badge--plan-cancelled",
               paused: "m-badge--plan-paused",
            }[status] || "m-badge--plan-expired"
         );
      },

   },
};
</script>

<style scoped>
.membership-qr-image {
   width: 260px;
   max-width: 100%;
}
</style>
