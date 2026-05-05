<template>
   <div class="members-page">
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <!-- Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Members</h4>
            <p class="text-muted small mb-0">All registered gym members</p>
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
            <div class="col-12 col-md-6">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                     <i class="bi bi-search text-muted search-icon"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" placeholder="Search name, email, or phone…" v-model="search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-3">
               <select class="form-select" v-model="selectedStatus" @change="fetchMembers(1)">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="suspended">Suspended</option>
               </select>
            </div>
            <div class="col-6 col-md-3">
               <select class="form-select" v-model="selectedPlan" @change="fetchMembers(1)">
                  <option value="">All Plans</option>
                  <option v-for="p in ratePlansData" :key="p.id" :value="p.id">{{ p.name }}</option>
               </select>
            </div>
         </div>
      </div>

      <!-- Table Card -->
      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Member List
               <span class="badge-count ms-1">{{ loading ? "-" : pagination.total }}</span>
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
                                 <div class="skeleton-box sk-email mt-1"></div>
                              </div>
                           </div>
                        </td>
                        <td><div class="skeleton-box sk-phone"></div></td>
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
                           <a :href="`/panel/members/${member.id}`" class="text-decoration-none d-flex align-items-center gap-2">
                              <div class="member-avatar">{{ $filters.getNameInitials(member.name) }}</div>
                              <div>
                                 <div class="member-name">{{ member.name }}</div>
                                 <div class="member-email">{{ member.email }}</div>
                                 <div class="small text-muted mt-1" v-if="member.profile && member.profile.notes">{{ getMemberNotes(member) }}</div>
                              </div>
                           </a>
                        </td>
                        <td class="text-muted small">{{ member.phone || "-" }}</td>
                        <td>
                           <template v-if="getCurrentMembership(member)">
                              <div class="plan-name mb-1">{{ getCurrentMembership(member).rate_plan.name }}</div>
                              <span class="m-badge" :class="getPlanStatusClass(getCurrentMembership(member).status)">
                                 {{ $filters.capitalize(getCurrentMembership(member).status) }}
                              </span>
                              <div class="small text-muted mt-1" v-if="getActivePtPackage(member)">{{ getActivePtPackage(member).pt_product?.name || "PT Package" }} • {{ getActivePtPackage(member).remaining_sessions }}/{{ getActivePtPackage(member).total_sessions }} left</div>
                           </template>
                           <template v-else-if="getActivePtPackage(member)">
                              <div class="plan-name mb-1">{{ getActivePtPackage(member).pt_product?.name || "PT Package" }}</div>
                              <span class="m-badge m-badge--plan-active"> {{ getActivePtPackage(member).remaining_sessions }}/{{ getActivePtPackage(member).total_sessions }} left </span>
                           </template>
                           <span v-else class="text-muted small">-</span>
                        </td>
                        <td>
                           <span :class="['m-badge', $filters.statusBadge(member.status)]">{{ $filters.capitalize(member.status) }}</span>
                        </td>
                        <td class="text-muted small">{{ formatDate(member.created_at) }}</td>
                        <td>
                           <div class="d-flex gap-1">
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
                     <a :href="`/panel/members/${member.id}`" class="member-card-identity text-decoration-none">
                        <div class="member-avatar">{{ $filters.getNameInitials(member.name) }}</div>
                        <div>
                           <div class="member-card-name">{{ member.name }}</div>
                           <div class="member-card-sub">{{ member.email }}</div>
                           <div class="small text-muted mt-1" v-if="member.profile && member.profile.notes">{{ getMemberNotes(member) }}</div>
                        </div>
                     </a>
                     <div class="dropdown">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditModal(member)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                        </ul>
                     </div>
                  </div>
                  <div class="member-card-tags">
                     <span :class="['m-badge', $filters.statusBadge(member.status)]">{{ $filters.capitalize(member.status) }}</span>
                     <template v-if="getCurrentMembership(member)">
                        <span class="text-capitalize m-badge m-badge--plan">{{ getCurrentMembership(member).rate_plan.name }}</span>
                        <span class="m-badge" :class="getPlanStatusClass(getCurrentMembership(member).status)">{{ $filters.capitalize(getCurrentMembership(member).status) }}</span>
                     </template>
                     <span v-if="getActivePtPackage(member)" class="m-badge m-badge--plan-active"> PT {{ getActivePtPackage(member).remaining_sessions }}/{{ getActivePtPackage(member).total_sessions }} </span>
                  </div>
                  <div class="small text-muted mt-2" v-if="member.profile && member.profile.notes">
                     {{ member.profile.notes }}
                  </div>
                  <div class="member-card-footer">
                     <span v-if="getActivePtPackage(member)">{{ getActivePtPackage(member).remaining_sessions }}/{{ getActivePtPackage(member).total_sessions }} PT left</span>
                     <span><i class="bi bi-calendar3 me-1"></i>{{ formatDate(member.created_at) }}</span>
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
                        <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" placeholder="Full name" />
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
                           <option disabled value="">Select a gender...</option>
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
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">ID Discount</label>
                        <select class="form-select" v-model="form.discount_type">
                           <option :value="''">None - regular rate</option>
                           <option value="student">Student - 20% off</option>
                           <option value="senior">Senior - 20% off</option>
                        </select>
                        <div class="form-text">Verify a valid student / senior ID before saving.</div>
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
                              <option disabled value="">Select a plan...</option>
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
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDate, toDateInputValue } from "../../dates";

