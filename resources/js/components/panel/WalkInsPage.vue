<template>
   <div class="walk-ins-page">
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <!-- ── Page Header ──────────────────────────────────────────────────── -->
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Walk-ins</h4>
            <p class="text-muted small mb-0">Log and track daily gym walk-in visitors</p>
         </div>
         <button class="btn btn-danger px-3" @click="openAddModal">
            <i class="bi bi-plus-lg me-1"></i>
            Add Walk-in
         </button>
      </div>

      <!-- ── Stat Cards ───────────────────────────────────────────────────── -->
      <div class="row g-3 mb-4">
         <div class="col-6 col-lg-3" v-for="stat in statCards" :key="stat.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="stat.iconBg">
                  <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ stat.label }}</div>
                  <div class="stat-card-value" v-if="statsLoading">
                     <div class="skeleton-box sk-stat-val"></div>
                  </div>
                  <div class="stat-card-value" v-else>{{ stat.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <!-- ── Filters ──────────────────────────────────────────────────────── -->
      <div class="panel-card mb-4 p-3">
         <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                     <i class="bi bi-search text-muted search-icon"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" placeholder="Search name or phone…" v-model="search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-2">
               <select class="form-select" v-model="selectedBranch" @change="fetchWalkIns(1)" title="Filter by branch">
                  <option value="">All Branches</option>
                  <option v-for="b in branchesData" :key="b.id" :value="b.id">{{ b.name }}</option>
               </select>
            </div>
            <div class="col-6 col-md-2">
               <input type="date" class="form-control" v-model="dateFrom" @change="fetchWalkIns(1)" title="From date" />
            </div>
            <div class="col-6 col-md-2">
               <input type="date" class="form-control" v-model="dateTo" @change="fetchWalkIns(1)" title="To date" />
            </div>
            <div class="col-6 col-md-2" v-if="hasActiveFilters">
               <button class="btn btn-outline-secondary w-100" @click="clearFilters"><i class="bi bi-x me-1"></i> Clear</button>
            </div>
         </div>
      </div>

      <!-- List Card -->
      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Walk-in Log
               <span class="badge-count ms-1">{{ loading ? "—" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0"> Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} </span>
         </div>

         <!-- Loading -->
         <div v-if="loading">
            <!-- Desktop skeleton -->
            <div class="d-none d-md-block table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Rate Plan</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Visited At</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="i in 8" :key="'sk' + i">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="skeleton-box sk-avatar"></div>
                              <div class="skeleton-box sk-name"></div>
                           </div>
                        </td>
                        <td><div class="skeleton-box sk-phone"></div></td>
                        <td><div class="skeleton-box sk-branch"></div></td>
                        <td><div class="skeleton-box sk-plan-badge"></div></td>
                        <td><div class="skeleton-box sk-amount"></div></td>
                        <td><div class="skeleton-box sk-plan-badge"></div></td>
                        <td><div class="skeleton-box sk-joined"></div></td>
                        <td>
                           <div class="d-flex gap-1">
                              <div class="skeleton-box sk-btn"></div>
                              <div class="skeleton-box sk-btn"></div>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <!-- Mobile skeleton cards -->
            <div class="d-md-none">
               <div class="member-card" v-for="i in 5" :key="'skm' + i">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="skeleton-box sk-avatar"></div>
                        <div>
                           <div class="skeleton-box sk-mc-name mb-1"></div>
                           <div class="skeleton-box sk-mc-sub"></div>
                        </div>
                     </div>
                     <div class="skeleton-box sk-btn"></div>
                  </div>
                  <div class="member-card-tags mt-2">
                     <div class="skeleton-box sk-mc-tag sk-mc-tag--sm"></div>
                     <div class="skeleton-box sk-mc-tag sk-mc-tag--md"></div>
                  </div>
                  <div class="member-card-footer">
                     <div class="skeleton-box sk-mc-date"></div>
                     <div class="skeleton-box sk-mc-num"></div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Empty -->
         <div v-else-if="walkIns.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-door-open empty-icon"></i>
            <p class="mt-2 mb-1">No walk-ins found</p>
            <p class="small" v-if="hasActiveFilters">Try adjusting your filters</p>
            <button class="btn btn-danger btn-sm mt-1" @click="openAddModal" v-else>Add first walk-in</button>
         </div>

         <!-- Data -->
         <div v-else>
            <!-- Desktop table -->
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Rate Plan</th>
                        <th>Amount</th>
                        <th>Visited At</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="w in walkIns" :key="w.id">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="member-avatar">{{ $filters.getNameInitials(w.name) }}</div>
                              <div>
                                 <div class="member-name">{{ w.name }}</div>
                              </div>
                           </div>
                        </td>
                        <td class="text-muted small">{{ w.phone || "—" }}</td>
                        <td class="small">{{ w.branch ? w.branch.name : "—" }}</td>
                        <td>
                           <span class="m-badge m-badge--plan" v-if="w.rate_plan">{{ w.rate_plan.name }}</span>
                           <span class="text-muted small" v-else>—</span>
                        </td>
                        <td class="fw-semibold small">₱{{ $filters.formatMoney(w.amount_paid) }}</td>
                        <td class="small">{{ $filters.capitalize(w.payment_method || "cash") }}</td>
                        <td class="text-muted small">{{ $filters.formatDateTime(w.visited_at) }}</td>
                        <td>
                           <div class="d-flex gap-1">
                              <button class="btn btn-sm btn-outline-secondary" title="Edit" @click="openEditModal(w)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger" title="Delete" @click="confirmDelete(w)">
                                 <i class="bi bi-trash tbl-icon"></i>
                              </button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <!-- Mobile cards -->
            <div class="d-md-none">
               <div class="member-card" v-for="w in walkIns" :key="'mc' + w.id">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="member-avatar">{{ $filters.getNameInitials(w.name) }}</div>
                        <div>
                           <div class="member-card-name">{{ w.name }}</div>
                           <div class="member-card-sub">{{ w.phone || w.branch?.name }}</div>
                        </div>
                     </div>
                     <div class="dropdown">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditModal(w)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                           <li><hr class="dropdown-divider" /></li>
                           <li>
                              <a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete(w)"><i class="bi bi-trash me-2"></i>Delete</a>
                           </li>
                        </ul>
                     </div>
                  </div>
                  <div class="member-card-tags">
                      <span class="m-badge m-badge--active">₱{{ $filters.formatMoney(w.amount_paid) }}</span>
                      <span class="m-badge m-badge--plan">{{ $filters.capitalize(w.payment_method || "cash") }}</span>
                      <span class="m-badge m-badge--plan" v-if="w.rate_plan">{{ w.rate_plan.name }}</span>
                  </div>
                  <div class="member-card-footer">
                     <span><i class="bi bi-calendar3 me-1"></i>{{ $filters.formatDateTime(w.visited_at) }}</span>
                     <span class="member-card-num">#{{ w.id }}</span>
                  </div>
               </div>
            </div>
         </div>

         <!-- Pagination -->
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

      <!-- ── Add / Edit Walk-in Modal ──────────────────────────────────── -->
      <div class="modal fade" id="walkInFormModal" tabindex="-1" ref="walkInFormModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">
                     {{ modalMode === "add" ? "Add Walk-in" : "Edit Walk-in" }}
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div v-if="formError" class="alert alert-danger py-2 small mb-3">{{ formError }}</div>
                  <div class="row g-3">
                     <div class="col-md-12">
                        <label class="form-label form-label-sm">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" placeholder="e.g. Carlo" />
                        <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Phone</label>
                        <input type="text" class="form-control" v-model="form.phone" placeholder="09XX XXX XXXX" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Branch <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.branch_id" :class="{ 'is-invalid': formErrors.branch_id }">
                           <option disabled value="">Select a branch...</option>
                           <option v-for="b in branchesData" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.branch_id">{{ formErrors.branch_id }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Rate Plan</label>
                        <select class="form-select" v-model="form.rate_plan_id">
                           <option disabled value="">Select a rate plan...</option>
                           <option v-for="p in ratePlansData" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Amount Paid (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" v-model="form.amount_paid" min="0" step="0.01" :class="{ 'is-invalid': formErrors.amount_paid }" placeholder="0.00" />
                        <div class="invalid-feedback" v-if="formErrors.amount_paid">{{ formErrors.amount_paid }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Payment Method</label>
                        <select class="form-select" v-model="form.payment_method" :class="{ 'is-invalid': formErrors.payment_method }">
                           <option value="cash">Cash</option>
                           <option value="gcash">GCash</option>
                           <option value="card">Card</option>
                           <option value="bank_transfer">Bank Transfer</option>
                           <option value="online_payment">Online Payment</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.payment_method">{{ formErrors.payment_method }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Visit Date &amp; Time</label>
                        <input type="datetime-local" class="form-control" v-model="form.visited_at" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Notes</label>
                        <input type="text" class="form-control" v-model="form.notes" placeholder="Optional" />
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="submitForm" :disabled="submitting">
                     <span v-if="submitting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     {{ modalMode === "add" ? "Add Walk-in" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <!-- ── Delete Confirmation Modal ─────────────────────────────────── -->
      <div class="modal fade" id="walkInDeleteModal" tabindex="-1" ref="walkInDeleteModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Walk-in</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <p class="mb-1">Are you sure you want to delete this walk-in record?</p>
                  <p class="fw-semibold mb-0">{{ deleteTarget.name }} &mdash; {{ $filters.formatDateTime(deleteTarget.visited_at) }}</p>
                  <p class="text-danger small mt-2 mb-0">This action cannot be undone.</p>
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
      branchesData: { type: Array, default: () => [] },
      ratePlansData: { type: Array, default: () => [] },
   },

   data: function () {
      return {
         loading: true,
         submitting: false,
         deleting: false,
         pageError: "",
         walkIns: [],
         pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
         stats: { today: 0, this_week: 0, this_month: 0, revenue_today: 0 },
         search: "",
         selectedBranch: parseInt(localStorage.getItem("selectedBranch")) || "",
         dateFrom: "",
         dateTo: "",
         currentPage: 1,
         searchTimer: null,
         modalMode: "add",
         formError: "",
         formErrors: {},
         form: this.emptyForm(),
         formModal: null,
         deleteModal: null,
         deleteTarget: null,
      };
   },

   mounted: function () {
      this.formModal = new Modal(this.$refs.walkInFormModal);
      this.deleteModal = new Modal(this.$refs.walkInDeleteModal);
      this.fetchWalkIns();
   },

   methods: {
      emptyForm: function () {
         return {
            name: "",
            phone: "",
            branch_id: this.selectedBranch || "",
            rate_plan_id: "",
            amount_paid: "",
            payment_method: "cash",
            visited_at: new Date().toISOString().slice(0, 16),
            notes: "",
         };
      },

      fetchWalkIns: function (page = 1) {
         this.loading = true;
         this.pageError = "";
         axios
            .get("/panel/walk-ins/list", {
               params: {
                  search: this.search || undefined,
                  branch: this.selectedBranch || undefined,
                  date_from: this.dateFrom || undefined,
                  date_to: this.dateTo || undefined,
                  page: page,
               },
            })
            .then((res) => {
               this.walkIns = res.data.walkIns.data;
               this.currentPage = res.data.walkIns.current_page;
               this.pagination = {
                  currentPage: res.data.walkIns.current_page,
                  lastPage: res.data.walkIns.last_page,
                  total: res.data.walkIns.total,
                  from: res.data.walkIns.from || 0,
                  to: res.data.walkIns.to || 0,
                  links: res.data.walkIns.links,
               };
               this.stats = res.data.stats;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load walk-ins."))
            .finally(() => (this.loading = false));
      },

      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchWalkIns(), 500);
      },

      clearFilters: function () {
         this.search = "";
         this.selectedBranch = parseInt(localStorage.getItem("selectedBranch"), 10) || "";
         this.dateFrom = "";
         this.dateTo = "";
         this.fetchWalkIns();
      },

      goToPage: function (link) {
         if (!link.url) return;
         const page = parseInt(new URL(link.url).searchParams.get("page") || "1");
         this.fetchWalkIns(page);
      },

      openAddModal: function () {
         this.modalMode = "add";
         this.form = this.emptyForm();
         this.pageError = "";
         this.formError = "";
         this.formErrors = {};
         this.formModal.show();
      },

      openEditModal: function (w) {
         this.modalMode = "edit";
         this.pageError = "";
         this.formError = "";
         this.formErrors = {};
         this.form = {
            id: w.id,
            name: w.name || "",
            phone: w.phone || "",
            branch_id: w.branch_id || "",
            rate_plan_id: w.rate_plan_id || "",
            amount_paid: w.amount_paid || "",
            payment_method: w.payment_method || "cash",
            visited_at: w.visited_at ? w.visited_at.slice(0, 16) : new Date().toISOString().slice(0, 16),
            notes: w.notes || "",
         };
         this.formModal.show();
      },

      confirmDelete: function (w) {
         this.deleteTarget = w;
         this.deleteModal.show();
      },

      doDelete: function () {
         if (!this.deleteTarget) return;
         this.deleting = true;
         this.pageError = "";
         axios
            .delete(`/panel/walk-ins/${this.deleteTarget.id}`)
            .then(() => {
               this.deleteModal.hide();
               this.deleteTarget = null;
               this.fetchWalkIns(this.currentPage);
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to delete walk-in record."))
            .finally(() => (this.deleting = false));
      },

      submitForm: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         const url = this.modalMode === "edit" ? `/panel/walk-ins/${this.form.id}` : "/panel/walk-ins";
         const method = this.modalMode === "edit" ? "put" : "post";

         axios[method](url, this.form)
            .then(() => {
               this.formModal.hide();
               this.fetchWalkIns(this.currentPage);
            })
            .catch((err) => {
               if (err.response && err.response.status === 422) {
                  const errors = err.response.data.errors || {};
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
               } else {
                  this.formError = "Something went wrong. Please try again.";
               }
            })
            .finally(() => (this.submitting = false));
      },
   },

   computed: {
      hasActiveFilters: function () {
         return !!(this.search || this.selectedBranch || this.dateFrom || this.dateTo);
      },
      statCards: function () {
         return [
            { label: "Today's Visits", value: this.stats.today, icon: "bi-person-walking", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "This Week", value: this.stats.this_week, icon: "bi-calendar-week", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "This Month", value: this.stats.this_month, icon: "bi-calendar-month", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Today's Revenue", value: "₱" + this.$filters.formatMoney(this.stats.revenue_today), icon: "bi-cash-coin", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },
};
</script>
