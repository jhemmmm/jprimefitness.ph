<template>
   <div class="attendance-page">
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <!-- Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Attendance</h4>
            <p class="text-muted small mb-0">Track check-ins for members, walk-ins, and employees</p>
         </div>
         <button class="btn btn-danger px-3" @click="openAddModal">
            <i class="bi bi-person-check-fill me-1"></i>
            Log Attendance
         </button>
      </div>

      <!-- Stat Cards -->
      <div class="row g-3 mb-4">
         <div class="col-6 col-lg-3" v-for="stat in statCards" :key="stat.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="stat.iconBg">
                  <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ stat.label }}</div>
                  <div class="stat-card-value" v-if="loading">
                     <div class="skeleton-box sk-stat-val"></div>
                  </div>
                  <div class="stat-card-value" v-else>{{ stat.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <!-- Filters -->
      <div class="panel-card mb-4 p-3">
         <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                     <i class="bi bi-search text-muted search-icon"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" placeholder="Search name…" v-model="search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-2">
               <select class="form-select" v-model="selectedBranch" @change="fetchRecords(1)">
                  <option value="">All Branches</option>
                  <option v-for="b in branchesData" :key="b.id" :value="b.id">{{ b.name }}</option>
               </select>
            </div>
            <div class="col-6 col-md-2">
               <select class="form-select" v-model="selectedType" @change="fetchRecords(1)">
                  <option value="">All Types</option>
                  <option value="member">Members</option>
                  <option value="walk_in">Walk-ins</option>
                  <option value="employee">Employees</option>
               </select>
            </div>
            <div class="col-6 col-md-2">
               <input type="date" class="form-control" v-model="dateFrom" @change="fetchRecords(1)" title="From date" />
            </div>
            <div class="col-6 col-md-2">
               <input type="date" class="form-control" v-model="dateTo" @change="fetchRecords(1)" title="To date" />
            </div>
            <div class="col-12 col-md-auto" v-if="hasActiveFilters">
               <button class="btn btn-outline-secondary w-100" @click="clearFilters"><i class="bi bi-x me-1"></i>Clear</button>
            </div>
         </div>
      </div>

      <!-- ── List Card ────────────────────────────────────────────────────── -->
      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Attendance Log
               <span class="badge-count ms-1">{{ loading ? "—" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0"> Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} </span>
         </div>

         <!-- Loading skeleton -->
         <div v-if="loading">
            <div class="d-none d-md-block table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Checked In</th>
                        <th>Checked Out</th>
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
                        <td><div class="skeleton-box sk-plan-badge"></div></td>
                        <td><div class="skeleton-box sk-branch"></div></td>
                        <td><div class="skeleton-box sk-joined"></div></td>
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
         <div v-else-if="records.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-calendar-check empty-icon"></i>
            <p class="mt-2 mb-1">No attendance records found</p>
            <p class="small" v-if="hasActiveFilters">Try adjusting your filters</p>
            <button class="btn btn-danger btn-sm mt-1" @click="openAddModal" v-else>Log first check-in</button>
         </div>

         <!-- Data -->
         <div v-else>
            <!-- Desktop table -->
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Checked In</th>
                        <th>Checked Out</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="r in records" :key="r.id" :class="{ 'table-success-soft': !r.checked_out_at }">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="member-avatar" :class="attendeeAvatarClasses[r.attendee_type] || ''">
                                 {{ $filters.getNameInitials(r.name) }}
                              </div>
                              <div>
                                 <div class="member-name">{{ r.name }}</div>
                              </div>
                           </div>
                        </td>
                        <td>
                           <span :class="['m-badge', $filters.roleBadge(r.attendee_type)]">
                              {{ $filters.capitalize(r.attendee_type) }}
                           </span>
                        </td>
                        <td class="small">{{ r.branch ? r.branch.name : "—" }}</td>
                        <td class="text-muted small">{{ $filters.formatDateTime(r.checked_in_at) }}</td>
                        <td>
                           <span v-if="r.checked_out_at" class="text-muted small">{{ $filters.formatDateTime(r.checked_out_at) }}</span>
                           <button v-else class="btn btn-sm btn-outline-success py-0 px-2" title="Check out now" @click="doCheckout(r)"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
                        </td>
                        <td>
                           <div class="d-flex gap-1">
                              <button class="btn btn-sm btn-outline-secondary" title="Edit" @click="openEditModal(r)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger" title="Delete" @click="confirmDelete(r)">
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
               <div class="member-card" v-for="r in records" :key="'mc' + r.id">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="member-avatar" :class="attendeeAvatarClasses[r.attendee_type] || ''">
                           {{ $filters.getNameInitials(r.name) }}
                        </div>
                        <div>
                           <div class="member-card-name">{{ r.name }}</div>
                           <div class="member-card-sub">{{ r.branch ? r.branch.name : "—" }}</div>
                        </div>
                     </div>
                     <div class="dropdown">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li v-if="!r.checked_out_at">
                              <a class="dropdown-item text-success" href="#" @click.prevent="doCheckout(r)"> <i class="bi bi-box-arrow-right me-2"></i>Check out </a>
                           </li>
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditModal(r)"> <i class="bi bi-pencil me-2"></i>Edit </a>
                           </li>
                           <li><hr class="dropdown-divider" /></li>
                           <li>
                              <a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete(r)"> <i class="bi bi-trash me-2"></i>Delete </a>
                           </li>
                        </ul>
                     </div>
                  </div>
                  <div class="member-card-tags">
                     <span :class="['m-badge', $filters.roleBadge(r.attendee_type)]">{{ $filters.capitalize(r.attendee_type) }}</span>
                     <span :class="['m-badge', $filters.statusBadge('active')]" v-if="!r.checked_out_at">Currently In</span>
                  </div>
                  <div class="member-card-footer">
                     <span><i class="bi bi-box-arrow-in-right me-1"></i>{{ $filters.formatDateTime(r.checked_in_at) }}</span>
                     <span v-if="r.checked_out_at" class="text-muted small"> <i class="bi bi-box-arrow-right me-1"></i>{{ $filters.formatDateTime(r.checked_out_at) }} </span>
                     <button v-if="!r.checked_out_at" class="btn btn-sm btn-outline-success py-0 px-2 ms-auto" @click="doCheckout(r)"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
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

      <!-- ── Add / Edit Modal ──────────────────────────────────────────────── -->
      <div class="modal fade" id="attendanceFormModal" tabindex="-1" ref="attendanceFormModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">
                     {{ modalMode === "add" ? "Log Attendance" : "Edit Attendance" }}
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div v-if="formError" class="alert alert-danger py-2 small mb-3">{{ formError }}</div>

                  <div class="row g-3">
                     <!-- Type selector (add mode only) -->
                     <div class="col-12" v-if="modalMode === 'add'">
                        <label class="form-label form-label-sm">Attendee Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 flex-wrap">
                           <button v-for="t in attendeeTypes" :key="t.value" type="button" class="btn btn-sm" :class="form.attendee_type === t.value ? 'btn-danger' : 'btn-outline-secondary'" @click="setType(t.value)"><i class="bi me-1" :class="t.icon"></i>{{ t.label }}</button>
                        </div>
                        <div class="text-danger small mt-1" v-if="formErrors.attendee_type">
                           {{ formErrors.attendee_type }}
                        </div>
                     </div>

                     <!-- Member / Employee picker -->
                     <template v-if="form.attendee_type === 'member' || form.attendee_type === 'employee'">
                        <div class="col-12" v-if="modalMode === 'add'">
                           <label class="form-label form-label-sm">
                              {{ form.attendee_type === "member" ? "Member" : "Employee" }}
                              <span class="text-danger">*</span>
                           </label>
                           <AsyncSearchSelect v-model="form.user_id" :selected-label="form.name" :placeholder="personSelectPlaceholder" :search-placeholder="personSearchPlaceholder" :fetch-options="fetchPeopleOptions" :invalid="!!formErrors.user_id" @select-option="handlePersonSelected" />
                           <div class="invalid-feedback d-block" v-if="formErrors.user_id">{{ formErrors.user_id }}</div>
                        </div>
                        <div class="col-12 small text-muted" v-else>
                           <i class="bi bi-person-fill me-1"></i>
                           {{ form.name }}
                           <span :class="['m-badge', $filters.roleBadge(form.attendee_type), 'ms-2']">{{ $filters.capitalize(form.attendee_type) }}</span>
                        </div>
                     </template>

                     <!-- Walk-in name -->
                     <template v-if="form.attendee_type === 'walk_in'">
                        <div class="col-12">
                           <label class="form-label form-label-sm">Name <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" placeholder="e.g. Carlo Dela Cruz" />
                           <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name }}</div>
                        </div>
                     </template>

                     <!-- Branch -->
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Branch <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.branch_id" :class="{ 'is-invalid': formErrors.branch_id }">
                           <option value="">Select a branch...</option>
                           <option v-for="b in branchesData" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.branch_id">{{ formErrors.branch_id }}</div>
                     </div>

                     <!-- Checked In -->
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Checked In <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" v-model="form.checked_in_at" :class="{ 'is-invalid': formErrors.checked_in_at }" />
                        <div class="invalid-feedback" v-if="formErrors.checked_in_at">
                           {{ formErrors.checked_in_at }}
                        </div>
                     </div>

                     <!-- Checked Out (edit mode only) -->
                     <div class="col-md-6" v-if="modalMode === 'edit'">
                        <label class="form-label form-label-sm">Checked Out</label>
                        <input type="datetime-local" class="form-control" v-model="form.checked_out_at" />
                     </div>

                     <!-- Notes -->
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <input type="text" class="form-control" v-model="form.notes" placeholder="Optional" />
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="submitForm" :disabled="submitting">
                     <span v-if="submitting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     {{ modalMode === "add" ? "Log Check-in" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <!-- ── Delete Confirmation Modal ─────────────────────────────────── -->
      <div class="modal fade" id="attendanceDeleteModal" tabindex="-1" ref="attendanceDeleteModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Record</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <p class="mb-1">Are you sure you want to delete this attendance record?</p>
                  <p class="fw-semibold mb-0">
                     {{ deleteTarget.name }} &mdash;
                     {{ $filters.formatDateTime(deleteTarget.checked_in_at) }}
                  </p>
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
import AsyncSearchSelect from "./_vendor/AsyncSearchSelect.vue";

export default {
   components: {
      AsyncSearchSelect,
   },
   props: {
      branchesData: { type: Array, required: true },
   },
   data() {
      return {
         loading: true,
         submitting: false,
         deleting: false,
         pageError: "",
         records: [],
         pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
         stats: { today: 0, this_week: 0, this_month: 0, currently_in: 0 },
         search: "",
         selectedBranch: parseInt(localStorage.getItem("selectedBranch")) || "",
         selectedType: "",
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

   mounted() {
      this.formModal = new Modal(this.$refs.attendanceFormModal);
      this.deleteModal = new Modal(this.$refs.attendanceDeleteModal);
      this.fetchRecords();
   },

   methods: {
      emptyForm: function () {
         return {
            attendee_type: "member",
            user_id: "",
            name: "",
            branch_id: this.selectedBranch || "",
            checked_in_at: new Date().toISOString().slice(0, 16),
            checked_out_at: "",
            notes: "",
         };
      },

      fetchRecords: function (page = 1) {
         this.loading = true;
         this.pageError = "";
         axios
            .get("/panel/attendance/list", {
               params: {
                  page,
                  search: this.search || undefined,
                  branch: this.selectedBranch || undefined,
                  type: this.selectedType || undefined,
                  date_from: this.dateFrom || undefined,
                  date_to: this.dateTo || undefined,
               },
            })
            .then((res) => {
               const d = res.data;
               this.records = d.records.data;
               this.currentPage = d.records.current_page;
               this.pagination = {
                  currentPage: d.records.current_page,
                  lastPage: d.records.last_page,
                  total: d.records.total,
                  from: d.records.from || 0,
                  to: d.records.to || 0,
                  links: d.records.links,
               };
               this.stats = d.stats;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load attendance records."))
            .finally(() => (this.loading = false));
      },

      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchRecords(), 500);
      },

      clearFilters: function () {
         this.search = "";
         this.selectedBranch = "";
         this.selectedType = "";
         this.dateFrom = "";
         this.dateTo = "";
         this.fetchRecords();
      },

      goToPage: function (link) {
         if (!link.url) return;
         const page = parseInt(new URL(link.url).searchParams.get("page") || "1");
         this.fetchRecords(page);
      },

      setType: function (type) {
         this.form.attendee_type = type;
         this.form.user_id = "";
         this.form.name = "";
      },

      openAddModal: function () {
         this.modalMode = "add";
         this.form = this.emptyForm();
         this.pageError = "";
         this.formError = "";
         this.formErrors = {};
         this.formModal.show();
      },

      fetchPeopleOptions: function (search) {
         const params = {
            search,
            branch: this.form.branch_id || undefined,
         };

         if (this.form.attendee_type === "employee") {
            return axios.get("/panel/employees/list", { params }).then((res) => this.mapPeopleOptions(res.data || []));
         }

         return axios.get("/panel/members/list", { params }).then((res) => this.mapPeopleOptions(res.data.members?.data || []));
      },

      mapPeopleOptions: function (people) {
         return people.map((person) => ({
            id: person.id,
            name: person.name,
            meta: person.email || (person.branches || []).map((branch) => branch.name).join(", "),
         }));
      },

      handlePersonSelected: function (option) {
         this.form.user_id = option.id;
         this.form.name = option.name;
      },

      openEditModal: function (r) {
         this.modalMode = "edit";
         this.pageError = "";
         this.formError = "";
         this.formErrors = {};
         this.form = {
            id: r.id,
            attendee_type: r.attendee_type,
            user_id: r.user_id || "",
            name: r.name || "",
            branch_id: r.branch_id || "",
            checked_in_at: r.checked_in_at ? r.checked_in_at.slice(0, 16) : new Date().toISOString().slice(0, 16),
            checked_out_at: r.checked_out_at ? r.checked_out_at.slice(0, 16) : "",
            notes: r.notes || "",
         };
         this.formModal.show();
      },

      confirmDelete: function (r) {
         this.deleteTarget = r;
         this.deleteModal.show();
      },

      doCheckout: function (r) {
         this.pageError = "";
         axios
            .post(`/panel/attendance/${r.id}/checkout`)
            .then((res) => {
               const idx = this.records.findIndex((x) => x.id === r.id);
               if (idx !== -1) {
                  this.records.splice(idx, 1, res.data);
               }
               this.fetchRecords(this.currentPage);
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to check out attendance record."));
      },

      doDelete: function () {
         if (!this.deleteTarget) return;
         this.deleting = true;
         this.pageError = "";
         axios
            .delete(`/panel/attendance/${this.deleteTarget.id}`)
            .then(() => {
               this.deleteModal.hide();
               this.deleteTarget = null;
               this.fetchRecords(this.currentPage);
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to delete attendance record."))
            .finally(() => (this.deleting = false));
      },

      submitForm: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         const url = this.modalMode === "edit" ? `/panel/attendance/${this.form.id}` : "/panel/attendance";
         const method = this.modalMode === "edit" ? "put" : "post";

         const payload = { ...this.form };
         if (!payload.checked_out_at) delete payload.checked_out_at;
         if (payload.attendee_type !== "walk_in") delete payload.name;

         axios[method](url, payload)
            .then(() => {
               this.formModal.hide();
               this.fetchRecords(this.currentPage);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
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
      attendeeTypes() {
         return [
            { value: "member", label: "Member", icon: "bi-people-fill" },
            { value: "walk_in", label: "Walk-in", icon: "bi-person-plus-fill" },
            { value: "employee", label: "Employee", icon: "bi-person-workspace" },
         ];
      },

      attendeeAvatarClasses() {
         return {
            employee: "attendance-avatar--employee",
            walk_in: "attendance-avatar--walkin",
         };
      },

      hasActiveFilters() {
         return !!(this.search || this.selectedBranch || this.selectedType || this.dateFrom || this.dateTo);
      },

      personSelectPlaceholder() {
         return this.form.attendee_type === "employee" ? "Select an employee..." : "Select a member...";
      },

      personSearchPlaceholder() {
         return this.form.attendee_type === "employee" ? "Search employees..." : "Search members...";
      },

      statCards() {
         return [
            {
               label: "Today",
               value: this.stats.today,
               icon: "bi-person-check-fill",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
            },
            {
               label: "This Week",
               value: this.stats.this_week,
               icon: "bi-calendar-week",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
            },
            {
               label: "This Month",
               value: this.stats.this_month,
               icon: "bi-calendar-month",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
            },
            {
               label: "Currently In",
               value: this.stats.currently_in,
               icon: "bi-door-open-fill",
               iconBg: "bg-danger-soft",
               iconColor: "text-danger",
            },
         ];
      },
   },
};
</script>
