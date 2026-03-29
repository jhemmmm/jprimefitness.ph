<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <!-- Stats -->
      <div class="row g-3 mb-4" v-if="loading">
         <div class="col-6 col-md-3" v-for="i in 4" :key="'sk-s-' + i">
            <div class="stat-card">
               <div class="skeleton-box rounded-circle flex-shrink-0" style="width: 40px; height: 40px"></div>
               <div class="stat-card-body">
                  <div class="skeleton-box mb-2" style="height: 11px; width: 65%"></div>
                  <div class="skeleton-box" style="height: 18px; width: 40%"></div>
               </div>
            </div>
         </div>
      </div>
      <div class="row g-3 mb-4" v-else>
         <div class="col-6 col-md-3" v-for="s in statCards" :key="s.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="s.iconBg"><i class="bi" :class="[s.icon, s.iconColor]"></i></div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ s.label }}</div>
                  <div class="stat-card-value small">{{ s.isMoney ? "₱" + $filters.formatMoney(s.value) : s.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <!-- Actions bar -->
      <div class="d-flex justify-content-between align-items-center mb-3">
         <div class="text-muted small" v-if="!loading">{{ advances.length }} record{{ advances.length !== 1 ? "s" : "" }}</div>
         <div class="skeleton-box" v-else style="height: 14px; width: 80px; border-radius: 4px"></div>
         <button class="btn btn-danger btn-sm" @click="openCreate"><i class="bi bi-plus-lg me-1"></i>New Cash Advance</button>
      </div>

      <!-- Loading -->
      <div v-if="loading">
         <div class="skeleton-box" v-for="i in 3" :key="i" style="height: 56px; border-radius: 6px; margin-bottom: 8px"></div>
      </div>

      <!-- Empty -->
      <div v-else-if="advances.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-25"></i>
         <div>No cash advances recorded.</div>
      </div>

      <!-- Desktop table -->
      <div class="d-none d-md-block" v-else>
         <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
               <tr>
                  <th>Date</th>
                  <th class="text-end">Amount</th>
                  <th class="text-end">Deducted</th>
                  <th class="text-end">Remaining</th>
                  <th>Status</th>
                  <th>Notes</th>
                  <th class="col-actions"></th>
               </tr>
            </thead>
            <tbody>
               <template v-for="a in advances" :key="a.id">
                  <tr role="button" @click="toggleAudit(a.id)">
                     <td class="small">{{ $filters.formatDate(a.requested_at) }}</td>
                     <td class="text-end small fw-semibold">₱{{ $filters.formatMoney(a.amount) }}</td>
                     <td class="text-end small text-success">{{ a.deducted_amount > 0 ? "₱" + $filters.formatMoney(a.deducted_amount) : "—" }}</td>
                     <td class="text-end small" :class="a.remaining_amount > 0 ? 'text-danger' : 'text-success'">
                        {{ a.remaining_amount > 0 ? "₱" + $filters.formatMoney(a.remaining_amount) : "✓" }}
                     </td>
                     <td>
                        <span :class="['m-badge', $filters.statusBadge(a.status)]">{{ $filters.capitalize(a.status) }}</span>
                     </td>
                     <td class="small text-muted">{{ a.notes || "—" }}</td>
                     <td>
                        <div class="d-flex gap-1">
                           <button v-if="canApproveAdvance(a.status)" class="btn btn-sm btn-outline-success" title="Approve" :disabled="isActioning(a.id, 'approved')" @click.stop="setAdvanceStatus(a, 'approved')">
                              <span v-if="isActioning(a.id, 'approved')" class="spinner-border spinner-border-sm"></span>
                              <i v-else class="bi bi-check-lg tbl-icon"></i>
                           </button>
                           <button v-if="canReleaseAdvance(a.status)" class="btn btn-sm btn-outline-primary" title="Release" :disabled="isActioning(a.id, 'released')" @click.stop="setAdvanceStatus(a, 'released')">
                              <span v-if="isActioning(a.id, 'released')" class="spinner-border spinner-border-sm"></span>
                              <i v-else class="bi bi-box-arrow-up-right tbl-icon"></i>
                           </button>
                           <button v-if="canManageAdvance(a.status)" class="btn btn-sm btn-outline-secondary" title="Edit" @click.stop="openEdit(a)">
                              <i class="bi bi-pencil tbl-icon"></i>
                           </button>
                           <button v-if="canCancelAdvance(a.status)" class="btn btn-sm btn-outline-warning" title="Cancel" :disabled="isActioning(a.id, 'cancelled')" @click.stop="setAdvanceStatus(a, 'cancelled')">
                              <span v-if="isActioning(a.id, 'cancelled')" class="spinner-border spinner-border-sm"></span>
                              <i v-else class="bi bi-x-circle tbl-icon"></i>
                           </button>
                           <i class="bi text-muted align-self-center" :class="expandedAdvanceId === a.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </div>
                     </td>
                  </tr>
                  <tr v-if="expandedAdvanceId === a.id">
                     <td colspan="7" class="bg-light">
                        <div class="p-3">
                           <div class="small fw-semibold mb-2">Audit History</div>
                           <div class="d-flex flex-column gap-2">
                              <div v-for="event in auditEvents(a)" :key="`${a.id}-${event.key}`" class="d-flex justify-content-between align-items-start border rounded px-3 py-2 bg-white">
                                 <div>
                                    <div class="small fw-semibold">{{ event.label }}</div>
                                    <div class="small text-muted">{{ event.by_name || "—" }}</div>
                                    <div v-if="event.details" class="small text-muted mt-1">{{ event.details }}</div>
                                 </div>
                                 <div class="small text-muted text-end">{{ $filters.formatDateTime(event.at) }}</div>
                              </div>
                           </div>
                        </div>
                     </td>
                  </tr>
               </template>
            </tbody>
         </table>
      </div>

      <!-- Mobile cards -->
      <div class="d-md-none" v-if="!loading && advances.length">
         <div class="member-card" v-for="a in advances" :key="'ca' + a.id" role="button" @click="toggleAudit(a.id)">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold small">₱{{ $filters.formatMoney(a.amount) }}</div>
                  <div class="text-muted small">{{ $filters.formatDate(a.requested_at) }}</div>
               </div>
               <div class="d-flex gap-2 align-items-center">
                  <span :class="['m-badge', $filters.statusBadge(a.status)]">{{ $filters.capitalize(a.status) }}</span>
                  <div class="dropdown" v-if="canManageAdvance(a.status)">
                     <button class="btn-icon-sm" data-bs-toggle="dropdown" @click.stop><i class="bi bi-three-dots-vertical"></i></button>
                     <ul class="dropdown-menu dropdown-menu-end">
                        <li v-if="canApproveAdvance(a.status)">
                           <a class="dropdown-item text-success" href="#" @click.prevent.stop="setAdvanceStatus(a, 'approved')"><i class="bi bi-check-lg me-2"></i>Approve</a>
                        </li>
                        <li v-if="canReleaseAdvance(a.status)">
                           <a class="dropdown-item text-primary" href="#" @click.prevent.stop="setAdvanceStatus(a, 'released')"><i class="bi bi-box-arrow-up-right me-2"></i>Release</a>
                        </li>
                        <li>
                           <a class="dropdown-item" href="#" @click.prevent.stop="openEdit(a)"><i class="bi bi-pencil me-2"></i>Edit</a>
                        </li>
                        <li v-if="canCancelAdvance(a.status)">
                           <a class="dropdown-item text-warning" href="#" @click.prevent.stop="setAdvanceStatus(a, 'cancelled')"><i class="bi bi-x-circle me-2"></i>Cancel</a>
                        </li>
                     </ul>
                  </div>
                  <i class="bi text-muted" :class="expandedAdvanceId === a.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
               </div>
            </div>
            <div class="member-card-tags ps-0 mt-1">
               <span class="small text-danger" v-if="a.remaining_amount > 0">Remaining: ₱{{ $filters.formatMoney(a.remaining_amount) }}</span>
               <span class="small text-success" v-else>Paid</span>
               <span class="small text-muted" v-if="a.notes"> · {{ a.notes }}</span>
            </div>
            <div v-if="expandedAdvanceId === a.id" class="mt-3 border-top pt-3">
               <div class="small fw-semibold mb-2">Audit History</div>
               <div class="d-flex flex-column gap-2">
                  <div v-for="event in auditEvents(a)" :key="`mobile-${a.id}-${event.key}`" class="border rounded px-3 py-2 bg-light">
                     <div class="small fw-semibold">{{ event.label }}</div>
                     <div class="small text-muted">{{ event.by_name || "—" }}</div>
                     <div v-if="event.details" class="small text-muted mt-1">{{ event.details }}</div>
                     <div class="small text-muted mt-1">{{ $filters.formatDateTime(event.at) }}</div>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <!-- Create / Edit Modal -->
      <div class="modal fade" tabindex="-1" ref="caModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ modalMode === "create" ? "New Cash Advance" : "Edit Cash Advance" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="formError">{{ formError }}</div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Amount (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="form.amount" min="1" step="0.01" :class="{ 'is-invalid': formErrors.amount }" />
                        <div class="invalid-feedback">{{ formErrors.amount }}</div>
                     </div>
                     <div class="col-md-6" v-if="modalMode === 'edit'">
                        <label class="form-label form-label-sm">Status <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.status" :class="{ 'is-invalid': formErrors.status }">
                           <option v-for="status in statusOptions" :key="status" :value="status">{{ $filters.capitalize(status) }}</option>
                        </select>
                        <div class="invalid-feedback">{{ formErrors.status }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Date</label>
                        <input type="datetime-local" class="form-control" v-model="form.requested_at" />
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <textarea class="form-control" rows="2" v-model="form.notes" placeholder="Optional"></textarea>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="submitting" @click="submit">
                     <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
                     {{ modalMode === "create" ? "Create" : "Save" }}
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";

export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         submitting: false,
         actioning: "",
         expandedAdvanceId: null,
         advances: [],
         stats: { remaining_amount: 0, released_count: 0, partially_paid_count: 0, paid_count: 0 },
         pageError: "",
         modalMode: "create",
         form: this.emptyForm(),
         formError: "",
         formErrors: {},
         caModalInst: null,
         editingAdvance: null,
      };
   },

   mounted: function () {
      this.caModalInst = new Modal(this.$refs.caModal);
      this.fetchAdvances();
   },
   methods: {
      fetchAdvances: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get(`/panel/employees/${this.employee.id}/cash-advances`)
            .then((res) => {
               this.advances = res.data.advances;
               this.stats = res.data.stats;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load cash advances."))
            .finally(() => (this.loading = false));
      },

      replaceAdvance: function (advance) {
         const index = this.advances.findIndex((item) => item.id === advance.id);
         if (index !== -1) {
            this.advances.splice(index, 1, advance);
         }
      },

      setAdvanceStatus: function (advance, status) {
         this.actioning = `${advance.id}:${status}`;
         this.pageError = "";

         axios
            .put(`/panel/employees/${this.employee.id}/cash-advances/${advance.id}`, { status })
            .then((res) => {
               this.replaceAdvance(res.data);
               this.fetchAdvances();
            })
            .catch((err) => {
               this.pageError = err.response?.data?.message || "Failed to update cash advance.";
            })
            .finally(() => (this.actioning = ""));
      },

      toggleAudit: function (id) {
         this.expandedAdvanceId = this.expandedAdvanceId === id ? null : id;
      },

      auditEvents: function (advance) {
         const auditData = Array.isArray(advance.audit_data)
            ? advance.audit_data
                 .filter((event) => event?.at)
                 .map((event, index) => ({
                    key: `${event.event || "event"}-${index}`,
                    event: event.event,
                    label: this.auditEventLabel(event.event),
                    at: event.at,
                    by_name: event.by_name,
                    details: this.auditEventDetails(event),
                 }))
            : [];

         const recordedEvents = new Set(auditData.map((event) => event.event));
         const legacyEvents = [
            { key: "requested", event: "requested", label: "Requested", at: advance.requested_at, by_name: this.employee.name },
            { key: "approved", event: "approved", label: "Approved", at: advance.approved_at, by_name: advance.approved_by_name },
            { key: "released", event: "released", label: "Released", at: advance.released_at, by_name: advance.released_by_name },
            { key: "paid", event: "paid", label: "Paid", at: advance.paid_at, by_name: null },
            { key: "cancelled", event: "cancelled", label: "Cancelled", at: advance.cancelled_at, by_name: advance.cancelled_by_name },
         ].filter((event) => event.at && !recordedEvents.has(event.event));

         return [...legacyEvents, ...auditData].sort((left, right) => new Date(left.at) - new Date(right.at));
      },

      auditEventLabel: function (event) {
         return (
            {
               requested: "Requested",
               approved: "Approved",
               released: "Released",
               partially_paid: "Partially Paid",
               paid: "Paid",
               cancelled: "Cancelled",
            }[event] || "Updated"
         );
      },

      auditEventDetails: function (event) {
         const details = [];

         if (event.deducted_amount !== undefined && event.deducted_amount !== null) {
            const source = event.source === "payroll" && event.source_id ? ` via payroll #${event.source_id}` : "";
            details.push(`Deducted ₱${this.$filters.formatMoney(event.deducted_amount)}${source}. Remaining: ₱${this.$filters.formatMoney(event.remaining_after ?? 0)}.`);
         }

         if (event.notes) {
            details.push(event.notes);
         }

         return details.join(" ");
      },

      openCreate: function () {
         this.modalMode = "create";
         this.editingAdvance = null;
         this.form = this.emptyForm();
         this.formError = "";
         this.formErrors = {};
         this.caModalInst.show();
      },

      openEdit: function (a) {
         this.modalMode = "edit";
         this.editingAdvance = a;
         this.formError = "";
         this.formErrors = {};
         const d = a.requested_at ? new Date(a.requested_at).toISOString().slice(0, 16) : "";
         this.form = { id: a.id, amount: a.amount, status: a.status, notes: a.notes || "", requested_at: d };
         this.caModalInst.show();
      },

      submit: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         const req = this.modalMode === "create" ? axios.post(`/panel/employees/${this.employee.id}/cash-advances`, this.form) : axios.put(`/panel/employees/${this.employee.id}/cash-advances/${this.form.id}`, this.form);

         req.then((res) => {
            this.caModalInst.hide();
            this.editingAdvance = null;
            if (this.modalMode === "create") {
               this.advances.unshift(res.data);
            } else {
               const idx = this.advances.findIndex((a) => a.id === res.data.id);
               if (idx !== -1) this.advances.splice(idx, 1, res.data);
            }
            // Refresh stats
            this.fetchAdvances();
         })
            .catch((err) => {
               if (err.response?.status === 422) {
                  const errors = err.response.data.errors || {};
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
               } else {
                  this.formError = err.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => (this.submitting = false));
      },

      emptyForm: function () {
         const d = new Date();
         d.setSeconds(0, 0);
         return { amount: "", status: "requested", notes: "", requested_at: d.toISOString().slice(0, 16) };
      },

      canManageAdvance: function (status) {
         return ["requested", "approved"].includes(status);
      },

      canApproveAdvance: function (status) {
         return status === "requested";
      },

      canReleaseAdvance: function (status) {
         return status === "approved";
      },

      canCancelAdvance: function (status) {
         return ["requested", "approved"].includes(status);
      },

      isActioning: function (id, status) {
         return this.actioning === `${id}:${status}`;
      },
   },
   computed: {
      statCards: function () {
         return [
            { label: "Remaining", value: this.stats.remaining_amount, isMoney: true, icon: "bi-hourglass-split", iconBg: "bg-danger-soft", iconColor: "text-danger" },
            { label: "Released", value: this.stats.released_count, isMoney: false, icon: "bi-box-arrow-up-right", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Partially Paid", value: this.stats.partially_paid_count, isMoney: false, icon: "bi-dash-circle", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Paid", value: this.stats.paid_count, isMoney: false, icon: "bi-check-circle", iconBg: "bg-success-soft", iconColor: "text-success" },
         ];
      },
      statusOptions: function () {
         if (!this.editingAdvance) {
            return ["requested"];
         }

         return (
            {
               requested: ["requested", "approved", "cancelled"],
               approved: ["approved", "released", "cancelled"],
            }[this.editingAdvance.status] ?? []
         );
      },
   },
};
</script>
