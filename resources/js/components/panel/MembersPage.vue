<template>
   <div class="members-page">
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <!-- Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Members</h4>
            <p class="text-muted small mb-0">All registered gym members across all branches</p>
         </div>
         <button class="btn btn-danger px-3" @click="openAddModal">
            <i class="bi bi-person-plus-fill me-1"></i>
            Add Member
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
                  <input type="text" class="form-control border-start-0" placeholder="Search name or email…" v-model="search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-2">
               <select class="form-select" v-model="selectedBranch" @change="fetchMembers">
                  <option value="">All Branches</option>
                  <option v-for="b in branchesData" :key="b.id" :value="b.id">{{ b.name }}</option>
               </select>
            </div>
            <div class="col-6 col-md-2">
               <select class="form-select" v-model="selectedStatus" @change="fetchMembers">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="suspended">Suspended</option>
               </select>
            </div>
            <div class="col-6 col-md-2">
               <select class="form-select" v-model="selectedPlan" @change="fetchMembers">
                  <option value="">All Plans</option>
                  <option v-for="p in ratePlansData" :key="p.id" :value="p.id">{{ p.name }}</option>
               </select>
            </div>
            <div class="col-6 col-md-2" v-if="hasActiveFilters">
               <button class="btn btn-outline-secondary w-100" @click="clearFilters"><i class="bi bi-x me-1"></i> Clear</button>
            </div>
         </div>
      </div>

      <!-- Table Card -->
      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Member List
               <span class="badge-count ms-1">{{ loading ? "—" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0"> Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} </span>
         </div>

         <!-- Loading state -->
         <div v-if="loading">
            <!-- Desktop skeleton table -->
            <div class="d-none d-md-block table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th class="col-num">#</th>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="i in 8" :key="'sk' + i">
                        <td><div class="skeleton-box sk-num"></div></td>
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="skeleton-box sk-avatar"></div>
                              <div>
                                 <div class="skeleton-box sk-name mb-1"></div>
                                 <div class="skeleton-box sk-email"></div>
                              </div>
                           </div>
                        </td>
                        <td><div class="skeleton-box sk-phone"></div></td>
                        <td><div class="skeleton-box sk-branch"></div></td>
                        <td>
                           <div class="skeleton-box sk-plan-name mb-1"></div>
                           <div class="skeleton-box sk-plan-badge"></div>
                        </td>
                        <td><div class="skeleton-box sk-status"></div></td>
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
                     <div class="skeleton-box sk-mc-tag sk-mc-tag--lg"></div>
                  </div>
                  <div class="member-card-footer">
                     <div class="skeleton-box sk-mc-date"></div>
                     <div class="skeleton-box sk-mc-num"></div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Empty state -->
         <div v-else-if="members.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-people empty-icon"></i>
            <p class="mt-2 mb-1">No members found</p>
            <p class="small" v-if="hasActiveFilters">Try adjusting your filters</p>
         </div>

         <!-- Data -->
         <div v-else>
            <!-- Desktop table -->
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th class="col-num">#</th>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="(member, i) in members" :key="member.id">
                        <td class="text-muted small">{{ pagination.from + i }}</td>
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="member-avatar">{{ $filters.getNameInitials(member.name) }}</div>
                              <div>
                                 <div class="member-name">{{ member.name }}</div>
                                 <div class="member-email">{{ member.email }}</div>
                              </div>
                           </div>
                        </td>
                        <td class="text-muted small">{{ member.phone || "—" }}</td>
                        <td class="small">{{ member.branches && member.branches.length ? member.branches.map((b) => b.name).join(", ") : "—" }}</td>
                        <td>
                           <template v-if="getActivePlan(member)">
                              <div class="plan-name mb-1">{{ getActivePlan(member).name }}</div>
                              <span class="m-badge" :class="getPlanStatusClass(getActivePlan(member).pivot.status)">
                                 {{ $filters.capitalize(getActivePlan(member).pivot.status) }}
                              </span>
                           </template>
                           <span v-else class="text-muted small">—</span>
                        </td>
                        <td>
                           <span :class="['m-badge', $filters.statusBadge(member.status)]">{{ $filters.capitalize(member.status) }}</span>
                        </td>
                        <td class="text-muted small">{{ $filters.formatDate(member.created_at) }}</td>
                        <td>
                           <div class="d-flex gap-1">
                              <button class="btn btn-sm btn-outline-secondary" title="View" @click="openViewModal(member)">
                                 <i class="bi bi-eye tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-secondary" title="Edit" @click="openEditModal(member)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <!-- Mobile cards -->
            <div class="d-md-none">
               <div class="member-card" v-for="member in members" :key="'mc' + member.id">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="member-avatar">{{ $filters.getNameInitials(member.name) }}</div>
                        <div>
                           <div class="member-card-name">{{ member.name }}</div>
                           <div class="member-card-sub">{{ member.phone || member.email }}</div>
                        </div>
                     </div>
                     <div class="dropdown">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openViewModal(member)"><i class="bi bi-eye me-2"></i>View</a>
                           </li>
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditModal(member)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                        </ul>
                     </div>
                  </div>
                  <div class="member-card-tags">
                     <span :class="['m-badge', $filters.statusBadge(member.status)]">{{ $filters.capitalize(member.status) }}</span>
                     <template v-if="getActivePlan(member)">
                        <span class="text-capitalize m-badge m-badge--plan">{{ getActivePlan(member).name }}</span>
                        <span class="m-badge" :class="getPlanStatusClass(getActivePlan(member).pivot.status)">{{ $filters.capitalize(getActivePlan(member).pivot.status) }}</span>
                     </template>
                  </div>
                  <div class="member-card-footer">
                     <span><i class="bi bi-calendar3 me-1"></i>{{ $filters.formatDate(member.created_at) }}</span>
                     <span class="member-card-num">#{{ member.id }}</span>
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

      <!-- Add / Edit Member Modal -->
      <div class="modal fade" id="memberFormModal" tabindex="-1" ref="memberFormModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">
                     {{ modalMode === "add" ? "Add New Member" : "Edit Member" }}
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div v-if="formError" class="alert alert-danger py-2 small mb-3">{{ formError }}</div>
                  <!-- Personal Info -->
                  <div class="form-section-header">
                     <i class="bi bi-person-fill"></i>
                     Personal Information
                  </div>
                  <div class="row g-3 mb-4">
                     <div class="col-md-12">
                        <label class="form-label form-label-sm">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" placeholder="e.g. Juan dela Cruz" />
                        <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" v-model="form.email" :class="{ 'is-invalid': formErrors.email }" placeholder="member@email.com" />
                        <div class="invalid-feedback" v-if="formErrors.email">{{ formErrors.email }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Phone</label>
                        <input type="text" class="form-control" v-model="form.phone" placeholder="09XX XXX XXXX" />
                     </div>
                     <div class="col-md-6" v-if="modalMode === 'add'">
                        <label class="form-label form-label-sm">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" v-model="form.password" :class="{ 'is-invalid': formErrors.password }" placeholder="Min. 8 characters" />
                        <div class="invalid-feedback" v-if="formErrors.password">{{ formErrors.password }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Branches</label>
                        <MultiSelect v-model="form.branch_ids" :options="branchesData" placeholder="Select branches..." searchable />
                     </div>
                     <div :class="modalMode === 'add' ? 'col-md-12' : 'col-md-6'">
                        <label class="form-label form-label-sm">Status</label>
                        <select class="form-select flex-grow-1" v-model="form.status">
                           <option value="active">Active</option>
                           <option value="inactive">Inactive</option>
                           <option value="suspended">Suspended</option>
                        </select>
                     </div>
                  </div>
                  <!-- Profile -->
                  <div class="form-section-header">
                     <i class="bi bi-card-list"></i>
                     Profile Details
                  </div>
                  <div class="row g-3 mb-4">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Date of Birth</label>
                        <input type="date" class="form-control" v-model="form.date_of_birth" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Gender</label>
                        <select class="form-select" v-model="form.gender">
                           <option value="">Select a gender...</option>
                           <option value="male">Male</option>
                           <option value="female">Female</option>
                           <option value="other">Other</option>
                        </select>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Emergency Contact Name</label>
                        <input type="text" class="form-control" v-model="form.emergency_contact_name" placeholder="Full name" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Emergency Contact Phone</label>
                        <input type="text" class="form-control" v-model="form.emergency_contact_phone" placeholder="Phone number" />
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <textarea class="form-control" rows="2" v-model="form.notes" placeholder="Optional notes about this member…"></textarea>
                     </div>
                  </div>

                  <!-- Membership Plan -->
                  <div class="plan-section rounded-3 p-3">
                     <div class="form-section-header form-section-header--plan mb-3">
                        <i class="bi bi-tag-fill text-danger"></i>
                        Membership Plan
                     </div>
                     <div class="row g-3">
                        <div class="col-md-6">
                           <label class="form-label form-label-sm">Rate Plan</label>
                           <select class="form-select" v-model="form.rate_plan_id">
                              <option value="">Select a plan...</option>
                              <option v-for="p in ratePlansData" :key="p.id" :value="p.id">{{ p.name }}</option>
                           </select>
                        </div>
                        <div class="col-md-6" v-if="form.rate_plan_id">
                           <label class="form-label form-label-sm">Start Date</label>
                           <input type="date" class="form-control" v-model="form.start_date" />
                        </div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="submitForm" :disabled="submitting">
                     <span v-if="submitting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     {{ modalMode === "add" ? "Create Member" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <!-- View Member Modal -->
      <div class="modal fade" id="memberViewModal" tabindex="-1" ref="memberViewModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" v-if="viewMember">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Member Details</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <!-- Avatar + name -->
                  <div class="d-flex align-items-center gap-3 mb-4 pb-4 border-bottom">
                     <div class="member-avatar member-avatar-lg">
                        {{ $filters.getNameInitials(viewMember.name) }}
                     </div>
                     <div>
                        <h5 class="mb-0 fw-bold">{{ viewMember.name }}</h5>
                        <div class="text-muted small">{{ viewMember.email }}</div>
                        <span :class="['m-badge', $filters.statusBadge(viewMember.status), 'mt-1']">{{ $filters.capitalize(viewMember.status) }}</span>
                     </div>
                  </div>

                  <div class="row g-3">
                     <div class="col-md-6">
                        <div class="small text-muted">Phone</div>
                        <div class="fw-semibold small">{{ viewMember.phone || "—" }}</div>
                     </div>
                     <div class="col-md-6">
                        <div class="small text-muted">Branch</div>
                        <div class="fw-semibold small">{{ viewMember.branches && viewMember.branches.length ? viewMember.branches.map((b) => b.name).join(", ") : "—" }}</div>
                     </div>
                     <div class="col-md-6">
                        <div class="small text-muted">Date of Birth</div>
                        <div class="fw-semibold small">{{ $filters.formatDate(viewMember.profile?.date_of_birth) }}</div>
                     </div>
                     <div class="col-md-6">
                        <div class="small text-muted">Gender</div>
                        <div class="fw-semibold small text-capitalize">{{ viewMember.profile?.gender || "—" }}</div>
                     </div>
                     <div class="col-md-6">
                        <div class="small text-muted">Emergency Contact</div>
                        <div class="fw-semibold small">{{ viewMember.profile?.emergency_contact_name || "—" }}</div>
                     </div>
                     <div class="col-md-6">
                        <div class="small text-muted">Emergency Phone</div>
                        <div class="fw-semibold small">{{ viewMember.profile?.emergency_contact_phone || "—" }}</div>
                     </div>
                     <div class="col-12" v-if="viewMember.profile && viewMember.profile.notes">
                        <div class="small text-muted">Notes</div>
                        <div class="fw-semibold small">{{ viewMember.profile.notes }}</div>
                     </div>
                  </div>

                  <!-- Active Subscription -->
                  <p class="modal-section-label">Active Subscription</p>
                  <div v-if="getActivePlan(viewMember)">
                     <div class="subscription-box">
                        <div>
                           <div class="fw-semibold">{{ getActivePlan(viewMember).name }}</div>
                           <div class="small text-muted">
                              From {{ $filters.formatDate(getActivePlan(viewMember).pivot.start_date) }}
                              <template v-if="getActivePlan(viewMember).pivot.end_date"> &mdash; Expires {{ $filters.formatDate(getActivePlan(viewMember).pivot.end_date) }} </template>
                           </div>
                        </div>
                        <span class="m-badge" :class="getPlanStatusClass(getActivePlan(viewMember).pivot.status)">
                           {{ $filters.capitalize(getActivePlan(viewMember).pivot.status) }}
                        </span>
                     </div>
                  </div>
                  <div v-else class="text-muted small">No active subscription.</div>

                  <div class="text-muted small mt-3">Member since {{ $filters.formatDate(viewMember.created_at) }}</div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="button" class="btn btn-sm btn-danger" @click="openEditFromView"><i class="bi bi-pencil me-1"></i> Edit Member</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import MultiSelect from "./_vendor/MultiSelect.vue";

export default {
   components: {
      MultiSelect,
   },
   props: {
      branchesData: {
         type: Array,
         default: function () {
            return [];
         },
      },
      ratePlansData: {
         type: Array,
         default: function () {
            return [];
         },
      },
   },

   data: function () {
      return {
         loading: true,
         submitting: false,
         pageError: "",
         members: [],
         pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
         stats: { total: 0, active: 0, inactive: 0, suspended: 0 },
         search: "",
         selectedBranch: parseInt(localStorage.getItem("selectedBranch")) || "",
         selectedStatus: "",
         selectedPlan: "",
         currentPage: 1,
         searchTimer: null,
         modalMode: "add",
         viewMember: null,
         formError: "",
         formErrors: {},
         form: this.emptyForm(),
         memberModal: null,
         viewModal: null,
      };
   },

   mounted: function () {
      this.memberModal = new Modal(this.$refs.memberFormModal);
      this.viewModal = new Modal(this.$refs.memberViewModal);
      this.fetchMembers();
   },

   methods: {
      emptyForm: function () {
         return {
            name: "",
            email: "",
            phone: "",
            password: "",
            status: "active",
            branch_ids: this.selectedBranch ? [this.selectedBranch] : [],
            date_of_birth: "",
            gender: "",
            emergency_contact_name: "",
            emergency_contact_phone: "",
            notes: "",
            rate_plan_id: "",
            start_date: new Date().toISOString().slice(0, 10),
         };
      },

      fetchMembers: function (page = 1) {
         this.loading = true;
         this.pageError = "";
         axios
            .post("/panel/members/list", {
               search: this.search,
               branch: this.selectedBranch,
               status: this.selectedStatus,
               plan: this.selectedPlan,
               page: page || this.currentPage,
            })
            .then((res) => {
               this.members = res.data.members.data;
               this.currentPage = res.data.members.current_page;
               this.pagination = {
                  currentPage: res.data.members.current_page,
                  lastPage: res.data.members.last_page,
                  total: res.data.members.total,
                  from: res.data.members.from || 0,
                  to: res.data.members.to || 0,
                  links: res.data.members.links,
               };
               this.stats = res.data.stats;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load members."))
            .finally(() => (this.loading = false));
      },

      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchMembers(), 500);
      },

      clearFilters: function () {
         this.search = "";
         this.selectedBranch = "";
         this.selectedStatus = "";
         this.selectedPlan = "";
         this.fetchMembers();
      },

      goToPage: function (link) {
         if (!link.url) return;
         var page = parseInt(new URL(link.url).searchParams.get("page") || "1");
         this.fetchMembers(page);
      },

      openAddModal: function () {
         this.modalMode = "add";
         this.form = this.emptyForm();
         this.pageError = "";
         this.formError = "";
         this.formErrors = {};
         this.memberModal.show();
      },

      openEditModal: function (member) {
         this.modalMode = "edit";
         this.pageError = "";
         this.formError = "";
         this.formErrors = {};
         var plan = this.getActivePlan(member);
         this.form = {
            id: member.id,
            name: member.name || "",
            email: member.email || "",
            phone: member.phone || "",
            password: "",
            status: member.status || "active",
            branch_ids: member.branches ? member.branches.map((b) => b.id) : [],
            date_of_birth: (member.profile && member.profile.date_of_birth) || "",
            gender: (member.profile && member.profile.gender) || "",
            emergency_contact_name: (member.profile && member.profile.emergency_contact_name) || "",
            emergency_contact_phone: (member.profile && member.profile.emergency_contact_phone) || "",
            notes: (member.profile && member.profile.notes) || "",
            rate_plan_id: plan ? plan.id : "",
            start_date: plan ? plan.pivot.start_date : new Date().toISOString().slice(0, 10),
         };
         this.memberModal.show();
      },

      openViewModal: function (member) {
         this.viewMember = member;
         this.viewModal.show();
      },

      openEditFromView: function () {
         this.viewModal.hide();
         this.$refs.memberViewModal.addEventListener("hidden.bs.modal", () => this.openEditModal(this.viewMember), { once: true });
      },

      submitForm: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};
         var isEdit = this.modalMode === "edit";
         var url = isEdit ? `/panel/members/${this.form.id}` : "/panel/members";
         var method = isEdit ? "put" : "post";
         axios[method](url, this.form)
            .then(() => {
               this.memberModal.hide();
               this.fetchMembers(this.currentPage);
            })
            .catch((err) => {
               if (err.response && err.response.status === 422) {
                  var errors = err.response.data.errors || {};
                  this.formErrors = Object.fromEntries(
                     Object.entries(errors).map(function (e) {
                        return [e[0], Array.isArray(e[1]) ? e[1][0] : e[1]];
                     }),
                  );
               } else {
                  this.formError = "Something went wrong. Please try again.";
               }
            })
            .finally(() => (this.submitting = false));
      },

      getPlanStatusClass: function (status) {
         var map = { active: "m-badge--plan-active", expired: "m-badge--plan-expired", cancelled: "m-badge--plan-cancelled", paused: "m-badge--plan-paused" };
         return map[status] || "m-badge--plan-expired";
      },

      getActivePlan: function (member) {
         if (!member.rate_plans || !member.rate_plans.length) return null;
         return (
            member.rate_plans.find(function (p) {
               return p.pivot && p.pivot.status === "active";
            }) || member.rate_plans[0]
         );
      },
   },

   computed: {
      hasActiveFilters: function () {
         return !!(this.search || this.selectedBranch || this.selectedStatus || this.selectedPlan);
      },
      statCards: function () {
         return [
            { label: "Total Members", value: this.stats.total, icon: "bi-people-fill", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Active", value: this.stats.active, icon: "bi-person-check-fill", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Inactive", value: this.stats.inactive, icon: "bi-person-dash-fill", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Suspended", value: this.stats.suspended, icon: "bi-person-x-fill", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },
};
</script>
