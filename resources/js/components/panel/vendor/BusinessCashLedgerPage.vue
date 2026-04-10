<template>
   <div class="cash-ledger-page">
      <div v-if="savedMessage" class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i>{{ savedMessage }}</div>
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Cash Ledger</h4>
            <p class="text-muted small mb-0">Track cash-in and cash-out entries for your business, including walk-ins, payroll payouts, cash advances, and manual adjustments.</p>
         </div>
         <button v-if="canManageEntries" class="btn btn-danger px-3" @click="openCreateModal">
            <i class="bi bi-plus-lg me-1"></i>
            Add Manual Entry
         </button>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-lg-3" v-for="card in summaryCards" :key="card.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="card.iconBg">
                  <i class="bi" :class="[card.icon, card.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ card.label }}</div>
                  <div class="stat-card-value" v-if="loading">
                     <div class="skeleton-box" style="width: 60px; height: 18px; border-radius: 5px"></div>
                  </div>
                  <div class="stat-card-value" v-else>{{ card.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <div class="panel-card mb-4 p-3">
         <div class="row g-2 align-items-center">
            <div class="col-12 col-lg-4">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                     <i class="bi bi-search text-muted search-icon"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" placeholder="Search title or description..." v-model="filters.search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
               <input type="date" class="form-control" v-model="filters.date_from" @change="fetchLedger(1)" title="From date" />
            </div>
            <div class="col-6 col-md-3 col-lg-2">
               <input type="date" class="form-control" v-model="filters.date_to" @change="fetchLedger(1)" title="To date" />
            </div>
            <div class="col-6 col-md-2 col-lg-1">
               <select class="form-select" v-model="filters.direction" @change="fetchLedger(1)">
                  <option value="">All Directions</option>
                  <option value="in">Cash In</option>
                  <option value="out">Cash Out</option>
               </select>
            </div>
            <div class="col-6 col-md-2 col-lg-1">
               <select class="form-select" v-model="filters.mode" @change="fetchLedger(1)">
                  <option value="">All Modes</option>
                  <option value="system">System</option>
                  <option value="manual">Manual</option>
               </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
               <select class="form-select" v-model="filters.entry_type" @change="fetchLedger(1)">
                  <option value="">All Types</option>
                  <option v-for="option in entryTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
               </select>
            </div>
         </div>
      </div>

      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Cash Ledger Entries
               <span class="badge-count ms-1">{{ loading ? "-" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</span>
         </div>

         <div v-if="loading">
            <div class="d-none d-md-block table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Date</th>
                        <th>Entry</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Mode</th>
                        <th>Logged By</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="index in 6" :key="'ledger-sk-' + index">
                        <td><div class="skeleton-box" style="width: 110px; height: 12px; border-radius: 4px"></div></td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 150px; height: 14px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 120px; height: 11px; border-radius: 4px"></div>
                        </td>
                        <td><div class="skeleton-box" style="width: 100px; height: 22px; border-radius: 999px"></div></td>
                        <td><div class="skeleton-box ms-auto" style="width: 80px; height: 12px; border-radius: 4px"></div></td>
                        <td><div class="skeleton-box" style="width: 70px; height: 22px; border-radius: 999px"></div></td>
                        <td><div class="skeleton-box" style="width: 90px; height: 12px; border-radius: 4px"></div></td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end">
                              <div class="skeleton-box" style="width: 32px; height: 32px; border-radius: 6px"></div>
                              <div class="skeleton-box" style="width: 32px; height: 32px; border-radius: 6px"></div>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="index in 4" :key="'ledger-mobile-sk-' + index">
                  <div class="member-card-top">
                     <div>
                        <div class="skeleton-box mb-1" style="width: 150px; height: 14px; border-radius: 4px"></div>
                        <div class="skeleton-box" style="width: 110px; height: 11px; border-radius: 4px"></div>
                     </div>
                     <div class="skeleton-box" style="width: 70px; height: 14px; border-radius: 4px"></div>
                  </div>
                  <div class="member-card-tags mt-2">
                     <div class="skeleton-box" style="width: 100px; height: 22px; border-radius: 999px"></div>
                     <div class="skeleton-box" style="width: 70px; height: 22px; border-radius: 999px"></div>
                  </div>
                  <div class="member-card-footer mt-3">
                     <div class="skeleton-box" style="width: 120px; height: 12px; border-radius: 4px"></div>
                     <div class="skeleton-box" style="width: 72px; height: 12px; border-radius: 4px"></div>
                  </div>
               </div>
            </div>
         </div>

         <div v-else-if="entries.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-cash-stack empty-icon"></i>
            <p class="mt-2 mb-1">No cash ledger entries found</p>
            <p class="small mb-3" v-if="hasActiveFilters">Try adjusting your filters</p>
            <button v-else-if="canManageEntries" class="btn btn-danger btn-sm" @click="openCreateModal">Add first entry</button>
         </div>

         <div v-else>
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Date</th>
                        <th>Entry</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Mode</th>
                        <th>Logged By</th>
                        <th class="col-actions" v-if="canManageEntries"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="entry in entries" :key="entry.id" :class="{ 'opacity-50': entry.is_deleted }">
                        <td class="text-muted small">{{ $filters.formatDateTime(entry.occurred_at) }}</td>
                        <td>
                           <div class="member-name">{{ entry.title }}</div>
                           <div class="text-muted small" v-if="entry.description">{{ entry.description }}</div>
                           <div class="text-danger small" v-if="entry.is_deleted">Deleted {{ $filters.formatDateTime(entry.deleted_at) }}</div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 flex-wrap">
                              <span class="m-badge m-badge--plan">{{ entry.entry_type_label }}</span>
                              <span class="m-badge m-badge--inactive" v-if="entry.is_deleted">Deleted</span>
                           </div>
                        </td>
                        <td class="text-end fw-semibold small" :class="entry.direction === 'in' ? 'text-success' : 'text-danger'">
                           {{ entry.direction === "in" ? "+" : "-" }}&#8369;{{ $filters.formatMoney(entry.amount) }}
                        </td>
                        <td>
                           <span :class="['m-badge', entry.is_system ? 'm-badge--inactive' : 'm-badge--active']">{{ entry.is_system ? "System" : "Manual" }}</span>
                        </td>
                        <td class="text-muted small">{{ entry.created_by_name || "-" }}</td>
                        <td v-if="canManageEntries">
                           <div class="d-flex gap-1 justify-content-end" v-if="!entry.is_system && !entry.is_deleted">
                              <button class="btn btn-sm btn-outline-secondary" @click="openEditModal(entry)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(entry)">
                                 <i class="bi bi-trash tbl-icon"></i>
                              </button>
                           </div>
                           <div v-else class="text-end text-muted small">{{ entry.is_deleted ? "Archived" : "Locked" }}</div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="entry in entries" :key="'mobile-' + entry.id" :class="{ 'opacity-50': entry.is_deleted }">
                  <div class="member-card-top">
                     <div>
                        <div class="member-card-name">{{ entry.title }}</div>
                        <div class="member-card-sub">{{ $filters.formatDateTime(entry.occurred_at) }}</div>
                     </div>
                     <span class="fw-semibold small" :class="entry.direction === 'in' ? 'text-success' : 'text-danger'">{{ entry.direction === "in" ? "+" : "-" }}&#8369;{{ $filters.formatMoney(entry.amount) }}</span>
                  </div>
                  <div class="member-card-tags">
                     <span class="m-badge m-badge--plan">{{ entry.entry_type_label }}</span>
                     <span :class="['m-badge', entry.is_system ? 'm-badge--inactive' : 'm-badge--active']">{{ entry.is_system ? "System" : "Manual" }}</span>
                     <span class="m-badge m-badge--inactive" v-if="entry.is_deleted">Deleted</span>
                  </div>
                  <div class="text-muted small mt-3" v-if="entry.description">{{ entry.description }}</div>
                  <div class="text-danger small mt-2" v-if="entry.is_deleted">Deleted {{ $filters.formatDateTime(entry.deleted_at) }}</div>
                  <div class="member-card-footer mt-3">
                     <span>{{ entry.created_by_name || "System" }}</span>
                     <div class="d-flex gap-2" v-if="canManageEntries && !entry.is_system && !entry.is_deleted">
                        <button class="btn btn-sm btn-outline-secondary" @click="openEditModal(entry)">Edit</button>
                        <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(entry)">Delete</button>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div v-if="!loading && pagination.lastPage > 1" class="d-flex justify-content-center py-3 border-top">
            <nav>
               <ul class="pagination pagination-sm mb-0">
                  <li v-for="link in pagination.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                     <a class="page-link" href="#" @click.prevent="goToPage(link)" v-html="link.label"></a>
                  </li>
               </ul>
            </nav>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="entryModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ modalMode === "create" ? "Add Manual Cash Entry" : "Edit Manual Cash Entry" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div class="alert alert-danger py-2 small mb-3" v-if="modalError">{{ modalError }}</div>

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
                  <button type="button" class="btn btn-danger px-4" :disabled="submitting" @click="submit">
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
                  <p class="mb-1">Are you sure you want to delete this cash ledger entry?</p>
                  <p class="fw-semibold mb-0">{{ deleteTarget.title }}</p>
                  <p class="text-muted small mb-0">{{ deleteTarget.entry_type_label }} · {{ deleteTarget.direction === "in" ? "+" : "-" }}&#8369;{{ $filters.formatMoney(deleteTarget.amount) }}</p>
                  <p class="text-muted small mt-2 mb-0">You can restore this manual entry later from Audit History.</p>
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
      profile: { type: Object, required: true },
   },

   emits: ["updated"],

   data: function () {
      return {
         loading: true,
         submitting: false,
         deleting: false,
         pageError: "",
         modalError: "",
         savedMessage: "",
         entries: [],
         pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
         summary: {
            balance: 0,
            cash_in_total: 0,
            cash_out_total: 0,
            month_net: 0,
         },
         filters: {
            date_from: "",
            date_to: "",
            direction: "",
            entry_type: "",
            mode: "",
            search: "",
         },
         currentPage: 1,
         modalMode: "create",
         form: this.emptyForm(),
         formErrors: {},
         deleteTarget: null,
         entryModalInst: null,
         deleteModalInst: null,
         searchTimeout: null,
      };
   },

   computed: {
      canManageEntries: function () {
         return this.is("super admin") || this.is("admin") || this.is("manager");
      },

      entryTypeOptions: function () {
         return [
            { value: "manual_adjustment", label: "Manual Adjustment" },
            { value: "inventory_sale", label: "Inventory Sale" },
            { value: "membership_sale", label: "Membership Sale" },
            { value: "pt_package_sale", label: "PT Package Sale" },
            { value: "walk_in_sale", label: "Walk-in Sale" },
            { value: "payroll_payout", label: "Payroll Payout" },
            { value: "cash_advance_release", label: "Cash Advance" },
         ];
      },

      hasActiveFilters: function () {
         return Object.values(this.filters).some((value) => String(value || "").trim() !== "");
      },

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
      "profile.id": {
         immediate: true,
         handler: function () {
            this.fetchLedger(1);
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
      window.clearTimeout(this.searchTimeout);
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

      filterParams: function (page = 1) {
         const params = Object.fromEntries(Object.entries(this.filters).filter(([, value]) => String(value || "").trim() !== ""));

         if (page > 1) {
            params.page = page;
         }

         return params;
      },

      applyLedgerPayload: function (payload) {
         const entries = payload.entries || {};

         this.entries = entries.data || [];
         this.currentPage = entries.current_page || 1;
         this.pagination = {
            currentPage: entries.current_page || 1,
            lastPage: entries.last_page || 1,
            total: entries.total || 0,
            from: entries.from || 0,
            to: entries.to || 0,
            links: entries.links || [],
         };
         this.summary = payload.summary || this.summary;
         this.$emit("updated", {
            cash_ledger_summary: this.summary,
         });
      },

      fetchLedger: function (page = 1, options = {}) {
         const { rethrow = false } = options;

         this.loading = true;
         this.pageError = "";

         return axios
            .get("/panel/business/cash-ledger/list", {
               params: this.filterParams(page),
            })
            .then((response) => {
               this.applyLedgerPayload(response.data);
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to load the cash ledger.";

               if (rethrow) {
                  throw error;
               }
            })
            .finally(() => {
               this.loading = false;
            });
      },

      onSearchInput: function () {
         window.clearTimeout(this.searchTimeout);
         this.searchTimeout = window.setTimeout(() => {
            this.fetchLedger(1);
         }, 250);
      },

      goToPage: function (link) {
         if (!link.url) {
            return;
         }

         const page = parseInt(new URL(link.url).searchParams.get("page") || "1", 10);
         this.fetchLedger(page);
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

         const request =
            this.modalMode === "create"
               ? axios.post("/panel/business/cash-ledger", payload)
               : axios.put(`/panel/business/cash-ledger/${this.form.id}`, payload);

         request
            .then(() => {
               this.savedMessage = this.modalMode === "create" ? "Manual cash entry added successfully." : "Manual cash entry updated successfully.";
               this.entryModalInst.hide();

               return this.fetchLedger(this.modalMode === "create" ? 1 : this.currentPage, { rethrow: true });
            })
            .then(() => {
               window.setTimeout(() => {
                  this.savedMessage = "";
               }, 3000);
            })
            .catch((error) => {
               if (error.response?.status === 422) {
                  const errors = error.response.data.errors || {};
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]));
                  this.modalError = error.response.data.message || "";

                  return;
               }

               this.modalError = error.response?.data?.message || "Something went wrong.";
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
            .delete(`/panel/business/cash-ledger/${this.deleteTarget.id}`)
            .then(() => {
               this.savedMessage = "Manual cash entry marked as deleted.";
               this.deleteModalInst.hide();
               this.deleteTarget = null;

               return this.fetchLedger(this.currentPage, { rethrow: true });
            })
            .then(() => {
               window.setTimeout(() => {
                  this.savedMessage = "";
               }, 3000);
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
