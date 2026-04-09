<template>
   <div class="inventory-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Inventory</h4>
            <p class="text-muted small mb-0">Track stock levels, categories, and item pricing for your business</p>
         </div>
         <button class="btn btn-danger px-3" @click="openAddModal">
            <i class="bi bi-plus-lg me-1"></i>
            Add Item
         </button>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-lg-3" v-for="stat in statCards" :key="stat.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="stat.iconBg">
                  <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ stat.label }}</div>
                  <div class="stat-card-value" v-if="loading">
                     <div class="skeleton-box" style="width: 52px; height: 18px; border-radius: 5px"></div>
                  </div>
                  <div class="stat-card-value" v-else>{{ stat.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <div class="panel-card mb-4 p-3">
         <div class="row g-2 align-items-center">
            <div class="col-12 col-lg-5">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                     <i class="bi bi-search text-muted search-icon"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" placeholder="Search item, SKU, category..." v-model="search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
               <select class="form-select" v-model="selectedCategory" @change="fetchItems(1)">
                  <option value="">All Categories</option>
                  <option v-for="category in categoriesData" :key="category.id" :value="category.id">{{ category.name }}</option>
               </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
               <select class="form-select" v-model="selectedStatus" @change="fetchItems(1)">
                  <option value="">All Statuses</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
               </select>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
               <select class="form-select" v-model="selectedStockState" @change="fetchItems(1)">
                  <option value="">All Stock</option>
                  <option value="in_stock">In Stock</option>
                  <option value="low_stock">Low Stock</option>
                  <option value="out_of_stock">Out of Stock</option>
               </select>
            </div>
         </div>
      </div>

      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Inventory List
               <span class="badge-count ms-1">{{ loading ? "-" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0">Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</span>
         </div>

         <div v-if="loading">
            <div class="d-none d-md-block table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Item</th>
                        <th>Stock</th>
                        <th>Pricing</th>
                        <th>Updated</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="i in 5" :key="'inventory-sk-' + i">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="skeleton-box" style="width: 40px; height: 40px; border-radius: 14px"></div>
                              <div>
                                 <div class="skeleton-box mb-1" style="width: 150px; height: 14px; border-radius: 4px"></div>
                                 <div class="skeleton-box mb-2" style="width: 115px; height: 11px; border-radius: 4px"></div>
                                 <div class="d-flex gap-1">
                                    <div class="skeleton-box" style="width: 84px; height: 22px; border-radius: 999px"></div>
                                    <div class="skeleton-box" style="width: 56px; height: 22px; border-radius: 999px"></div>
                                 </div>
                              </div>
                           </div>
                        </td>
                        <td><div class="skeleton-box" style="width: 110px; height: 12px; border-radius: 4px"></div></td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 95px; height: 13px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 125px; height: 11px; border-radius: 4px"></div>
                        </td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 85px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 100px; height: 12px; border-radius: 4px"></div>
                        </td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 100px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 88px; height: 11px; border-radius: 4px"></div>
                        </td>
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
               <div class="member-card" v-for="i in 4" :key="'inventory-mobile-sk-' + i">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="skeleton-box" style="width: 40px; height: 40px; border-radius: 14px"></div>
                        <div>
                           <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 95px; height: 11px; border-radius: 4px"></div>
                        </div>
                     </div>
                     <div class="skeleton-box" style="width: 30px; height: 30px; border-radius: 6px"></div>
                  </div>
                  <div class="member-card-tags mt-2">
                     <div class="skeleton-box" style="width: 86px; height: 22px; border-radius: 999px"></div>
                     <div class="skeleton-box" style="width: 72px; height: 22px; border-radius: 999px"></div>
                     <div class="skeleton-box" style="width: 92px; height: 22px; border-radius: 999px"></div>
                  </div>
                  <div class="mt-3">
                     <div class="skeleton-box mb-2" style="width: 120px; height: 12px; border-radius: 4px"></div>
                     <div class="skeleton-box mb-2" style="width: 145px; height: 12px; border-radius: 4px"></div>
                     <div class="skeleton-box" style="width: 118px; height: 12px; border-radius: 4px"></div>
                  </div>
                  <div class="member-card-footer mt-3">
                     <div class="skeleton-box" style="width: 70px; height: 13px; border-radius: 4px"></div>
                     <div class="skeleton-box" style="width: 34px; height: 13px; border-radius: 4px"></div>
                  </div>
               </div>
            </div>
         </div>

         <div v-else-if="items.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-box-seam empty-icon"></i>
            <p class="mt-2 mb-1">No inventory items found</p>
            <p class="small mb-3" v-if="hasActiveFilters">Try adjusting your filters</p>
            <button class="btn btn-danger btn-sm" @click="openAddModal" v-else>Add first item</button>
         </div>

         <div v-else>
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Item</th>
                        <th>Stock</th>
                        <th>Pricing</th>
                        <th>Updated</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="item in items" :key="item.id">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="inventory-avatar">
                                 <i class="bi bi-box-seam-fill"></i>
                              </div>
                              <div>
                                 <div class="member-name">{{ item.name }}</div>
                                 <div class="text-muted small">
                                    <span v-if="item.category">{{ item.category.name }}</span>
                                    <span v-if="item.category && item.sku"> • </span>
                                    <span v-if="item.sku">{{ item.sku }}</span>
                                    <span v-if="!item.category && !item.sku">-</span>
                                 </div>
                                 <div class="mt-1 d-flex gap-1 flex-wrap">
                                    <span :class="['m-badge', item.is_out_of_stock ? 'm-badge--suspended' : item.is_low_stock ? 'm-badge--pending' : 'm-badge--active']">
                                       <template v-if="item.is_out_of_stock">Out of Stock</template>
                                       <template v-else-if="item.is_low_stock">Low Stock</template>
                                       <template v-else>In Stock</template>
                                    </span>
                                    <span :class="['m-badge', $filters.statusBadge(item.status)]">{{ $filters.capitalize(item.status) }}</span>
                                 </div>
                              </div>
                           </div>
                        </td>
                        <td>
                           <div class="fw-semibold">{{ $filters.formatQuantity(item.quantity) }} {{ item.unit }}</div>
                           <div class="text-muted small">Low stock at {{ $filters.formatQuantity(item.low_stock_threshold) }} {{ item.unit }}</div>
                        </td>
                        <td class="small">
                           <div>Cost: {{ item.cost_price !== null ? `₱${$filters.formatMoney(item.cost_price)}` : "-" }}</div>
                           <div>Selling: {{ item.selling_price !== null ? `₱${$filters.formatMoney(item.selling_price)}` : "-" }}</div>
                        </td>
                        <td class="small text-muted">
                           <div>{{ $filters.formatDateTime(item.updated_at) }}</div>
                           <div v-if="item.last_restocked_at">Restocked {{ $filters.formatDate(item.last_restocked_at) }}</div>
                           <div v-else>Never restocked</div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end">
                              <button class="btn btn-sm btn-outline-secondary" @click="openEditModal(item)">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(item)">
                                 <i class="bi bi-trash tbl-icon"></i>
                              </button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="item in items" :key="'mobile-' + item.id">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="inventory-avatar">
                           <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <div>
                           <div class="member-card-name">{{ item.name }}</div>
                           <div class="member-card-sub">
                              <span v-if="item.category">{{ item.category.name }}</span>
                              <span v-if="item.category && item.sku"> • </span>
                              <span v-if="item.sku">{{ item.sku }}</span>
                              <span v-if="!item.category && !item.sku">No category or SKU</span>
                           </div>
                        </div>
                     </div>
                     <div class="dropdown">
                        <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                           <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                           <li>
                              <a class="dropdown-item" href="#" @click.prevent="openEditModal(item)"><i class="bi bi-pencil me-2"></i>Edit</a>
                           </li>
                           <li><hr class="dropdown-divider" /></li>
                           <li>
                              <a class="dropdown-item text-danger" href="#" @click.prevent="confirmDelete(item)"><i class="bi bi-trash me-2"></i>Delete</a>
                           </li>
                        </ul>
                     </div>
                  </div>
                  <div class="member-card-tags">
                     <span :class="['m-badge', item.is_out_of_stock ? 'm-badge--suspended' : item.is_low_stock ? 'm-badge--pending' : 'm-badge--active']">
                        <template v-if="item.is_out_of_stock">Out of Stock</template>
                        <template v-else-if="item.is_low_stock">Low Stock</template>
                        <template v-else>In Stock</template>
                     </span>
                     <span :class="['m-badge', $filters.statusBadge(item.status)]">{{ $filters.capitalize(item.status) }}</span>
                     <span class="m-badge m-badge--plan" v-if="item.category">{{ item.category.name }}</span>
                  </div>
                  <div class="small text-muted mb-2">
                     <div v-if="item.sku">SKU: {{ item.sku }}</div>
                     <div>{{ $filters.formatQuantity(item.quantity) }} {{ item.unit }} available</div>
                     <div>Low stock at {{ $filters.formatQuantity(item.low_stock_threshold) }} {{ item.unit }}</div>
                  </div>
                  <div class="member-card-footer">
                     <span>{{ item.selling_price !== null ? `₱${$filters.formatMoney(item.selling_price)}` : "-" }}</span>
                     <span class="member-card-num">#{{ item.id }}</span>
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

      <div class="modal fade" id="inventoryFormModal" tabindex="-1" ref="inventoryFormModal">
         <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">{{ modalMode === "add" ? "Add Inventory Item" : "Edit Inventory Item" }}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4">
                  <div v-if="formError" class="alert alert-danger py-2 small mb-3">{{ formError }}</div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" />
                        <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">SKU</label>
                        <input type="text" class="form-control" v-model="form.sku" :class="{ 'is-invalid': formErrors.sku }" />
                        <div class="invalid-feedback" v-if="formErrors.sku">{{ formErrors.sku }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Category <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.inventory_category_id" :class="{ 'is-invalid': formErrors.inventory_category_id }">
                           <option value="" disabled>Select category</option>
                           <option v-for="category in categoriesData" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.inventory_category_id">{{ formErrors.inventory_category_id }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Unit <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.unit" :class="{ 'is-invalid': formErrors.unit }" placeholder="pcs, box, bottle" />
                        <div class="invalid-feedback" v-if="formErrors.unit">{{ formErrors.unit }}</div>
                     </div>
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="form.quantity" :class="{ 'is-invalid': formErrors.quantity }" />
                        <div class="invalid-feedback" v-if="formErrors.quantity">{{ formErrors.quantity }}</div>
                     </div>
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Low Stock Threshold <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="form.low_stock_threshold" :class="{ 'is-invalid': formErrors.low_stock_threshold }" />
                        <div class="invalid-feedback" v-if="formErrors.low_stock_threshold">{{ formErrors.low_stock_threshold }}</div>
                     </div>
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Cost Price</label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="form.cost_price" :class="{ 'is-invalid': formErrors.cost_price }" />
                        <div class="invalid-feedback" v-if="formErrors.cost_price">{{ formErrors.cost_price }}</div>
                     </div>
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Selling Price</label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="form.selling_price" :class="{ 'is-invalid': formErrors.selling_price }" />
                        <div class="invalid-feedback" v-if="formErrors.selling_price">{{ formErrors.selling_price }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Last Restocked At</label>
                        <input type="datetime-local" class="form-control" v-model="form.last_restocked_at" :class="{ 'is-invalid': formErrors.last_restocked_at }" />
                        <div class="invalid-feedback" v-if="formErrors.last_restocked_at">{{ formErrors.last_restocked_at }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Status <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.status" :class="{ 'is-invalid': formErrors.status }">
                           <option value="active">Active</option>
                           <option value="inactive">Inactive</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.status">{{ formErrors.status }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <textarea rows="3" class="form-control" v-model="form.notes" :class="{ 'is-invalid': formErrors.notes }"></textarea>
                        <div class="invalid-feedback" v-if="formErrors.notes">{{ formErrors.notes }}</div>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger px-4" @click="submitForm" :disabled="submitting">
                     <span v-if="submitting" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     {{ modalMode === "add" ? "Add Item" : "Save Changes" }}
                  </button>
               </div>
            </div>
         </div>
      </div>

      <div class="modal fade" id="inventoryDeleteModal" tabindex="-1" ref="inventoryDeleteModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Item</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="deleteTarget">
                  <p class="mb-1">Are you sure you want to delete this inventory item?</p>
                  <p class="fw-semibold mb-0">{{ deleteTarget.name }}</p>
                  <p class="text-muted small mb-0">{{ deleteTarget.category?.name || "Inventory item" }}</p>
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
      categoriesData: {
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
         deleting: false,
         items: [],
         stats: { total: 0, active: 0, low_stock: 0, out_of_stock: 0 },
         pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
         search: new URLSearchParams(window.location.search).get("search") || "",
         selectedCategory: "",
         selectedStatus: "",
         selectedStockState: "",
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
      this.formModal = new Modal(this.$refs.inventoryFormModal);
      this.deleteModal = new Modal(this.$refs.inventoryDeleteModal);
      this.fetchItems();
   },
   methods: {
      emptyForm: function () {
         return {
            id: null,
            inventory_category_id: "",
            name: "",
            sku: "",
            unit: "pcs",
            quantity: "0.00",
            low_stock_threshold: "0.00",
            cost_price: "",
            selling_price: "",
            status: "active",
            notes: "",
            last_restocked_at: "",
         };
      },
      fetchItems: function (page = 1) {
         this.loading = true;
         axios
            .get("/panel/inventory/list", {
               params: {
                  search: this.search || undefined,
                  category: this.selectedCategory || undefined,
                  status: this.selectedStatus || undefined,
                  stock_state: this.selectedStockState || undefined,
                  page: page,
               },
            })
            .then((res) => {
               this.items = res.data.inventory.data;
               this.pagination = {
                  currentPage: res.data.inventory.current_page,
                  lastPage: res.data.inventory.last_page,
                  total: res.data.inventory.total,
                  from: res.data.inventory.from || 0,
                  to: res.data.inventory.to || 0,
                  links: res.data.inventory.links,
               };
               this.stats = res.data.stats;
               this.currentPage = page;
               this.loading = false;
            })
            .catch(() => {
               this.loading = false;
            });
      },
      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchItems(1), 400);
      },
      goToPage: function (link) {
         if (!link.url) {
            return;
         }

         var page = parseInt(new URL(link.url).searchParams.get("page") || "1", 10);
         this.fetchItems(page);
      },
      openAddModal: function () {
         this.modalMode = "add";
         this.formError = "";
         this.formErrors = {};
         this.form = this.emptyForm();
         this.formModal.show();
      },
      openEditModal: function (item) {
         this.modalMode = "edit";
         this.formError = "";
         this.formErrors = {};
         this.form = {
            id: item.id,
            inventory_category_id: item.inventory_category_id || item.category?.id || "",
            name: item.name || "",
            sku: item.sku || "",
            unit: item.unit || "pcs",
            quantity: item.quantity || "0.00",
            low_stock_threshold: item.low_stock_threshold || "0.00",
            cost_price: item.cost_price ?? "",
            selling_price: item.selling_price ?? "",
            status: item.status || "active",
            notes: item.notes || "",
            last_restocked_at: this.$filters.formatDateTime(item.last_restocked_at, "input"),
         };
         this.formModal.show();
      },
      confirmDelete: function (item) {
         this.deleteTarget = item;
         this.deleteModal.show();
      },
      doDelete: function () {
         if (!this.deleteTarget) {
            return;
         }

         this.deleting = true;
         axios
            .delete(`/panel/inventory/${this.deleteTarget.id}`)
            .then(() => {
               this.deleteModal.hide();
               this.deleteTarget = null;
               this.fetchItems(this.currentPage);
            })
            .catch(() => {})
            .finally(() => {
               this.deleting = false;
            });
      },
      submitForm: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};

         var payload = {
            inventory_category_id: this.form.inventory_category_id,
            name: this.form.name,
            sku: this.form.sku || null,
            unit: this.form.unit,
            quantity: this.form.quantity === "" ? 0 : this.form.quantity,
            low_stock_threshold: this.form.low_stock_threshold === "" ? 0 : this.form.low_stock_threshold,
            cost_price: this.form.cost_price === "" ? null : this.form.cost_price,
            selling_price: this.form.selling_price === "" ? null : this.form.selling_price,
            status: this.form.status,
            notes: this.form.notes || null,
            last_restocked_at: this.form.last_restocked_at || null,
         };

         var request = this.modalMode === "edit" ? axios.put(`/panel/inventory/${this.form.id}`, payload) : axios.post("/panel/inventory", payload);

         request
            .then(() => {
               this.formModal.hide();
               this.fetchItems(this.modalMode === "add" ? 1 : this.currentPage);
            })
            .catch((err) => {
               if (err.response && err.response.status === 422) {
                  var errors = err.response.data.errors || {};
                  this.formErrors = Object.fromEntries(
                     Object.entries(errors).map(function (entry) {
                        return [entry[0], Array.isArray(entry[1]) ? entry[1][0] : entry[1]];
                     }),
                  );
               } else {
                  this.formError = "Something went wrong. Please try again.";
               }
            })
            .finally(() => {
               this.submitting = false;
            });
      },
   },
   computed: {
      hasActiveFilters: function () {
         return !!(this.search || this.selectedCategory || this.selectedStatus || this.selectedStockState);
      },
      statCards: function () {
         return [
            { label: "Total Items", value: this.stats.total, icon: "bi-box-seam-fill", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Active", value: this.stats.active, icon: "bi-check-circle-fill", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Low Stock", value: this.stats.low_stock, icon: "bi-exclamation-circle-fill", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Out of Stock", value: this.stats.out_of_stock, icon: "bi-x-octagon-fill", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },
};
</script>

<style scoped>
.inventory-avatar {
   width: 2.5rem;
   height: 2.5rem;
   border-radius: 0.9rem;
   display: inline-flex;
   align-items: center;
   justify-content: center;
   background: rgba(220, 53, 69, 0.12);
   color: #dc3545;
   flex-shrink: 0;
}
</style>