export default {
   props: {
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
         search: new URLSearchParams(window.location.search).get("search") || "",
         selectedStatus: "",
         selectedPlan: "",
         currentPage: 1,
         searchTimer: null,
         modalMode: "add",
         formError: "",
         formErrors: {},
         form: this.emptyForm(),
         memberModal: null,
      };
   },

   mounted: function () {
      this.memberModal = new Modal(this.$refs.memberFormModal);
      this.fetchMembers();
   },

   methods: {
      formatDate,
      emptyForm: function () {
         return {
            name: "",
            email: "",
            phone: "",
            password: "",
            status: "active",
            date_of_birth: "",
            gender: "",
            emergency_contact_name: "",
            emergency_contact_phone: "",
            notes: "",
            discount_type: "",
            rate_plan_id: "",
            start_date: toDateInputValue(),
         };
      },

      fetchMembers: function (page = 1) {
         this.loading = true;
         this.pageError = "";
         axios
            .get("/panel/members/list", {
               params: {
                  search: this.search || undefined,
                  status: this.selectedStatus || undefined,
                  plan: this.selectedPlan || undefined,
                  page: page || this.currentPage,
               },
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
         var plan = this.getCurrentMembership(member);
         this.form = {
            id: member.id,
            name: member.name || "",
            email: member.email || "",
            phone: member.phone || "",
            password: "",
            status: member.status || "active",
            date_of_birth: (member.profile && member.profile.date_of_birth) || "",
            gender: (member.profile && member.profile.gender) || "",
            emergency_contact_name: (member.profile && member.profile.emergency_contact_name) || "",
            emergency_contact_phone: (member.profile && member.profile.emergency_contact_phone) || "",
            notes: (member.profile && member.profile.notes) || "",
            discount_type: (member.profile && member.profile.discount_type) || "",
            rate_plan_id: plan ? plan.rate_plan_id : "",
            start_date: plan ? toDateInputValue(plan.start_date) : toDateInputValue(),
         };
         this.memberModal.show();
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

      getCurrentMembership: function (member) {
         if (!member.member_subscriptions || !member.member_subscriptions.length) return null;
         return (
            member.member_subscriptions.find(function (membership) {
               return membership.status === "active" || membership.status === "paused";
            }) || member.member_subscriptions[0]
         );
      },

      getActivePtPackage: function (member) {
         if (!member.member_pt_packages || !member.member_pt_packages.length) return null;

         return (
            member.member_pt_packages.find(function (pkg) {
               return pkg.status === "active" && Number(pkg.remaining_sessions) > 0;
            }) || member.member_pt_packages[0]
         );
      },

      getMemberNotes: function (member) {
         var notes = member.profile && member.profile.notes ? member.profile.notes.trim() : "";

         if (!notes) {
            return "-";
         }

         return notes.length > 60 ? `${notes.slice(0, 57)}...` : notes;
      },
   },

   computed: {
      hasActiveFilters: function () {
         return !!(this.search || this.selectedStatus || this.selectedPlan);
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
