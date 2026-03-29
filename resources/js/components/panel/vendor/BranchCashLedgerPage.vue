<template>
   <div class="p-4">
      <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
         <div>
            <h6 class="fw-bold mb-1">Branch Cash Ledger</h6>
            <div class="text-muted small">Track branch cash-in and cash-out entries, including walk-ins, payroll payouts, cash advances, and manual adjustments.</div>
         </div>
         <button v-if="is('super admin') || is('admin') || is('manager')" class="btn btn-danger" @click="openCreateModal">
            <i class="bi bi-plus-circle me-1"></i>Add Manual Entry
         </button>
      </div>

      <div class="alert alert-success py-2 small" v-if="savedMessage"><i class="bi bi-check-circle me-1"></i>{{ savedMessage }}</div>
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-xl-3" v-for="card in summaryCards" :key="card.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="card.iconBg">
                  <i class="bi" :class="[card.icon, card.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ card.label }}</div>
                  <div class="stat-card-value small">{{ card.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <div v-if="loading" class="text-center py-5 text-muted">
         <div class="spinner-border text-danger mb-2"></div>
         <div>Loading branch cash ledger...</div>
      </div>

      <template v-else>
         <div v-if="entries.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-25"></i>
            <div>No cash ledger entries yet.</div>
            <div class="small">Add an opening balance or manual adjustment to start tracking cash on hand.</div>
         </div>

         <div v-else>
            <div class="table-responsive d-none d-md-block">
                  <table class="table align-middle">
                  <thead>
                     <tr>
                        <th>Date</th>
                        <th>Entry</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Mode</th>
                        <th>Logged By</th>
                        <th class="text-end" v-if="is('super admin') || is('admin') || is('manager')">Actions</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="entry in entries" :key="entry.id" :class="{ 'opacity-50': entry.is_deleted }">
                        <td class="small text-muted">{{ $filters.formatDateTime(entry.occurred_at) }}</td>
                        <td>
                           <div class="fw-semibold">{{ entry.title }}</div>
                           <div class="small text-muted" v-if="entry.description">{{ entry.description }}</div>
                           <div class="small text-danger" v-if="entry.is_deleted">Deleted {{ $filters.formatDateTime(entry.deleted_at) }}</div>
                        </td>
                        <td>
                           <span class="m-badge m-badge--plan">{{ entry.entry_type_label }}</span>
                           <span class="m-badge m-badge--inactive ms-1" v-if="entry.is_deleted">Deleted</span>
                        </td>
                        <td class="text-end fw-semibold" :class="entry.direction === 'in' ? 'text-success' : 'text-danger'">
                           {{ entry.direction === "in" ? "+" : "-" }}₱{{ $filters.formatMoney(entry.amount) }}
                        </td>
                        <td>
                           <span :class="['m-badge', entry.is_system ? 'm-badge--inactive' : 'm-badge--active']">{{ entry.is_system ? "System" : "Manual" }}</span>
                        </td>
                        <td class="small text-muted">{{ entry.created_by_name || "—" }}</td>
                        <td class="text-end" v-if="is('super admin') || is('admin') || is('manager')">
                           <div v-if="!entry.is_system && !entry.is_deleted" class="btn-group btn-group-sm">
                              <button class="btn btn-outline-secondary" @click="openEditModal(entry)"><i class="bi bi-pencil"></i></button>
                              <button class="btn btn-outline-danger" @click="confirmDelete(entry)"><i class="bi bi-trash"></i></button>
                           </div>
                           <span v-else class="text-muted small">{{ entry.is_deleted ? "Archived" : "Locked" }}</span>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card mb-3" v-for="entry in entries" :key="`mobile-${entry.id}`" :class="{ 'opacity-50': entry.is_deleted }">
                  <div class="d-flex justify-content-between align-items-start gap-3">
                     <div class="min-w-0">
                        <div class="fw-semibold">{{ entry.title }}</div>
                        <div class="small text-muted">{{ $filters.formatDateTime(entry.occurred_at) }}</div>
                        <div class="small text-danger" v-if="entry.is_deleted">Deleted {{ $filters.formatDateTime(entry.deleted_at) }}</div>
                     </div>
                     <span class="fw-semibold" :class="entry.direction === 'in' ? 'text-success' : 'text-danger'">
                        {{ entry.direction === "in" ? "+" : "-" }}₱{{ $filters.formatMoney(entry.amount) }}
                     </span>
                  </div>

                  <div class="small text-muted mt-2" v-if="entry.description">{{ entry.description }}</div>

                  <div class="d-flex flex-wrap gap-2 mt-3 small">
                     <span class="m-badge m-badge--plan">{{ entry.entry_type_label }}</span>
                     <span class="m-badge m-badge--inactive" v-if="entry.is_deleted">Deleted</span>
                     <span :class="['m-badge', entry.is_system ? 'm-badge--inactive' : 'm-badge--active']">{{ entry.is_system ? "System" : "Manual" }}</span>
                     <span class="text-muted" v-if="entry.created_by_name">By {{ entry.created_by_name }}</span>
                  </div>

                  <div class="d-flex justify-content-end gap-2 mt-3" v-if="(is('super admin') || is('admin') || is('manager')) && !entry.is_system && !entry.is_deleted">
                     <button class="btn btn-outline-secondary btn-sm" @click="openEditModal(entry)">Edit</button>
                     <button class="btn btn-outline-danger btn-sm" @click="confirmDelete(entry)">Delete</button>
                  </div>
               </div>
            </div>
         </div>
      </template>

      <div class="modal fade" tabindex="-1" ref="entryModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title">{{ modalMode === "create" ? "Add Manual Cash Entry" : "Edit Manual Cash Entry" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="alert alert-danger py-2 small" v-if="modalError">{{ modalError }}</div>

                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Direction</label>
                        <select class="form-select" v-model="form.direction" :class="{ 'is-invalid': formErrors.direction }">
                           <option value="in">Cash In</option>
                           <option value="out">Cash Out</option>
                        </select>
                        <div class="invalid-feedback">{{ formErrors.direction }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm fw-semibold">Amount</label>
                        <input type="number" class="form-control" v-model="form.amount" min="0.01" step="0.01" :class="{ 'is-invalid': formErrors.amount }" />
                        <div class="invalid-feedback">{{ formErrors.amount }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Title</label>
                        <input type="text" class="form-control" v-model="form.title" maxlength="120" :class="{ 'is-invalid': formErrors.title }" placeholder="Opening balance, petty cash top-up, bank deposit, utilities, etc." />
                        <div class="invalid-feedback">{{ formErrors.title }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Occurred At</label>
                        <input type="datetime-local" class="form-control" v-model="form.occurred_at" :class="{ 'is-invalid': formErrors.occurred_at }" />
                        <div class="invalid-feedback">{{ formErrors.occurred_at }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm fw-semibold">Description</label>
                        <textarea class="form-control" rows="3" v-model="form.description" :class="{ 'is-invalid': formErrors.description }" placeholder="Optional reference or explanation"></textarea>
                        <div class="invalid-feedback">{{ formErrors.description }}</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="button" class="btn btn-danger" :disabled="submitting" @click="submit">
                     <span class="spinner-border spinner-border-sm me-1" v-if="submitting"></span>
                     {{ modalMode === "create" ? "Save Entry" : "Update Entry" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="deleteModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Cash Entry</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <p class="mb-1">Are you sure you want to delete this branch cash ledger entry?</p>
                  <p class="fw-semibold mb-0">{{ deleteTarget.title }}</p>
                  <p class="text-muted small mb-0">{{ deleteTarget.entry_type_label }} · {{ deleteTarget.direction === "in" ? "+" : "-" }}₱{{ $filters.formatMoney(deleteTarget.amount) }}</p>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="doDelete" :disabled="deleting">
                     <span v-if="deleting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     Delete
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
      branch: { type: Object, required: true },
   },

   emits: ["updated"],

   data: function () {
      return {
         loading: false,
         submitting: false,
         pageError: "",
         modalError: "",
         savedMessage: "",
         entries: [],
         deleteTarget: null,
         deleting: false,
         summary: {
            balance: 0,
            cash_in_total: 0,
            cash_out_total: 0,
            month_net: 0,
         },
         modalMode: "create",
         form: this.emptyForm(),
         formErrors: {},
         entryModalInst: null,
         deleteModalInst: null,
      };
   },

   computed: {
      summaryCards: function () {
         return [
            { label: "Current Balance", value: `₱${this.$filters.formatMoney(this.summary.balance || 0)}`, icon: "bi-wallet2", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Cash In", value: `₱${this.$filters.formatMoney(this.summary.cash_in_total || 0)}`, icon: "bi-arrow-down-circle", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Cash Out", value: `₱${this.$filters.formatMoney(this.summary.cash_out_total || 0)}`, icon: "bi-arrow-up-circle", iconBg: "bg-danger-soft", iconColor: "text-danger" },
            { label: "This Month Net", value: `₱${this.$filters.formatMoney(this.summary.month_net || 0)}`, icon: "bi-graph-up-arrow", iconBg: "bg-warning-soft", iconColor: "text-warning" },
         ];
      },
   },

   watch: {
      "branch.id": {
         immediate: true,
         handler: function () {
            this.fetchLedger();
         },
      },
   },

   mounted: function () {
      this.entryModalInst = new Modal(this.$refs.entryModal);
      this.deleteModalInst = new Modal(this.$refs.deleteModal);
   },

   beforeUnmount: function () {
      this.entryModalInst?.dispose();
      this.deleteModalInst?.dispose();
   },

   methods: {
      emptyForm: function () {
         return {
            id: null,
            direction: "in",
            amount: "",
            title: "",
            description: "",
            occurred_at: this.toInputDateTime(new Date()),
         };
      },

      toInputDateTime: function (value) {
         const date = value instanceof Date ? value : new Date(value);

         if (Number.isNaN(date.getTime())) {
            return "";
         }

         const year = date.getFullYear();
         const month = String(date.getMonth() + 1).padStart(2, "0");
         const day = String(date.getDate()).padStart(2, "0");
         const hours = String(date.getHours()).padStart(2, "0");
         const minutes = String(date.getMinutes()).padStart(2, "0");

         return `${year}-${month}-${day}T${hours}:${minutes}`;
      },

      fetchLedger: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get(`/panel/branches/${this.branch.id}/cash-ledger`)
            .then((response) => {
               this.entries = response.data.entries || [];
               this.summary = response.data.summary || this.summary;
               this.$emit("updated", {
                  cash_ledger_summary: this.summary,
               });
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to load branch cash ledger.";
            })
            .finally(() => {
               this.loading = false;
            });
      },

      openCreateModal: function () {
         this.modalMode = "create";
         this.form = this.emptyForm();
         this.formErrors = {};
         this.modalError = "";
         this.entryModalInst.show();
      },

      confirmDelete: function (entry) {
         this.deleteTarget = entry;
         this.deleteModalInst.show();
      },

      openEditModal: function (entry) {
         this.modalMode = "edit";
         this.form = {
            id: entry.id,
            direction: entry.direction,
            amount: entry.amount,
            title: entry.title,
            description: entry.description || "",
            occurred_at: this.toInputDateTime(entry.occurred_at),
         };
         this.formErrors = {};
         this.modalError = "";
         this.entryModalInst.show();
      },

      submit: function () {
         this.submitting = true;
         this.formErrors = {};
         this.modalError = "";

         const payload = {
            direction: this.form.direction,
            amount: this.form.amount,
            title: this.form.title,
            description: this.form.description || null,
            occurred_at: this.form.occurred_at,
         };

         const request = this.modalMode === "create"
            ? axios.post(`/panel/branches/${this.branch.id}/cash-ledger`, payload)
            : axios.put(`/panel/branches/${this.branch.id}/cash-ledger/${this.form.id}`, payload);

         request
            .then(() => {
               this.savedMessage = this.modalMode === "create"
                  ? "Manual cash entry added successfully."
                  : "Manual cash entry updated successfully.";
               this.entryModalInst.hide();

               return axios.get(`/panel/branches/${this.branch.id}/cash-ledger`);
            })
            .then((response) => {
               this.entries = response.data.entries || [];
               this.summary = response.data.summary || this.summary;
               this.$emit("updated", {
                  cash_ledger_summary: this.summary,
               });
               setTimeout(() => (this.savedMessage = ""), 3000);
            })
            .catch((error) => {
               if (error.response?.status === 422) {
                  const errors = error.response.data.errors || {};
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]));
                  this.modalError = error.response.data.message || "";
               } else {
                  this.modalError = error.response?.data?.message || "Something went wrong.";
               }
            })
            .finally(() => {
               this.submitting = false;
            });
      },

      doDelete: function () {
         if (!this.deleteTarget) {
            return;
         }

         this.deleting = true;
         this.pageError = "";

         axios
            .delete(`/panel/branches/${this.branch.id}/cash-ledger/${this.deleteTarget.id}`)
            .then(() => {
               this.savedMessage = "Manual cash entry marked as deleted.";
               this.deleteModalInst.hide();
               this.deleteTarget = null;

               return axios.get(`/panel/branches/${this.branch.id}/cash-ledger`);
            })
            .then((response) => {
               this.entries = response.data.entries || [];
               this.summary = response.data.summary || this.summary;
               this.$emit("updated", {
                  cash_ledger_summary: this.summary,
               });
               setTimeout(() => (this.savedMessage = ""), 3000);
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to delete cash ledger entry.";
            })
            .finally(() => {
               this.deleting = false;
            });
      },
   },
};
</script>
