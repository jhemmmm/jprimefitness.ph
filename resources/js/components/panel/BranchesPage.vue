<template>
   <div class="branches-page">
      <!-- Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Branches</h4>
            <p class="text-muted small mb-0">Manage gym locations and their details</p>
         </div>
         <button v-if="is('super admin')" class="btn btn-danger px-3" @click="openAddModal">
            <i class="bi bi-plus-lg me-1"></i>
            Add Branch
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
            <div class="col-12 col-md-5">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                     <i class="bi bi-search text-muted search-icon"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" placeholder="Search name, city, province…" v-model="search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-3">
               <select class="form-select" v-model="selectedStatus" @change="fetchBranches(1)">
                  <option value="">All Statuses</option>
                  <option value="open">Open</option>
                  <option value="closed">Closed</option>
                  <option value="coming_soon">Coming Soon</option>
               </select>
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
               Branch List
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
                        <th>Location</th>
                        <th>Phone</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="i in 5" :key="'sk' + i">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="skeleton-box sk-avatar"></div>
                              <div class="skeleton-box sk-name"></div>
                           </div>
                        </td>
                        <td><div class="skeleton-box sk-branch"></div></td>
                        <td><div class="skeleton-box sk-phone"></div></td>
                        <td><div class="skeleton-box sk-joined"></div></td>
                        <td><div class="skeleton-box sk-plan-badge"></div></td>
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
            <!-- Mobile skeleton -->
            <div class="d-md-none">
               <div class="member-card" v-for="i in 4" :key="'skm' + i">
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
         <div v-else-if="branches.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-geo-alt empty-icon"></i>
            <p class="mt-2 mb-1">No branches found</p>
            <p class="small" v-if="hasActiveFilters">Try adjusting your filters</p>
            <button class="btn btn-danger btn-sm mt-1" @click="openAddModal" v-else-if="is('super admin')">Add first branch</button>
         </div>

         <!-- Data -->
         <div v-else>
            <!-- Desktop table -->
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Hours</th>
                        <th class="col-actions" v-if="is('super admin') || is('admin')"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="b in branches" :key="b.id">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="branch-avatar">
                                 <i class="bi bi-geo-alt-fill"></i>
                              </div>
                              <div>
                                 <div class="member-name">{{ b.name }}</div>
                                 <div>
                                    <span :class="['m-badge', $filters.statusBadge(b.status)]">{{ $filters.capitalize(b.status) }}</span>
                                 </div>
                              </div>
                           </div>
                        </td>
                        <td class="small">
                           <div>
                              {{ b.city }}<span v-if="b.province">, {{ b.province }}</span>
                           </div>
                           <div class="text-muted" v-if="b.address" style="font-size: 0.75rem">{{ b.address }}</div>
                        </td>
                        <td class="small">
                           <div v-if="b.phone">
                              <a :href="'tel:' + b.phone" class="text-decoration-none text-muted"><i class="bi bi-telephone me-1"></i>{{ b.phone }}</a>
                           </div>
                           <div v-if="b.email">
                              <a :href="'mailto:' + b.email" class="text-decoration-none text-muted"><i class="bi bi-envelope me-1"></i>{{ b.email }}</a>
                           </div>
                           <div class="d-flex gap-2 mt-1" v-if="b.facebook_url || b.messenger_url || b.whatsapp_url">
                              <a v-if="b.facebook_url" :href="b.facebook_url" target="_blank" rel="noopener noreferrer" class="social-link" title="Facebook"><i class="bi bi-facebook"></i></a>
                              <a v-if="b.messenger_url" :href="b.messenger_url" target="_blank" rel="noopener noreferrer" class="social-link" title="Messenger"><i class="bi bi-messenger"></i></a>
                              <a v-if="b.whatsapp_url" :href="b.whatsapp_url" target="_blank" rel="noopener noreferrer" class="social-link" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                           </div>
                           <span v-if="!b.phone && !b.email && !b.facebook_url && !b.messenger_url && !b.whatsapp_url" class="text-muted">—</span>
                        </td>
                        <td class="small text-muted">
                           <span v-if="b.opening_time && b.closing_time"> {{ formatTime(b.opening_time) }} – {{ formatTime(b.closing_time) }} </span>
                           <span v-else>—</span>
                        </td>
                        <td v-if="is('super admin') || is('admin')">
                           <div class="d-flex gap-1">
                              <button class="btn btn-sm btn-outline-secondary" title="Edit" @click="openEditModal(b)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button v-if="is('super admin')" class="btn btn-sm btn-outline-danger" title="Delete" @click="confirmDelete(b)">
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
               <div class="member-card" v-for="b in branches" :key="'mc' + b.id">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="branch-avatar">
                           <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div>
                           <div class="member-card-name">{{ b.name }}</div>
                           <div class="member-card-sub">
                              {{ b.city }}<span v-if="b.province">, {{ b.province }}</span>
                           </div>
                        </div>
                     </div>
                     <div class="dropdown" v-if="is('super admin') || is('admin')">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditModal(b)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                           <li v-if="is('super admin')"><hr class="dropdown-divider" /></li>
                           <li v-if="is('super admin')">
                              <a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete(b)"><i class="bi bi-trash me-2"></i>Delete</a>
                           </li>
                        </ul>
                     </div>
                  </div>
                  <div class="member-card-tags">
                     <span :class="['m-badge', $filters.statusBadge(b.status)]">{{ $filters.capitalize(b.status) }}</span>
                     <span class="m-badge m-badge--plan" v-if="b.opening_time && b.closing_time"> {{ formatTime(b.opening_time) }} – {{ formatTime(b.closing_time) }} </span>
                  </div>
                  <div class="member-card-footer">
                     <span><i class="bi bi-telephone me-1"></i>{{ b.phone || "—" }}</span>
                     <span class="d-flex gap-2 align-items-center">
                        <a v-if="b.email" :href="'mailto:' + b.email" class="social-link" title="Email"><i class="bi bi-envelope"></i></a>
                        <a v-if="b.facebook_url" :href="b.facebook_url" target="_blank" rel="noopener noreferrer" class="social-link" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a v-if="b.messenger_url" :href="b.messenger_url" target="_blank" rel="noopener noreferrer" class="social-link" title="Messenger"><i class="bi bi-messenger"></i></a>
                        <a v-if="b.whatsapp_url" :href="b.whatsapp_url" target="_blank" rel="noopener noreferrer" class="social-link" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <span class="member-card-num">#{{ b.id }}</span>
                     </span>
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

      <!-- ── Add / Edit Branch Modal  -->
      <div class="modal fade" id="branchFormModal" tabindex="-1" ref="branchFormModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">
                     {{ modalMode === "add" ? "Add Branch" : "Edit Branch" }}
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div v-if="formError" class="alert alert-danger py-2 small mb-3">{{ formError }}</div>
                  <!-- Basic Info -->
                  <div class="form-section-header">
                     <i class="bi bi-geo-alt-fill"></i>
                     Basic Information
                  </div>
                  <div class="row g-3 mb-4">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" :disabled="modalMode === 'edit' && is('admin') && !is('super admin')" placeholder="e.g. JPRIME Fitness Calabanga" />
                        <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Status <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.status" :class="{ 'is-invalid': formErrors.status }" :disabled="modalMode === 'edit' && is('admin') && !is('super admin')">
                           <option value="open">Open</option>
                           <option value="closed">Closed</option>
                           <option value="coming_soon">Coming Soon</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.status">{{ formErrors.status }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Opening Time</label>
                        <input type="time" class="form-control" v-model="form.opening_time" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Closing Time</label>
                        <input type="time" class="form-control" v-model="form.closing_time" />
                     </div>
                  </div>

                  <!-- Location -->
                  <div class="form-section-header">
                     <i class="bi bi-pin-map-fill"></i>
                     Location
                  </div>
                  <div class="row g-3 mb-4">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">City <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.city" :class="{ 'is-invalid': formErrors.city }" placeholder="e.g. Calabanga" />
                        <div class="invalid-feedback" v-if="formErrors.city">{{ formErrors.city }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Province</label>
                        <input type="text" class="form-control" v-model="form.province" placeholder="e.g. Camarines Sur" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Full Address</label>
                        <input type="text" class="form-control" v-model="form.address" placeholder="Street, Barangay, City, Province, ZIP" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Google Maps URL</label>
                        <input type="url" class="form-control" v-model="form.map_url" :class="{ 'is-invalid': formErrors.map_url }" placeholder="https://maps.google.com/..." />
                        <div class="invalid-feedback" v-if="formErrors.map_url">{{ formErrors.map_url }}</div>
                     </div>
                  </div>

                  <!-- Contact -->
                  <div class="form-section-header">
                     <i class="bi bi-telephone-fill"></i>
                     Contact
                  </div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Phone</label>
                        <input type="text" class="form-control" v-model="form.phone" placeholder="+63 9XX XXX XXXX" />
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Email</label>
                        <input type="email" class="form-control" v-model="form.email" :class="{ 'is-invalid': formErrors.email }" placeholder="branch@jprimefitness.ph" />
                        <div class="invalid-feedback" v-if="formErrors.email">{{ formErrors.email }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Facebook URL</label>
                        <input type="url" class="form-control" v-model="form.facebook_url" :class="{ 'is-invalid': formErrors.facebook_url }" placeholder="https://facebook.com/..." />
                        <div class="invalid-feedback" v-if="formErrors.facebook_url">{{ formErrors.facebook_url }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Messenger URL</label>
                        <input type="url" class="form-control" v-model="form.messenger_url" :class="{ 'is-invalid': formErrors.messenger_url }" placeholder="https://m.me/..." />
                        <div class="invalid-feedback" v-if="formErrors.messenger_url">{{ formErrors.messenger_url }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">WhatsApp URL</label>
                        <input type="url" class="form-control" v-model="form.whatsapp_url" :class="{ 'is-invalid': formErrors.whatsapp_url }" placeholder="https://wa.me/..." />
                        <div class="invalid-feedback" v-if="formErrors.whatsapp_url">{{ formErrors.whatsapp_url }}</div>
                     </div>
                  </div>

                  <!-- Photos -->
                  <div class="form-section-header mt-4" v-if="is('super admin') || is('admin')">
                     <i class="bi bi-images"></i>
                     Photos / Gallery
                  </div>
                  <div v-if="(is('super admin') || is('admin')) && modalMode === 'edit'">
                     <div class="branch-photos-grid" v-if="form.photos && form.photos.length > 0">
                        <div class="branch-photo-item" v-for="(photo, i) in form.photos" :key="i">
                           <img :src="'/storage/' + photo" class="branch-photo-thumb" :alt="'Branch photo ' + (i + 1)" />
                           <button v-if="is('super admin')" type="button" class="branch-photo-del" @click="deletePhoto(i)" :disabled="photoDeleting === i" title="Remove photo">
                              <span v-if="photoDeleting === i" class="spinner-border spinner-border-sm"></span>
                              <i v-else class="bi bi-x-lg"></i>
                           </button>
                        </div>
                     </div>
                     <p class="text-muted small mb-2" v-else>No photos yet.</p>
                     <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="btn btn-outline-secondary btn-sm mb-0" :class="{ disabled: photoUploading }">
                           <span v-if="photoUploading" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                           <i v-else class="bi bi-upload me-1"></i>
                           Upload Photo(s)
                           <input type="file" accept="image/*" multiple class="d-none" @change="uploadPhotos" :disabled="photoUploading" ref="photoInput" />
                        </label>
                        <span class="text-muted small">JPG, PNG, WEBP — max 5 MB each</span>
                     </div>
                  </div>
                  <p class="text-muted small mb-0" v-else-if="is('super admin') || is('admin')">Save the branch first to upload photos.</p>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="submitForm" :disabled="submitting">
                     <span v-if="submitting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     {{ modalMode === "add" ? "Add Branch" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <!-- ── Delete Confirmation Modal  -->
      <div class="modal fade" id="branchDeleteModal" tabindex="-1" ref="branchDeleteModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Branch</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <p class="mb-1">Are you sure you want to delete this branch?</p>
                  <p class="fw-semibold mb-0">{{ deleteTarget.name }}</p>
                  <p class="text-muted small mb-0">
                     {{ deleteTarget.city }}<span v-if="deleteTarget.province">, {{ deleteTarget.province }}</span>
                  </p>
                  <p class="text-danger small mt-2 mb-0">This action cannot be undone. Members linked to this branch will lose their branch assignment.</p>
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
   data: function () {
      return {
         loading: true,
         submitting: false,
         deleting: false,
         branches: [],
         pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
         stats: { total: 0, open: 0, closed: 0, coming_soon: 0 },
         search: "",
         selectedStatus: "",
         currentPage: 1,
         searchTimer: null,
         modalMode: "add",
         formError: "",
         formErrors: {},
         form: this.emptyForm(),
         formModal: null,
         deleteModal: null,
         deleteTarget: null,
         photoUploading: false,
         photoDeleting: null,
      };
   },
   mounted: function () {
      this.formModal = new Modal(this.$refs.branchFormModal);
      this.deleteModal = new Modal(this.$refs.branchDeleteModal);
      this.fetchBranches();
   },
   methods: {
      emptyForm: function () {
         return {
            name: "",
            status: "open",
            city: "",
            province: "",
            address: "",
            phone: "",
            email: "",
            opening_time: "",
            closing_time: "",
            facebook_url: "",
            messenger_url: "",
            whatsapp_url: "",
            map_url: "",
            photos: [],
         };
      },
      fetchBranches: function (page = 1) {
         this.loading = true;
         axios
            .get("/panel/branches/list", {
               params: {
                  search: this.search || undefined,
                  status: this.selectedStatus || undefined,
                  page: page,
               },
            })
            .then((res) => {
               this.branches = res.data.branches.data;
               this.pagination = {
                  currentPage: res.data.branches.current_page,
                  lastPage: res.data.branches.last_page,
                  total: res.data.branches.total,
                  from: res.data.branches.from || 0,
                  to: res.data.branches.to || 0,
                  links: res.data.branches.links,
               };
               this.stats = res.data.stats;
               this.currentPage = page;
               this.loading = false;
            })
            .catch(() => (this.loading = false));
      },

      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchBranches(), 500);
      },

      clearFilters: function () {
         this.search = "";
         this.selectedStatus = "";
         this.fetchBranches();
      },

      goToPage: function (link) {
         if (!link.url) return;
         var page = parseInt(new URL(link.url).searchParams.get("page") || "1");
         this.fetchBranches(page);
      },

      openAddModal: function () {
         this.modalMode = "add";
         this.form = this.emptyForm();
         this.formError = "";
         this.formErrors = {};
         this.formModal.show();
      },

      openEditModal: function (b) {
         this.modalMode = "edit";
         this.formError = "";
         this.formErrors = {};
         this.form = {
            id: b.id,
            name: b.name || "",
            status: b.status || "open",
            city: b.city || "",
            province: b.province || "",
            address: b.address || "",
            phone: b.phone || "",
            email: b.email || "",
            opening_time: b.opening_time ? b.opening_time.slice(0, 5) : "",
            closing_time: b.closing_time ? b.closing_time.slice(0, 5) : "",
            facebook_url: b.facebook_url || "",
            messenger_url: b.messenger_url || "",
            whatsapp_url: b.whatsapp_url || "",
            map_url: b.map_url || "",
            photos: b.photos || [],
         };
         this.formModal.show();
      },

      confirmDelete: function (b) {
         this.deleteTarget = b;
         this.deleteModal.show();
      },

      doDelete: function () {
         if (!this.deleteTarget) return;
         this.deleting = true;
         axios
            .delete(`/panel/branches/${this.deleteTarget.id}`)
            .then(() => {
               this.deleteModal.hide();
               this.deleteTarget = null;
               this.fetchBranches(this.currentPage);
            })
            .catch(() => {})
            .finally(() => (this.deleting = false));
      },

      submitForm: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         let payload = { ...this.form };
         if (this.modalMode === "edit" && this.is("admin") && !this.is("super admin")) {
            payload = {
               city: this.form.city,
               province: this.form.province,
               address: this.form.address,
               phone: this.form.phone,
               email: this.form.email,
               opening_time: this.form.opening_time,
               closing_time: this.form.closing_time,
               facebook_url: this.form.facebook_url,
               messenger_url: this.form.messenger_url,
               whatsapp_url: this.form.whatsapp_url,
               map_url: this.form.map_url,
            };
         }

         const request = this.modalMode === "edit" ? axios.put(`/panel/branches/${this.form.id}`, payload) : axios.post("/panel/branches", payload);
         request
            .then(() => {
               this.formModal.hide();
               this.fetchBranches(this.currentPage);
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

      formatTime: function (timeStr) {
         if (!timeStr) return "";
         var parts = timeStr.split(":");
         var h = parseInt(parts[0]);
         var m = parts[1];
         var ampm = h >= 12 ? "PM" : "AM";
         h = h % 12 || 12;
         return h + ":" + m + " " + ampm;
      },

      uploadPhotos: function (e) {
         var files = Array.from(e.target.files);
         if (!files.length) return;
         this.photoUploading = true;
         var uploadNext = (i) => {
            if (i >= files.length) {
               this.photoUploading = false;
               if (this.$refs.photoInput) this.$refs.photoInput.value = "";
               return;
            }
            var fd = new FormData();
            fd.append("photo", files[i]);
            axios
               .post(`/panel/branches/${this.form.id}/photos`, fd, {
                  headers: { "Content-Type": "multipart/form-data" },
               })
               .then((res) => {
                  if (!this.form.photos) this.form.photos = [];
                  this.form.photos.push(res.data.path);
                  uploadNext(i + 1);
               })
               .catch(() => {
                  this.photoUploading = false;
                  if (this.$refs.photoInput) this.$refs.photoInput.value = "";
               });
         };
         uploadNext(0);
      },

      deletePhoto: function (index) {
         this.photoDeleting = index;
         axios
            .delete(`/panel/branches/${this.form.id}/photos/${index}`)
            .then(() => {
               this.form.photos.splice(index, 1);
            })
            .catch(() => {})
            .finally(() => (this.photoDeleting = null));
      },
   },
   computed: {
      hasActiveFilters: function () {
         return !!(this.search || this.selectedStatus);
      },
      statCards: function () {
         return [
            { label: "Total Branches", value: this.stats.total, icon: "bi-geo-alt-fill", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Open", value: this.stats.open, icon: "bi-door-open-fill", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Closed", value: this.stats.closed, icon: "bi-door-closed-fill", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Coming Soon", value: this.stats.coming_soon, icon: "bi-hourglass-split", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },
};
</script>
