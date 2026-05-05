<template>
   <div class="inventory-page">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
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

      <div class="panel-card mb-3 p-3">
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

         <div v-if="hasActiveFilters" class="d-flex flex-wrap align-items-center gap-1 mt-3 pt-3 border-top">
            <span class="text-muted small me-1">Active filters:</span>
            <span v-for="chip in activeFilterChips" :key="chip.key" class="filter-chip">
               <span class="filter-chip-label">{{ chip.label }}:</span>
               <span class="filter-chip-value">{{ chip.value }}</span>
               <button type="button" class="filter-chip-remove" @click="clearFilter(chip.key)" :aria-label="'Remove ' + chip.label">
                  <i class="bi bi-x-lg"></i>
               </button>
            </span>
            <button type="button" class="btn btn-link btn-sm text-danger px-2 py-0 ms-1" @click="clearAllFilters">Clear all</button>
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
                        <td>
                           <div class="skeleton-box mb-2" style="width: 110px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 140px; height: 6px; border-radius: 999px"></div>
                        </td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 95px; height: 13px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 125px; height: 11px; border-radius: 4px"></div>
                        </td>
                        <td>
                           <div class="skeleton-box mb-1" style="width: 85px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 100px; height: 12px; border-radius: 4px"></div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end">
                              <div class="skeleton-box" style="width: 32px; height: 32px; border-radius: 6px"></div>
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
            <div class="empty-illustration mx-auto mb-3">
               <i class="bi" :class="hasActiveFilters ? 'bi-funnel' : 'bi-box-seam'"></i>
            </div>
            <p class="mt-2 mb-1 fw-semibold text-body">{{ hasActiveFilters ? "No items match your filters" : "No inventory items yet" }}</p>
            <p class="small mb-3">{{ hasActiveFilters ? "Try removing one or more filters above." : "Add your first item to start tracking stock and pricing." }}</p>
            <button class="btn btn-outline-secondary btn-sm" @click="clearAllFilters" v-if="hasActiveFilters">
               <i class="bi bi-x-lg me-1"></i>Clear filters
            </button>
            <button class="btn btn-danger btn-sm" @click="openAddModal" v-else>
               <i class="bi bi-plus-lg me-1"></i>Add first item
            </button>
         </div>

         <div v-else>
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>Item</th>
                        <th style="min-width: 200px">Stock</th>
                        <th>Pricing</th>
                        <th>Updated</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="item in items" :key="item.id" :class="rowStateClass(item)">
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="inventory-avatar" :class="avatarStateClass(item)">
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
                                    <span :class="['m-badge', stockBadgeClass(item)]">
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
                           <div class="fw-semibold mb-1">{{ $filters.formatQuantity(item.quantity) }} <span class="text-muted fw-normal">{{ item.unit }}</span></div>
                           <div class="stock-bar" :title="stockBarTitle(item)">
                              <div class="stock-bar-fill" :class="stockBarClass(item)" :style="{ width: stockBarWidth(item) + '%' }"></div>
                              <div class="stock-bar-marker" v-if="Number(item.low_stock_threshold) > 0" :style="{ left: thresholdMarkerPos(item) + '%' }"></div>
                           </div>
                           <div class="text-muted x-small mt-1">Low at {{ $filters.formatQuantity(item.low_stock_threshold) }} {{ item.unit }}</div>
                        </td>
                        <td class="small">
                           <div>Cost: {{ item.cost_price !== null ? `₱${$filters.formatMoney(item.cost_price)}` : "-" }}</div>
                           <div>Selling: {{ item.selling_price !== null ? `₱${$filters.formatMoney(item.selling_price)}` : "-" }}</div>
                           <div v-if="marginInfo(item)" class="margin-pill mt-1" :class="marginInfo(item).cls">
                              {{ marginInfo(item).label }}
                           </div>
                        </td>
                        <td class="small text-muted">
                           <div>{{ formatDateTime(item.updated_at) }}</div>
                           <div v-if="item.last_restocked_at">Restocked {{ formatDate(item.last_restocked_at) }}</div>
                           <div v-else>Never restocked</div>
                        </td>
                        <td>
                           <div class="d-flex gap-1 justify-content-end">
                              <button class="btn btn-sm btn-outline-success" @click="openRestockModal(item)" title="Restock">
                                 <i class="bi bi-plus-lg tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-secondary" @click="openEditModal(item)" title="Edit">
                                 <i class="bi bi-pencil tbl-icon"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(item)" title="Delete">
                                 <i class="bi bi-trash tbl-icon"></i>
                              </button>
                           </div>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>

            <div class="d-md-none">
               <div class="member-card" v-for="item in items" :key="'mobile-' + item.id" :class="rowStateClass(item)">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="inventory-avatar" :class="avatarStateClass(item)">
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
                              <a class="dropdown-item" href="#" @click.prevent="openRestockModal(item)"><i class="bi bi-plus-lg me-2"></i>Restock</a>
                           </li>
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
                     <span :class="['m-badge', stockBadgeClass(item)]">
                        <template v-if="item.is_out_of_stock">Out of Stock</template>
                        <template v-else-if="item.is_low_stock">Low Stock</template>
                        <template v-else>In Stock</template>
                     </span>
                     <span :class="['m-badge', $filters.statusBadge(item.status)]">{{ $filters.capitalize(item.status) }}</span>
                     <span class="m-badge m-badge--plan" v-if="item.category">{{ item.category.name }}</span>
                  </div>
                  <div class="mb-2">
                     <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <div class="fw-semibold small">{{ $filters.formatQuantity(item.quantity) }} <span class="text-muted fw-normal">{{ item.unit }}</span></div>
                        <div class="text-muted x-small">Low at {{ $filters.formatQuantity(item.low_stock_threshold) }}</div>
                     </div>
                     <div class="stock-bar">
                        <div class="stock-bar-fill" :class="stockBarClass(item)" :style="{ width: stockBarWidth(item) + '%' }"></div>
                        <div class="stock-bar-marker" v-if="Number(item.low_stock_threshold) > 0" :style="{ left: thresholdMarkerPos(item) + '%' }"></div>
                     </div>
                  </div>
                  <div class="small text-muted mb-2">
                     <div v-if="item.sku">SKU: {{ item.sku }}</div>
                     <div>Cost: {{ item.cost_price !== null ? `₱${$filters.formatMoney(item.cost_price)}` : "-" }}</div>
                     <div>Selling: {{ item.selling_price !== null ? `₱${$filters.formatMoney(item.selling_price)}` : "-" }}</div>
                     <div v-if="marginInfo(item)" class="margin-pill mt-1 d-inline-block" :class="marginInfo(item).cls">
                        {{ marginInfo(item).label }}
                     </div>
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

                  <div class="form-section-title">Item Details</div>
                  <div class="row g-3 mb-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.name" :class="{ 'is-invalid': formErrors.name }" />
                        <div class="invalid-feedback" v-if="formErrors.name">{{ formErrors.name }}</div>
                     </div>
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">SKU</label>
                        <input type="text" class="form-control" v-model="form.sku" :class="{ 'is-invalid': formErrors.sku }" />
                        <div class="invalid-feedback" v-if="formErrors.sku">{{ formErrors.sku }}</div>
                     </div>
                     <div class="col-md-3">
                        <label class="form-label form-label-sm">Category <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.inventory_category_id" :class="{ 'is-invalid': formErrors.inventory_category_id }">
                           <option value="" disabled>Select category</option>
                           <option v-for="category in categoriesData" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.inventory_category_id">{{ formErrors.inventory_category_id }}</div>
                     </div>
                  </div>

                  <div class="form-section-title">Stock</div>
                  <div class="row g-3 mb-3">
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Unit <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="form.unit" :class="{ 'is-invalid': formErrors.unit }" placeholder="pcs, box, bottle" />
                        <div class="invalid-feedback" v-if="formErrors.unit">{{ formErrors.unit }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="form.quantity" :class="{ 'is-invalid': formErrors.quantity }" />
                        <div class="invalid-feedback" v-if="formErrors.quantity">{{ formErrors.quantity }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Low Stock Threshold <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" v-model="form.low_stock_threshold" :class="{ 'is-invalid': formErrors.low_stock_threshold }" />
                        <div class="invalid-feedback" v-if="formErrors.low_stock_threshold">{{ formErrors.low_stock_threshold }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Last Restocked At</label>
                        <input type="datetime-local" class="form-control" v-model="form.last_restocked_at" :class="{ 'is-invalid': formErrors.last_restocked_at }" />
                        <div class="invalid-feedback" v-if="formErrors.last_restocked_at">{{ formErrors.last_restocked_at }}</div>
                     </div>
                  </div>

                  <div class="form-section-title">Pricing</div>
                  <div class="row g-3 mb-3 align-items-end">
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Cost Price</label>
                        <div class="input-group">
                           <span class="input-group-text">₱</span>
                           <input type="number" min="0" step="0.01" class="form-control" v-model="form.cost_price" :class="{ 'is-invalid': formErrors.cost_price }" />
                        </div>
                        <div class="invalid-feedback d-block" v-if="formErrors.cost_price">{{ formErrors.cost_price }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Selling Price</label>
                        <div class="input-group">
                           <span class="input-group-text">₱</span>
                           <input type="number" min="0" step="0.01" class="form-control" v-model="form.selling_price" :class="{ 'is-invalid': formErrors.selling_price }" />
                        </div>
                        <div class="invalid-feedback d-block" v-if="formErrors.selling_price">{{ formErrors.selling_price }}</div>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Profit Margin</label>
                        <div class="margin-display" :class="formMarginInfo ? formMarginInfo.cls : 'margin-display--empty'">
                           <i class="bi" :class="formMarginInfo ? 'bi-graph-up-arrow' : 'bi-dash'"></i>
                           <span class="ms-1">{{ formMarginInfo ? formMarginInfo.label : "Enter cost & selling" }}</span>
                        </div>
                     </div>
                  </div>

                  <div class="form-section-title">Status & Notes</div>
                  <div class="row g-3">
                     <div class="col-md-4">
                        <label class="form-label form-label-sm">Status <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.status" :class="{ 'is-invalid': formErrors.status }">
                           <option value="active">Active</option>
                           <option value="inactive">Inactive</option>
                        </select>
                        <div class="invalid-feedback" v-if="formErrors.status">{{ formErrors.status }}</div>
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <textarea rows="3" class="form-control" v-model="form.notes" :class="{ 'is-invalid': formErrors.notes }" placeholder="Optional notes about this item"></textarea>
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

      <div class="modal fade" id="inventoryRestockModal" tabindex="-1" ref="inventoryRestockModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-2 text-success"></i>Restock Item</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body p-4" v-if="restockTarget">
                  <div v-if="restockError" class="alert alert-danger py-2 small mb-3">{{ restockError }}</div>
                  <div class="restock-summary mb-3">
                     <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="inventory-avatar inventory-avatar--sm">
                           <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <div>
                           <div class="fw-semibold">{{ restockTarget.name }}</div>
                           <div class="text-muted x-small">{{ restockTarget.category?.name || "Uncategorized" }}<span v-if="restockTarget.sku"> • {{ restockTarget.sku }}</span></div>
                        </div>
                     </div>
                     <div class="restock-stats">
                        <div>
                           <div class="restock-stat-label">Current</div>
                           <div class="restock-stat-value">{{ $filters.formatQuantity(restockTarget.quantity) }} <span class="text-muted small">{{ restockTarget.unit }}</span></div>
                        </div>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <div>
                           <div class="restock-stat-label">After Restock</div>
                           <div class="restock-stat-value text-success">{{ $filters.formatQuantity(restockProjected) }} <span class="text-muted small">{{ restockTarget.unit }}</span></div>
                        </div>
                     </div>
                  </div>

                  <label class="form-label form-label-sm">Add Quantity <span class="text-danger">*</span></label>
                  <div class="input-group">
                     <input ref="restockInput" type="number" min="0.01" step="0.01" class="form-control" v-model="restockAmount" />
                     <span class="input-group-text">{{ restockTarget.unit }}</span>
                  </div>
                  <div class="text-muted x-small mt-1">Last restocked at will be set to now.</div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-success px-4" @click="submitRestock" :disabled="restocking || !restockAmountValid">
                     <span v-if="restocking" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     <i v-else class="bi bi-check-lg me-1"></i>
                     Confirm Restock
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
                  <p class="text-muted small mt-2 mb-0">You can restore this item later from System Activity.</p>
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
import { formatDate, formatDateTime, toDateTimeInputValue } from "../../dates";

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
         restocking: false,
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
         restockModal: null,
         restockTarget: null,
         restockAmount: "",
         restockError: "",
      };
   },
   mounted: function () {
      this.formModal = new Modal(this.$refs.inventoryFormModal);
      this.deleteModal = new Modal(this.$refs.inventoryDeleteModal);
      this.restockModal = new Modal(this.$refs.inventoryRestockModal);
      this.fetchItems();
   },
   methods: {
      formatDate,
      formatDateTime,
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
      clearFilter: function (key) {
         if (key === "search") this.search = "";
         if (key === "category") this.selectedCategory = "";
         if (key === "status") this.selectedStatus = "";
         if (key === "stock") this.selectedStockState = "";
         this.fetchItems(1);
      },
      clearAllFilters: function () {
         this.search = "";
         this.selectedCategory = "";
         this.selectedStatus = "";
         this.selectedStockState = "";
         this.fetchItems(1);
      },
      stockBadgeClass: function (item) {
         if (item.is_out_of_stock) return "m-badge--suspended";
         if (item.is_low_stock) return "m-badge--pending";
         return "m-badge--active";
      },
      rowStateClass: function (item) {
         if (item.is_out_of_stock) return "row-state-danger";
         if (item.is_low_stock) return "row-state-warning";
         return "";
      },
      avatarStateClass: function (item) {
         if (item.is_out_of_stock) return "inventory-avatar--danger";
         if (item.is_low_stock) return "inventory-avatar--warning";
         return "";
      },
      stockBarClass: function (item) {
         if (item.is_out_of_stock) return "stock-bar-fill--danger";
         if (item.is_low_stock) return "stock-bar-fill--warning";
         return "stock-bar-fill--success";
      },
      stockBarMax: function (item) {
         var qty = Number(item.quantity) || 0;
         var threshold = Number(item.low_stock_threshold) || 0;
         var max = Math.max(threshold * 2, qty, 1);
         return max;
      },
      stockBarWidth: function (item) {
         var qty = Number(item.quantity) || 0;
         if (qty <= 0) return 0;
         var pct = (qty / this.stockBarMax(item)) * 100;
         return Math.max(2, Math.min(100, pct));
      },
      thresholdMarkerPos: function (item) {
         var threshold = Number(item.low_stock_threshold) || 0;
         var pct = (threshold / this.stockBarMax(item)) * 100;
         return Math.max(0, Math.min(100, pct));
      },
      stockBarTitle: function (item) {
         return `${item.quantity} ${item.unit} on hand · low at ${item.low_stock_threshold}`;
      },
      marginInfo: function (item) {
         return this.computeMargin(item.cost_price, item.selling_price);
      },
      computeMargin: function (cost, selling) {
         if (cost === null || cost === "" || selling === null || selling === "") return null;
         var c = Number(cost);
         var s = Number(selling);
         if (!isFinite(c) || !isFinite(s) || s <= 0) return null;
         var profit = s - c;
         var pct = (profit / s) * 100;
         var cls = "margin-pill--good";
         if (pct < 0) cls = "margin-pill--bad";
         else if (pct < 15) cls = "margin-pill--warn";
         var sign = profit >= 0 ? "+" : "−";
         var absProfit = Math.abs(profit).toFixed(2);
         return { label: `${sign}₱${absProfit} · ${pct.toFixed(1)}%`, cls: cls };
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
            last_restocked_at: toDateTimeInputValue(item.last_restocked_at, false),
         };
         this.formModal.show();
      },
      openRestockModal: function (item) {
         this.restockTarget = item;
         this.restockAmount = "";
         this.restockError = "";
         this.restockModal.show();
         this.$nextTick(() => {
            if (this.$refs.restockInput) this.$refs.restockInput.focus();
         });
      },
      submitRestock: function () {
         if (!this.restockTarget || !this.restockAmountValid) return;

         var item = this.restockTarget;
         var newQty = Number(item.quantity) + Number(this.restockAmount);
         var payload = {
            inventory_category_id: item.inventory_category_id || item.category?.id,
            name: item.name,
            sku: item.sku || null,
            unit: item.unit,
            quantity: newQty,
            low_stock_threshold: item.low_stock_threshold,
            cost_price: item.cost_price,
            selling_price: item.selling_price,
            status: item.status,
            notes: item.notes || null,
            last_restocked_at: new Date().toISOString().slice(0, 19),
         };

         this.restocking = true;
         this.restockError = "";

         axios
            .put(`/panel/inventory/${item.id}`, payload)
            .then(() => {
               this.restockModal.hide();
               this.restockTarget = null;
               this.fetchItems(this.currentPage);
            })
            .catch((err) => {
               if (err.response && err.response.data && err.response.data.message) {
                  this.restockError = err.response.data.message;
               } else {
                  this.restockError = "Failed to restock. Please try again.";
               }
            })
            .finally(() => {
               this.restocking = false;
            });
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
      activeFilterChips: function () {
         var chips = [];
         if (this.search) chips.push({ key: "search", label: "Search", value: this.search });
         if (this.selectedCategory) {
            var cat = this.categoriesData.find((c) => c.id === Number(this.selectedCategory) || c.id === this.selectedCategory);
            chips.push({ key: "category", label: "Category", value: cat ? cat.name : this.selectedCategory });
         }
         if (this.selectedStatus) chips.push({ key: "status", label: "Status", value: this.$filters.capitalize(this.selectedStatus) });
         if (this.selectedStockState) {
            var labels = { in_stock: "In Stock", low_stock: "Low Stock", out_of_stock: "Out of Stock" };
            chips.push({ key: "stock", label: "Stock", value: labels[this.selectedStockState] || this.selectedStockState });
         }
         return chips;
      },
      statCards: function () {
         return [
            { label: "Total Items", value: this.stats.total, icon: "bi-box-seam-fill", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Active", value: this.stats.active, icon: "bi-check-circle-fill", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Low Stock", value: this.stats.low_stock, icon: "bi-exclamation-circle-fill", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Out of Stock", value: this.stats.out_of_stock, icon: "bi-x-octagon-fill", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
      formMarginInfo: function () {
         return this.computeMargin(this.form.cost_price, this.form.selling_price);
      },
      restockProjected: function () {
         if (!this.restockTarget) return 0;
         var add = Number(this.restockAmount) || 0;
         return Number(this.restockTarget.quantity) + add;
      },
      restockAmountValid: function () {
         var n = Number(this.restockAmount);
         return isFinite(n) && n > 0;
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
.inventory-avatar--sm {
   width: 2rem;
   height: 2rem;
   border-radius: 0.65rem;
   font-size: 0.85rem;
}
.inventory-avatar--warning {
   background: rgba(255, 193, 7, 0.16);
   color: #d39e00;
}
.inventory-avatar--danger {
   background: rgba(220, 53, 69, 0.18);
   color: #c8102e;
}

.row-state-warning td:first-child,
.row-state-warning.member-card {
   box-shadow: inset 3px 0 0 rgba(255, 193, 7, 0.7);
}
.row-state-danger td:first-child,
.row-state-danger.member-card {
   box-shadow: inset 3px 0 0 rgba(220, 53, 69, 0.85);
}

.stock-bar {
   position: relative;
   width: 100%;
   max-width: 220px;
   height: 6px;
   background: rgba(0, 0, 0, 0.06);
   border-radius: 999px;
   overflow: visible;
}
.stock-bar-fill {
   height: 100%;
   border-radius: 999px;
   transition: width 0.25s ease;
}
.stock-bar-fill--success {
   background: #198754;
}
.stock-bar-fill--warning {
   background: #f0ad4e;
}
.stock-bar-fill--danger {
   background: #dc3545;
}
.stock-bar-marker {
   position: absolute;
   top: -2px;
   width: 2px;
   height: 10px;
   background: rgba(0, 0, 0, 0.45);
   border-radius: 1px;
   pointer-events: none;
}

.x-small {
   font-size: 0.72rem;
   line-height: 1.1;
}

.margin-pill {
   display: inline-flex;
   align-items: center;
   font-size: 0.7rem;
   font-weight: 600;
   padding: 2px 8px;
   border-radius: 999px;
   line-height: 1.4;
}
.margin-pill--good {
   background: rgba(25, 135, 84, 0.12);
   color: #146c43;
}
.margin-pill--warn {
   background: rgba(240, 173, 78, 0.18);
   color: #b07a13;
}
.margin-pill--bad {
   background: rgba(220, 53, 69, 0.14);
   color: #b02a37;
}

.filter-chip {
   display: inline-flex;
   align-items: center;
   gap: 6px;
   padding: 4px 4px 4px 10px;
   background: rgba(13, 110, 253, 0.08);
   color: #084298;
   border-radius: 999px;
   font-size: 0.75rem;
   font-weight: 500;
}
.filter-chip-label {
   color: rgba(0, 0, 0, 0.55);
   font-weight: 500;
}
.filter-chip-value {
   font-weight: 600;
}
.filter-chip-remove {
   border: 0;
   background: transparent;
   color: inherit;
   width: 18px;
   height: 18px;
   border-radius: 50%;
   display: inline-flex;
   align-items: center;
   justify-content: center;
   font-size: 0.65rem;
   line-height: 1;
   cursor: pointer;
   transition: background 0.15s ease;
}
.filter-chip-remove:hover {
   background: rgba(0, 0, 0, 0.12);
}

.form-section-title {
   font-size: 0.7rem;
   font-weight: 700;
   text-transform: uppercase;
   letter-spacing: 1px;
   color: rgba(0, 0, 0, 0.5);
   margin-bottom: 0.5rem;
   padding-bottom: 0.35rem;
   border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.margin-display {
   display: flex;
   align-items: center;
   padding: 0.45rem 0.75rem;
   border-radius: 0.4rem;
   font-size: 0.85rem;
   font-weight: 600;
   min-height: calc(2.25rem + 2px);
}
.margin-display.margin-pill--good {
   background: rgba(25, 135, 84, 0.12);
   color: #146c43;
}
.margin-display.margin-pill--warn {
   background: rgba(240, 173, 78, 0.18);
   color: #b07a13;
}
.margin-display.margin-pill--bad {
   background: rgba(220, 53, 69, 0.14);
   color: #b02a37;
}
.margin-display--empty {
   background: rgba(0, 0, 0, 0.04);
   color: rgba(0, 0, 0, 0.45);
   font-weight: 500;
}

.empty-illustration {
   width: 64px;
   height: 64px;
   border-radius: 50%;
   background: rgba(220, 53, 69, 0.08);
   display: flex;
   align-items: center;
   justify-content: center;
   color: #dc3545;
   font-size: 1.6rem;
}

.restock-summary {
   background: rgba(0, 0, 0, 0.025);
   border: 1px solid rgba(0, 0, 0, 0.06);
   border-radius: 0.6rem;
   padding: 0.85rem;
}
.restock-stats {
   display: flex;
   align-items: center;
   gap: 1rem;
   padding-top: 0.5rem;
   margin-top: 0.5rem;
   border-top: 1px dashed rgba(0, 0, 0, 0.08);
}
.restock-stats > div {
   flex: 1;
}
.restock-stat-label {
   font-size: 0.68rem;
   text-transform: uppercase;
   letter-spacing: 1px;
   color: rgba(0, 0, 0, 0.5);
   font-weight: 600;
}
.restock-stat-value {
   font-size: 1.05rem;
   font-weight: 700;
}

[data-bs-theme="dark"] .stock-bar {
   background: rgba(255, 255, 255, 0.08);
}
[data-bs-theme="dark"] .stock-bar-marker {
   background: rgba(255, 255, 255, 0.55);
}
[data-bs-theme="dark"] .filter-chip {
   background: rgba(13, 110, 253, 0.18);
   color: #9ec5fe;
}
[data-bs-theme="dark"] .filter-chip-label {
   color: rgba(255, 255, 255, 0.55);
}
[data-bs-theme="dark"] .filter-chip-remove:hover {
   background: rgba(255, 255, 255, 0.12);
}
[data-bs-theme="dark"] .form-section-title {
   color: rgba(255, 255, 255, 0.5);
   border-bottom-color: rgba(255, 255, 255, 0.08);
}
[data-bs-theme="dark"] .margin-display--empty {
   background: rgba(255, 255, 255, 0.06);
   color: rgba(255, 255, 255, 0.45);
}
[data-bs-theme="dark"] .restock-summary {
   background: rgba(255, 255, 255, 0.03);
   border-color: rgba(255, 255, 255, 0.08);
}
[data-bs-theme="dark"] .restock-stats {
   border-top-color: rgba(255, 255, 255, 0.1);
}
[data-bs-theme="dark"] .restock-stat-label {
   color: rgba(255, 255, 255, 0.5);
}
[data-bs-theme="dark"] .empty-illustration {
   background: rgba(220, 53, 69, 0.16);
}
</style>
