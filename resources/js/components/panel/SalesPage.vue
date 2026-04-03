<template>
   <div class="sales-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Sales</h4>
            <p class="text-muted small mb-0">Sell products, memberships, PT packages, and walk-in access from one branch-aware POS flow.</p>
         </div>
      </div>

      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div v-if="successMessage" class="alert alert-success py-2 small mb-3">
         <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>{{ successMessage }}</div>
            <div class="d-flex gap-2" v-if="lastCompletedSale && lastCompletedSale.receipt_url">
               <a class="btn btn-sm btn-danger" :href="lastCompletedSale.receipt_url" target="_blank" rel="noopener">
                  <i class="bi bi-printer me-1"></i>
                  Download Receipt
               </a>
            </div>
         </div>
      </div>

      <div v-if="!branchesData.length" class="panel-card p-5 text-center text-muted">
         <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>
         <div>No accessible branches found.</div>
      </div>

      <div v-else-if="!selectedBranch" class="panel-card p-5 text-center text-muted">
         <i class="bi bi-diagram-3 fs-1 d-block mb-2 opacity-25"></i>
         <div class="fw-semibold mb-1">Select a branch</div>
         <div class="small">Choose a specific branch from the sidebar before processing a sale.</div>
      </div>

      <template v-else>
         <div class="row g-4">
            <div class="col-12 col-xl-8">
               <div class="panel-card">
                  <div class="panel-card-header">
                     <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 w-100">
                        <div>
                           <span class="panel-card-title">New Sale</span>
                           <div class="small text-muted mt-1">Processing sale for {{ currentBranchName }}</div>
                        </div>
                        <div class="btn-group btn-group-sm ms-md-auto sales-type-group" role="group">
                           <button type="button" :class="['btn', saleType === 'inventory' ? 'btn-danger' : 'btn-outline-secondary']" @click="setSaleType('inventory')">Inventory</button>
                           <button type="button" :class="['btn', saleType === 'membership' ? 'btn-danger' : 'btn-outline-secondary']" @click="setSaleType('membership')">Membership</button>
                           <button type="button" :class="['btn', saleType === 'pt_package' ? 'btn-danger' : 'btn-outline-secondary']" @click="setSaleType('pt_package')">PT Package</button>
                           <button type="button" :class="['btn', saleType === 'walk_in' ? 'btn-danger' : 'btn-outline-secondary']" @click="setSaleType('walk_in')">Walk-in</button>
                        </div>
                     </div>
                  </div>

                  <div class="p-3 p-md-4">
                     <div v-if="loadingContext">
                        <div class="row g-3">
                           <div class="col-12" v-for="index in 6" :key="'context-sk-' + index">
                              <div class="skeleton-box" style="width: 100%; height: 44px; border-radius: 10px"></div>
                           </div>
                        </div>
                     </div>

                     <template v-else>
                        <div v-if="saleType === 'inventory'">
                           <div class="d-flex justify-content-between align-items-center mb-3">
                              <div>
                                 <div class="fw-semibold">Inventory Items</div>
                                 <div class="small text-muted">Add one or more products to this sale.</div>
                              </div>
                              <button type="button" class="btn btn-sm btn-outline-secondary" @click="addInventoryLine">
                                 <i class="bi bi-plus-lg me-1"></i>
                                 Add Item
                              </button>
                           </div>

                           <div class="vstack gap-3">
                              <div class="border rounded-3 p-3" v-for="(line, index) in form.inventory_lines" :key="line.key">
                                 <div class="row g-3 align-items-end">
                                    <div class="col-12 col-lg-6">
                                       <label class="form-label">Product <span class="text-danger">*</span></label>
                                       <async-search-select v-model="line.inventory_item_id" :selected-label="line.inventory_item_label" placeholder="Select inventory product" search-placeholder="Search inventory product..." :fetch-options="inventoryFetchOptions(line.key)" :invalid="!!inventoryLineError(index, 'inventory_item_id')" :min-chars="1" @select-option="handleInventoryItemSelect(line, $event)"></async-search-select>
                                       <div class="invalid-feedback d-block" v-if="inventoryLineError(index, 'inventory_item_id')">{{ inventoryLineError(index, "inventory_item_id") }}</div>
                                    </div>
                                    <div class="col-6 col-lg-2">
                                       <label class="form-label">Qty <span class="text-danger">*</span></label>
                                       <input type="number" min="1" step="1" class="form-control" v-model="line.quantity" :class="{ 'is-invalid': inventoryLineError(index, 'quantity') }" />
                                       <div class="invalid-feedback">{{ inventoryLineError(index, "quantity") }}</div>
                                    </div>
                                    <div class="col-6 col-lg-2">
                                       <label class="form-label">Line Total</label>
                                       <div class="form-control bg-light fw-semibold">₱{{ $filters.formatMoney(inventoryLineTotal(line)) }}</div>
                                    </div>
                                    <div class="col-12 col-lg-2 d-flex justify-content-lg-end">
                                       <button type="button" class="btn btn-outline-danger w-100 w-lg-auto d-flex align-items-center justify-content-center" @click="removeInventoryLine(line)" :disabled="form.inventory_lines.length === 1"><i class="bi bi-trash me-1"></i>Remove</button>
                                    </div>
                                 </div>

                                 <div class="small text-muted mt-2" v-if="findInventoryItem(line.inventory_item_id)">
                                    Available: {{ $filters.formatQuantity(findInventoryItem(line.inventory_item_id).quantity) }} {{ findInventoryItem(line.inventory_item_id).unit }}
                                    <span class="ms-2">Remaining after sale: {{ $filters.formatQuantity((parseFloat(findInventoryItem(line.inventory_item_id).quantity) || 0) - (parseFloat(line.quantity) || 0)) }} {{ findInventoryItem(line.inventory_item_id).unit }}</span>
                                 </div>
                              </div>
                           </div>

                           <div class="invalid-feedback d-block mt-2" v-if="formErrors.items">{{ formErrors.items }}</div>

                           <div class="row g-3 mt-1">
                              <div class="col-12">
                                 <label class="form-label">Notes</label>
                                 <input type="text" class="form-control" v-model="form.notes" :class="{ 'is-invalid': formErrors.notes }" placeholder="Optional cashier or sale note" />
                                 <div class="invalid-feedback">{{ formErrors.notes }}</div>
                              </div>
                           </div>
                        </div>

                        <template v-if="saleType === 'membership' || saleType === 'pt_package'">
                           <div class="border rounded-3 p-3 mb-3">
                              <div class="fw-semibold mb-3">Member</div>
                              <div class="row g-3">
                                 <div class="col-12">
                                    <div class="btn-group btn-group-sm" role="group">
                                       <button type="button" :class="['btn', form.member_mode === 'existing' ? 'btn-danger' : 'btn-outline-secondary']" @click="setMemberMode('existing')">Existing Member</button>
                                       <button type="button" :class="['btn', form.member_mode === 'new' ? 'btn-danger' : 'btn-outline-secondary']" @click="setMemberMode('new')">New Member</button>
                                    </div>
                                    <div class="invalid-feedback d-block" v-if="formErrors.member_mode">{{ formErrors.member_mode }}</div>
                                 </div>

                                 <div class="col-12" v-if="form.member_mode === 'existing'">
                                    <label class="form-label">Search Member <span class="text-danger">*</span></label>
                                    <async-search-select v-model="form.member_id" :selected-label="form.member_label" placeholder="Select member" search-placeholder="Search by name, email, or phone" :fetch-options="fetchMemberOptions" :invalid="!!formErrors.member_id" @select-option="handleMemberSelect"></async-search-select>
                                    <div class="form-text small">Search by member name, email, or phone number.</div>
                                    <div class="invalid-feedback d-block">{{ formErrors.member_id }}</div>
                                 </div>

                                 <template v-else>
                                    <div class="col-12 col-md-6">
                                       <label class="form-label">Member Name <span class="text-danger">*</span></label>
                                       <input type="text" class="form-control" v-model="form.customer_name" :class="{ 'is-invalid': formErrors.customer_name }" />
                                       <div class="invalid-feedback">{{ formErrors.customer_name }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                       <label class="form-label">Phone</label>
                                       <input type="text" class="form-control" v-model="form.customer_phone" :class="{ 'is-invalid': formErrors.customer_phone }" />
                                       <div class="invalid-feedback">{{ formErrors.customer_phone }}</div>
                                    </div>
                                    <div class="col-12">
                                       <label class="form-label">Email</label>
                                       <input type="email" class="form-control" v-model="form.customer_email" :class="{ 'is-invalid': formErrors.customer_email }" />
                                       <div class="invalid-feedback">{{ formErrors.customer_email }}</div>
                                    </div>
                                 </template>
                              </div>
                           </div>
                        </template>

                        <div v-if="saleType === 'membership'" class="row g-3">
                           <div class="col-12">
                              <label class="form-label">Membership Plan <span class="text-danger">*</span></label>
                              <select class="form-select" v-model="form.membership_rate_plan_id" :class="{ 'is-invalid': formErrors.rate_plan_id }">
                                 <option value="">Select membership plan</option>
                                 <option v-for="plan in context.membership_rates" :key="plan.id" :value="plan.id">{{ plan.name }} · ₱{{ $filters.formatMoney(plan.price) }}</option>
                              </select>
                              <div class="invalid-feedback">{{ formErrors.rate_plan_id }}</div>
                           </div>
                           <div class="col-12" v-if="selectedMembershipPlan">
                              <div class="rounded-3 bg-light border p-3">
                                 <div class="fw-semibold">{{ selectedMembershipPlan.name }}</div>
                                 <div class="small text-muted mt-1">{{ selectedMembershipPlan.description || "Membership plan" }}</div>
                                 <div class="small mt-2"><strong>Price:</strong> ₱{{ $filters.formatMoney(selectedMembershipPlan.price) }}</div>
                                 <div class="small"><strong>Duration:</strong> {{ selectedMembershipPlan.duration_days }} days</div>
                                 <div class="small"><strong>Manager Commission:</strong> {{ $filters.formatMoney(selectedMembershipPlan.manager_commission_rate || 0) }}%</div>
                              </div>
                           </div>
                           <div class="col-12 col-md-6">
                              <label class="form-label">Start Date <span class="text-danger">*</span></label>
                              <input type="date" class="form-control" v-model="form.start_date" :class="{ 'is-invalid': formErrors.start_date }" />
                              <div class="invalid-feedback">{{ formErrors.start_date }}</div>
                           </div>
                           <div class="col-12 col-md-6">
                              <label class="form-label">Notes</label>
                              <input type="text" class="form-control" v-model="form.notes" :class="{ 'is-invalid': formErrors.notes }" placeholder="Optional membership note" />
                              <div class="invalid-feedback">{{ formErrors.notes }}</div>
                           </div>
                        </div>

                        <div v-if="saleType === 'pt_package'" class="row g-3">
                           <div class="col-12">
                              <label class="form-label">PT Package <span class="text-danger">*</span></label>
                              <select class="form-select" v-model="form.pt_product_id" :class="{ 'is-invalid': formErrors.pt_product_id }">
                                 <option value="">Select PT package</option>
                                 <option v-for="ptRate in context.pt_rates" :key="ptRate.id" :value="ptRate.id">{{ ptRate.name }} · {{ ptRate.session_count }} sessions · ₱{{ $filters.formatMoney(ptRate.price) }}</option>
                              </select>
                              <div class="invalid-feedback">{{ formErrors.pt_product_id }}</div>
                           </div>
                           <div class="col-12" v-if="selectedPtProduct">
                              <div class="rounded-3 bg-light border p-3">
                                 <div class="fw-semibold">{{ selectedPtProduct.name }}</div>
                                 <div class="small text-muted mt-1">{{ selectedPtProduct.description || "PT package" }}</div>
                                 <div class="small mt-2"><strong>Price:</strong> ₱{{ $filters.formatMoney(selectedPtProduct.price) }}</div>
                                 <div class="small"><strong>Sessions:</strong> {{ selectedPtProduct.session_count }}</div>
                              </div>
                           </div>
                           <div class="col-12 col-md-6">
                              <label class="form-label">Sale Date <span class="text-danger">*</span></label>
                              <input type="date" class="form-control" v-model="form.assigned_at" :class="{ 'is-invalid': formErrors.assigned_at }" />
                              <div class="invalid-feedback">{{ formErrors.assigned_at }}</div>
                           </div>
                           <div class="col-12 col-md-6">
                              <label class="form-label">Expires At</label>
                              <input type="date" class="form-control" v-model="form.expires_at" :class="{ 'is-invalid': formErrors.expires_at }" />
                              <div class="invalid-feedback">{{ formErrors.expires_at }}</div>
                           </div>
                           <div class="col-12">
                              <label class="form-label">Notes</label>
                              <input type="text" class="form-control" v-model="form.notes" :class="{ 'is-invalid': formErrors.notes }" placeholder="Coach assignment can be done later on the member profile" />
                              <div class="invalid-feedback">{{ formErrors.notes }}</div>
                           </div>
                        </div>

                        <div v-if="saleType === 'walk_in'" class="row g-3">
                           <div class="col-12 col-md-6">
                              <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                              <input type="text" class="form-control" v-model="form.customer_name" :class="{ 'is-invalid': formErrors.customer_name }" />
                              <div class="invalid-feedback">{{ formErrors.customer_name }}</div>
                           </div>
                           <div class="col-12 col-md-6">
                              <label class="form-label">Phone</label>
                              <input type="text" class="form-control" v-model="form.customer_phone" :class="{ 'is-invalid': formErrors.customer_phone }" />
                              <div class="invalid-feedback">{{ formErrors.customer_phone }}</div>
                           </div>
                           <div class="col-12 col-md-6">
                              <label class="form-label">Walk-in Plan</label>
                              <select class="form-select" v-model="form.walk_in_rate_plan_id" @change="syncWalkInAmount">
                                 <option value="">Custom walk-in payment</option>
                                 <option v-for="plan in context.membership_rates" :key="'walkin-' + plan.id" :value="plan.id">{{ plan.name }} · ₱{{ $filters.formatMoney(plan.price) }}</option>
                              </select>
                           </div>
                           <div class="col-12 col-md-6">
                              <label class="form-label">Amount Paid <span class="text-danger">*</span></label>
                              <input type="number" min="0" step="0.01" class="form-control" v-model="form.amount_paid" :class="{ 'is-invalid': formErrors.amount_paid }" />
                              <div class="invalid-feedback">{{ formErrors.amount_paid }}</div>
                           </div>
                           <div class="col-12" v-if="selectedWalkInPlan">
                              <div class="rounded-3 bg-light border p-3">
                                 <div class="fw-semibold">{{ selectedWalkInPlan.name }}</div>
                                 <div class="small text-muted mt-1">{{ selectedWalkInPlan.description || "Walk-in plan" }}</div>
                                 <div class="small mt-2"><strong>Price:</strong> ₱{{ $filters.formatMoney(selectedWalkInPlan.price) }}</div>
                              </div>
                           </div>
                           <div class="col-12">
                              <label class="form-label">Notes</label>
                              <input type="text" class="form-control" v-model="form.notes" :class="{ 'is-invalid': formErrors.notes }" placeholder="Optional walk-in note" />
                              <div class="invalid-feedback">{{ formErrors.notes }}</div>
                           </div>
                        </div>

                        <div class="border-top mt-4 pt-3">
                           <div class="row g-3">
                              <div class="col-12 col-md-4">
                                 <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                 <select class="form-select" v-model="form.payment_method" :class="{ 'is-invalid': formErrors.payment_method }">
                                    <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                                 </select>
                                 <div class="invalid-feedback">{{ formErrors.payment_method }}</div>
                              </div>
                              <div class="col-12 col-md-4">
                                 <label class="form-label">Amount Received <span class="text-danger">*</span></label>
                                 <input type="number" min="0" step="0.01" class="form-control" v-model="form.amount_received" :class="{ 'is-invalid': formErrors.amount_received }" />
                                 <div class="invalid-feedback">{{ formErrors.amount_received }}</div>
                              </div>
                              <div class="col-12 col-md-4">
                                 <label class="form-label">Sold At <span class="text-danger">*</span></label>
                                 <input type="datetime-local" class="form-control" v-model="form.sold_at" :class="{ 'is-invalid': formErrors.sold_at }" />
                                 <div class="invalid-feedback">{{ formErrors.sold_at }}</div>
                              </div>
                              <div class="col-12" v-if="requiresPaymentReference">
                                 <label class="form-label">Payment Reference</label>
                                 <input type="text" class="form-control" v-model="form.payment_reference" :class="{ 'is-invalid': formErrors.payment_reference }" :placeholder="paymentReferencePlaceholder" />
                                 <div class="invalid-feedback">{{ formErrors.payment_reference }}</div>
                              </div>
                           </div>
                        </div>
                     </template>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-4">
               <div class="panel-card">
                  <div class="panel-card-header">
                     <span class="panel-card-title">Sale Summary</span>
                  </div>
                  <div class="p-3 p-md-4">
                     <div class="small text-muted mb-3">Review the sale details before confirming the transaction.</div>

                     <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span class="text-muted small">Type</span>
                        <span class="fw-semibold">{{ $filters.capitalize(saleType) }}</span>
                     </div>
                     <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span class="text-muted small">Customer</span>
                        <span class="fw-semibold text-end">{{ summaryCustomer || "—" }}</span>
                     </div>
                     <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span class="text-muted small">Item</span>
                        <span class="fw-semibold text-end">{{ summaryItem || "—" }}</span>
                     </div>
                     <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <span class="text-muted small">Payment</span>
                        <span class="fw-semibold">{{ currentPaymentMethodLabel }}</span>
                     </div>
                     <div class="d-flex justify-content-between align-items-center pb-2 mb-3">
                        <span class="text-muted small">Sold At</span>
                        <span class="fw-semibold text-end">{{ $filters.formatDateTime(form.sold_at) }}</span>
                     </div>

                     <div v-if="saleType === 'inventory' && normalizedInventoryLines.length" class="rounded-3 border mb-3 overflow-hidden">
                        <table class="table table-sm align-middle mb-0">
                           <thead class="table-light">
                              <tr>
                                 <th>Item</th>
                                 <th class="text-end">Qty</th>
                                 <th class="text-end">Line Total</th>
                              </tr>
                           </thead>
                           <tbody>
                              <tr v-for="line in normalizedInventoryLines" :key="'summary-' + line.key">
                                 <td>
                                    <div class="fw-semibold small">{{ line.item ? line.item.name : "Unselected Item" }}</div>
                                    <div class="small text-muted" v-if="line.item">{{ line.item.category_name || "Uncategorized" }}</div>
                                 </td>
                                 <td class="text-end small">{{ $filters.formatQuantity(line.quantity) }}</td>
                                 <td class="text-end fw-semibold small">₱{{ $filters.formatMoney(line.line_total) }}</td>
                              </tr>
                           </tbody>
                        </table>
                     </div>

                     <div v-else-if="saleType === 'membership' && selectedMembershipPlan" class="rounded-3 bg-light p-3 mb-3">
                        <div class="fw-semibold">{{ selectedMembershipPlan.name }}</div>
                        <div class="small text-muted">{{ selectedMembershipPlan.duration_days }} day membership</div>
                        <div class="small mt-2">
                           Price preview: <strong>₱{{ $filters.formatMoney(selectedMembershipPlan.price) }}</strong>
                        </div>
                     </div>

                     <div v-else-if="saleType === 'pt_package' && selectedPtProduct" class="rounded-3 bg-light p-3 mb-3">
                        <div class="fw-semibold">{{ selectedPtProduct.name }}</div>
                        <div class="small text-muted">{{ selectedPtProduct.session_count }} sessions</div>
                        <div class="small mt-2">
                           Price preview: <strong>₱{{ $filters.formatMoney(selectedPtProduct.price) }}</strong>
                        </div>
                     </div>

                     <div v-else-if="saleType === 'walk_in' && (selectedWalkInPlan || form.amount_paid)" class="rounded-3 bg-light p-3 mb-3">
                        <div class="fw-semibold">{{ selectedWalkInPlan ? selectedWalkInPlan.name : "Walk-in" }}</div>
                        <div class="small text-muted">Walk-in payment</div>
                        <div class="small mt-2">
                           Price preview: <strong>₱{{ $filters.formatMoney(summaryTotal) }}</strong>
                        </div>
                     </div>

                     <div class="rounded-3 bg-light p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                           <span>Subtotal</span>
                           <span>₱{{ $filters.formatMoney(summaryTotal) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                           <span>Amount Received</span>
                           <span>₱{{ $filters.formatMoney(amountReceivedValue) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-muted" v-if="requiresPaymentReference && form.payment_reference">
                           <span>Reference</span>
                           <span class="text-end">{{ form.payment_reference }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" v-if="isCashPayment">
                           <span class="text-muted small">Change</span>
                           <span class="fw-semibold">₱{{ $filters.formatMoney(changeDue) }}</span>
                        </div>
                     </div>

                     <div class="rounded-3 bg-light border p-3 mb-3">
                        <div class="text-muted small mb-1">Total</div>
                        <div class="fs-3 fw-bold">₱{{ $filters.formatMoney(summaryTotal) }}</div>
                     </div>

                     <button class="btn btn-danger w-100" :disabled="processingSale || loadingContext" @click="submitSale">
                        <span v-if="processingSale" class="spinner-border spinner-border-sm me-2"></span>
                        Confirm Sale
                     </button>
                  </div>
               </div>
            </div>
         </div>

         <div class="panel-card mt-4">
            <div class="panel-card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
               <div>
                  <span class="panel-card-title">Transaction History</span>
                  <div class="small text-muted mt-1">Recent transactions recorded for {{ currentBranchName }}</div>
               </div>
               <div class="small text-muted" v-if="!loadingHistory && historyPagination.total > 0">Showing {{ historyPagination.from }}–{{ historyPagination.to }} of {{ historyPagination.total }}</div>
            </div>

            <div class="p-3 border-bottom">
               <div class="row g-2">
                  <div class="col-12 col-md-5">
                     <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0">
                           <i class="bi bi-search text-muted search-icon"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" placeholder="Search customer, item, or payment..." v-model="historyFilters.search" @input="onHistorySearchInput" />
                     </div>
                  </div>
                  <div class="col-6 col-md-3">
                     <select class="form-select" v-model="historyFilters.type" @change="fetchHistory(1)">
                        <option value="">All Types</option>
                        <option value="inventory">Inventory</option>
                        <option value="membership">Membership</option>
                        <option value="pt_package">PT Package</option>
                        <option value="walk_in">Walk-in</option>
                     </select>
                  </div>
                  <div class="col-6 col-md-2">
                     <input type="date" class="form-control" v-model="historyFilters.date_from" @change="fetchHistory(1)" />
                  </div>
                  <div class="col-6 col-md-2">
                     <input type="date" class="form-control" v-model="historyFilters.date_to" @change="fetchHistory(1)" />
                  </div>
               </div>
            </div>

            <div v-if="loadingHistory">
               <div class="table-responsive d-none d-md-block">
                  <table class="table table-striped align-middle mb-0 panel-table">
                     <thead>
                        <tr>
                           <th>Customer</th>
                           <th>Type</th>
                           <th>Item</th>
                           <th>Payment</th>
                           <th>Total</th>
                           <th>Sold At</th>
                           <th class="col-actions"></th>
                        </tr>
                     </thead>
                     <tbody>
                        <tr v-for="index in 6" :key="'history-sk-' + index">
                           <td><div class="skeleton-box" style="width: 120px; height: 14px; border-radius: 4px"></div></td>
                           <td><div class="skeleton-box" style="width: 80px; height: 22px; border-radius: 999px"></div></td>
                           <td><div class="skeleton-box" style="width: 130px; height: 14px; border-radius: 4px"></div></td>
                           <td><div class="skeleton-box" style="width: 100px; height: 14px; border-radius: 4px"></div></td>
                           <td><div class="skeleton-box" style="width: 80px; height: 14px; border-radius: 4px"></div></td>
                           <td><div class="skeleton-box" style="width: 130px; height: 14px; border-radius: 4px"></div></td>
                           <td><div class="skeleton-box ms-auto" style="width: 80px; height: 32px; border-radius: 6px"></div></td>
                        </tr>
                     </tbody>
                  </table>
               </div>

               <div class="d-md-none">
                  <div class="member-card" v-for="index in 4" :key="'history-mobile-sk-' + index">
                     <div class="member-card-top">
                        <div class="member-card-identity">
                           <div class="skeleton-box" style="width: 40px; height: 40px; border-radius: 999px"></div>
                           <div>
                              <div class="skeleton-box mb-1" style="width: 110px; height: 14px; border-radius: 4px"></div>
                              <div class="skeleton-box" style="width: 90px; height: 11px; border-radius: 4px"></div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div v-else-if="history.length === 0" class="text-center py-5 text-muted">
               <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i>
               <div>No transactions recorded yet.</div>
            </div>

            <div v-else>
               <div class="table-responsive d-none d-md-block">
                  <table class="table table-hover table-striped align-middle mb-0 panel-table">
                     <thead>
                        <tr>
                           <th>Customer</th>
                           <th>Type</th>
                           <th>Item</th>
                           <th>Payment</th>
                           <th>Total</th>
                           <th>Sold At</th>
                           <th class="col-actions"></th>
                        </tr>
                     </thead>
                     <tbody>
                        <tr v-for="transaction in history" :key="transaction.id">
                           <td>
                              <a class="text-decoration-none" :href="transaction.source_url" v-if="transaction.source_url" title="Open source record">
                                 <div class="member-name">{{ transaction.customer_name || "—" }}</div>
                              </a>
                              <div v-else class="member-name">{{ transaction.customer_name || "—" }}</div>
                              <div class="small text-muted">{{ transaction.receipt_number }} · {{ transaction.processed_by || "—" }}</div>
                           </td>
                           <td>
                              <span class="m-badge m-badge--open">{{ $filters.capitalize(transaction.type) }}</span>
                           </td>
                           <td class="small">{{ transaction.item_name || "—" }}</td>
                           <td class="small">
                              <div>{{ transaction.payment_method_label || $filters.capitalize(transaction.payment_method) }}</div>
                              <div class="text-muted" v-if="transaction.payment_reference">{{ transaction.payment_reference }}</div>
                           </td>
                           <td class="fw-semibold small">₱{{ $filters.formatMoney(transaction.total) }}</td>
                           <td class="small text-muted">{{ $filters.formatDateTime(transaction.sold_at) }}</td>
                           <td>
                              <div class="d-flex gap-1">
                                 <a class="btn btn-sm btn-outline-info" :href="transaction.receipt_url" target="_blank" rel="noopener" title="Download receipt">
                                    <i class="bi bi-printer tbl-icon"></i>
                                 </a>
                                 <a class="btn btn-sm btn-outline-dark" :href="transaction.source_url" v-if="transaction.source_url" title="Open source record">
                                    <i class="bi bi-box-arrow-up-right tbl-icon"></i>
                                 </a>
                              </div>
                           </td>
                        </tr>
                     </tbody>
                  </table>
               </div>

               <div class="d-md-none">
                  <div class="member-card" v-for="transaction in history" :key="'mobile-history-' + transaction.id">
                     <div class="member-card-top">
                        <div class="member-card-identity">
                           <div class="member-avatar">{{ $filters.getNameInitials(transaction.customer_name || transaction.item_name) }}</div>
                           <div>
                              <div class="member-card-name">{{ transaction.customer_name || "—" }}</div>
                              <div class="member-card-sub">{{ transaction.receipt_number }}</div>
                           </div>
                        </div>
                     </div>
                     <div class="member-card-tags">
                        <span class="m-badge m-badge--open">{{ $filters.capitalize(transaction.type) }}</span>
                        <span class="m-badge m-badge--active">₱{{ $filters.formatMoney(transaction.total) }}</span>
                     </div>
                     <div class="small text-muted mt-2">
                        <div>{{ transaction.item_name || "—" }}</div>
                        <div>{{ transaction.payment_method_label || $filters.capitalize(transaction.payment_method) }}</div>
                        <div>{{ $filters.formatDateTime(transaction.sold_at) }}</div>
                     </div>
                     <div class="d-flex gap-2 mt-3">
                        <a class="btn btn-sm btn-outline-info" :href="transaction.receipt_url" target="_blank" rel="noopener">Download Receipt</a>
                        <a class="btn btn-sm btn-outline-primary" :href="transaction.source_url" v-if="transaction.source_url">Open Record</a>
                     </div>
                  </div>
               </div>
            </div>

            <div v-if="!loadingHistory && historyPagination.lastPage > 1" class="d-flex justify-content-center py-3 border-top">
               <nav>
                  <ul class="pagination pagination-sm mb-0">
                     <li v-for="link in historyPagination.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                        <a class="page-link" href="#" @click.prevent="goToHistoryPage(link)" v-html="link.label"></a>
                     </li>
                  </ul>
               </nav>
            </div>
         </div>
      </template>
   </div>
</template>

<script>
export default {
   props: {
      branchesData: {
         type: Array,
         default: function () {
            return [];
         },
      },
   },
   data: function () {
      var storedBranchId = localStorage.getItem("selectedBranch");
      var now = new Date();
      var localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000);

      return {
         saleType: "inventory",
         selectedBranch: storedBranchId && storedBranchId !== "null" ? parseInt(storedBranchId, 10) || "" : "",
         loadingContext: false,
         loadingHistory: false,
         processingSale: false,
         pageError: "",
         successMessage: "",
         lastCompletedSale: null,
         historySearchTimer: null,
         context: {
            inventory_items: [],
            membership_rates: [],
            pt_rates: [],
         },
         history: [],
         historyPagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
         historyFilters: {
            search: "",
            type: "",
            date_from: "",
            date_to: "",
         },
         formErrors: {},
         form: {
            inventory_lines: [
               {
                  key: "inv-" + Date.now() + "-0",
                  inventory_item_id: "",
                  inventory_item_label: "",
                  quantity: 1,
               },
            ],
            member_mode: "existing",
            member_id: null,
            member_label: "",
            customer_name: "",
            customer_email: "",
            customer_phone: "",
            membership_rate_plan_id: "",
            pt_product_id: "",
            walk_in_rate_plan_id: "",
            start_date: now.toISOString().slice(0, 10),
            assigned_at: now.toISOString().slice(0, 10),
            expires_at: "",
            amount_paid: "",
            payment_method: "cash",
            amount_received: "",
            payment_reference: "",
            sold_at: localDate.toISOString().slice(0, 16),
            notes: "",
         },
         paymentMethods: [
            { value: "cash", label: "Cash" },
            { value: "gcash", label: "GCash" },
            { value: "card", label: "Card" },
            { value: "bank_transfer", label: "Bank Transfer" },
         ],
      };
   },
   mounted: function () {
      this.form = this.defaultForm();

      if (this.selectedBranch && !this.branchesData.some((branch) => branch.id === this.selectedBranch)) {
         this.selectedBranch = "";
      }

      if (this.selectedBranch) {
         this.fetchContext();
         this.fetchHistory();
      }
   },
   watch: {
      summaryTotal: function (value, oldValue) {
         var currentAmountReceived = parseFloat(this.form.amount_received) || 0;

         if (!value) {
            this.form.amount_received = "";
            return;
         }

         if (!this.isCashPayment) {
            this.form.amount_received = value.toFixed(2);
            return;
         }

         if (!currentAmountReceived || Math.abs(currentAmountReceived - (oldValue || 0)) < 0.01) {
            this.form.amount_received = value.toFixed(2);
         }
      },
      "form.payment_method": function (value) {
         if (value === "cash") {
            if (!this.form.amount_received && this.summaryTotal > 0) {
               this.form.amount_received = this.summaryTotal.toFixed(2);
            }
            return;
         }

         this.form.amount_received = this.summaryTotal > 0 ? this.summaryTotal.toFixed(2) : "";
      },
   },
   computed: {
      currentBranchName: function () {
         return this.branchesData.find((branch) => branch.id === this.selectedBranch)?.name || "the selected branch";
      },
      isCashPayment: function () {
         return this.form.payment_method === "cash";
      },
      requiresPaymentReference: function () {
         return this.form.payment_method !== "cash";
      },
      paymentReferencePlaceholder: function () {
         if (this.form.payment_method === "gcash") {
            return "Enter the GCash reference number";
         }

         if (this.form.payment_method === "card") {
            return "Enter the card approval or terminal reference";
         }

         if (this.form.payment_method === "bank_transfer") {
            return "Enter the bank transfer reference";
         }

         return "Enter the payment reference";
      },
      currentPaymentMethodLabel: function () {
         return this.paymentMethods.find((method) => method.value === this.form.payment_method)?.label || this.$filters.capitalize(this.form.payment_method);
      },
      selectedMembershipPlan: function () {
         return this.context.membership_rates.find((plan) => Number(plan.id) === Number(this.form.membership_rate_plan_id)) || null;
      },
      selectedPtProduct: function () {
         return this.context.pt_rates.find((ptRate) => Number(ptRate.id) === Number(this.form.pt_product_id)) || null;
      },
      selectedWalkInPlan: function () {
         return this.context.membership_rates.find((plan) => Number(plan.id) === Number(this.form.walk_in_rate_plan_id)) || null;
      },
      normalizedInventoryLines: function () {
         return this.form.inventory_lines
            .map((line) => {
               var item = this.findInventoryItem(line.inventory_item_id);
               var quantity = parseInt(line.quantity, 10) || 0;
               var unitPrice = parseFloat(item?.selling_price) || 0;

               return {
                  key: line.key,
                  inventory_item_id: line.inventory_item_id,
                  quantity: quantity,
                  item: item,
                  line_total: quantity * unitPrice,
               };
            })
            .filter(function (line) {
               return !!line.inventory_item_id;
            });
      },
      summaryCustomer: function () {
         if (this.saleType === "walk_in") {
            return this.form.customer_name;
         }

         if (this.saleType === "inventory") {
            return "Walk-in / Member Counter Sale";
         }

         if (this.form.member_mode === "existing") {
            return this.form.member_label;
         }

         return this.form.customer_name;
      },
      summaryItem: function () {
         if (this.saleType === "inventory") {
            if (!this.normalizedInventoryLines.length) {
               return "";
            }

            if (this.normalizedInventoryLines.length === 1) {
               return this.normalizedInventoryLines[0].item?.name || "";
            }

            return this.normalizedInventoryLines.length + " inventory items";
         }

         if (this.saleType === "membership") {
            return this.selectedMembershipPlan?.name || "";
         }

         if (this.saleType === "pt_package") {
            return this.selectedPtProduct?.name || "";
         }

         return this.selectedWalkInPlan?.name || "Walk-in";
      },
      summaryTotal: function () {
         if (this.saleType === "inventory") {
            return this.normalizedInventoryLines.reduce(function (total, line) {
               return total + line.line_total;
            }, 0);
         }

         if (this.saleType === "membership") {
            return parseFloat(this.selectedMembershipPlan?.price) || 0;
         }

         if (this.saleType === "pt_package") {
            return parseFloat(this.selectedPtProduct?.price) || 0;
         }

         return parseFloat(this.form.amount_paid) || 0;
      },
      amountReceivedValue: function () {
         return parseFloat(this.form.amount_received) || 0;
      },
      changeDue: function () {
         if (!this.isCashPayment) {
            return 0;
         }

         return Math.max(this.amountReceivedValue - this.summaryTotal, 0);
      },
   },
   methods: {
      defaultInventoryLine: function () {
         return {
            key: "inv-" + Date.now() + "-" + Math.floor(Math.random() * 100000),
            inventory_item_id: "",
            inventory_item_label: "",
            quantity: 1,
         };
      },
      defaultForm: function () {
         var currentDate = new Date();

         return {
            inventory_lines: [this.defaultInventoryLine()],
            member_mode: "existing",
            member_id: null,
            member_label: "",
            customer_name: "",
            customer_email: "",
            customer_phone: "",
            membership_rate_plan_id: "",
            pt_product_id: "",
            walk_in_rate_plan_id: "",
            start_date: currentDate.toISOString().slice(0, 10),
            assigned_at: currentDate.toISOString().slice(0, 10),
            expires_at: "",
            amount_paid: "",
            payment_method: "cash",
            amount_received: "",
            payment_reference: "",
            sold_at: this.$filters.formatDateTime(new Date(), "input"),
            notes: "",
         };
      },
      resetForm: function () {
         this.form = this.defaultForm();
         this.formErrors = {};
      },
      setSaleType: function (type) {
         this.saleType = type;
         this.pageError = "";
         this.successMessage = "";
         this.lastCompletedSale = null;
         this.resetForm();
      },
      setMemberMode: function (mode) {
         this.form.member_mode = mode;
         this.form.member_id = null;
         this.form.member_label = "";
         this.form.customer_name = "";
         this.form.customer_email = "";
         this.form.customer_phone = "";
         this.formErrors.member_mode = "";
         this.formErrors.member_id = "";
         this.formErrors.customer_name = "";
         this.formErrors.customer_email = "";
         this.formErrors.customer_phone = "";
      },
      addInventoryLine: function () {
         this.form.inventory_lines.push(this.defaultInventoryLine());
      },
      removeInventoryLine: function (line) {
         if (this.form.inventory_lines.length === 1) {
            return;
         }

         this.form.inventory_lines = this.form.inventory_lines.filter(function (inventoryLine) {
            return inventoryLine.key !== line.key;
         });
      },
      fetchContext: function () {
         if (!this.selectedBranch) {
            return;
         }

         this.loadingContext = true;
         this.pageError = "";

         axios
            .get("/panel/sales/context", {
               params: {
                  branch: this.selectedBranch,
               },
            })
            .then((response) => {
               this.context = response.data.options;
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to load selling options.";
            })
            .finally(() => {
               this.loadingContext = false;
            });
      },
      fetchHistory: function (page = 1) {
         if (!this.selectedBranch) {
            return;
         }

         this.loadingHistory = true;

         axios
            .get("/panel/sales/history", {
               params: {
                  branch: this.selectedBranch,
                  search: this.historyFilters.search || undefined,
                  type: this.historyFilters.type || undefined,
                  date_from: this.historyFilters.date_from || undefined,
                  date_to: this.historyFilters.date_to || undefined,
                  page: page,
               },
            })
            .then((response) => {
               this.history = response.data.transactions.data;
               this.historyPagination = {
                  currentPage: response.data.transactions.current_page,
                  lastPage: response.data.transactions.last_page,
                  total: response.data.transactions.total,
                  from: response.data.transactions.from || 0,
                  to: response.data.transactions.to || 0,
                  links: response.data.transactions.links,
               };
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to load transaction history.";
            })
            .finally(() => {
               this.loadingHistory = false;
            });
      },
      fetchMemberOptions: function (search) {
         if (!this.selectedBranch) {
            return Promise.resolve([]);
         }

         return axios
            .get("/panel/members/list", {
               params: {
                  branch: this.selectedBranch,
                  search: search,
               },
            })
            .then((response) => {
               return (response.data.members?.data || []).map((member) => ({
                  id: member.id,
                  name: member.name,
                  meta: [member.email, member.phone].filter(Boolean).join(" • "),
               }));
            })
            .catch(() => []);
      },
      fetchInventoryOptions: function (search, lineKey) {
         var normalizedSearch = String(search || "")
            .trim()
            .toLowerCase();
         var selectedIds = this.form.inventory_lines
            .filter(function (line) {
               return line.key !== lineKey && line.inventory_item_id;
            })
            .map(function (line) {
               return Number(line.inventory_item_id);
            });

         return Promise.resolve(
            this.context.inventory_items
               .filter(function (item) {
                  return !selectedIds.includes(Number(item.id));
               })
               .filter(function (item) {
                  if (!normalizedSearch) {
                     return true;
                  }

                  return [item.name, item.category_name, item.unit].filter(Boolean).some(function (value) {
                     return String(value).toLowerCase().includes(normalizedSearch);
                  });
               })
               .map((item) => ({
                  id: item.id,
                  name: item.name,
                  meta: `${item.category_name || "Uncategorized"} • ${this.$filters.formatQuantity(item.quantity)} ${item.unit} left • ₱${this.$filters.formatMoney(item.selling_price)}`,
               })),
         );
      },
      inventoryFetchOptions: function (lineKey) {
         var vm = this;

         return function (search) {
            return vm.fetchInventoryOptions(search, lineKey);
         };
      },
      handleInventoryItemSelect: function (line, item) {
         line.inventory_item_label = item?.name || "";
      },
      handleMemberSelect: function (member) {
         this.form.member_label = member?.name || "";
      },
      findInventoryItem: function (inventoryItemId) {
         return this.context.inventory_items.find((item) => Number(item.id) === Number(inventoryItemId)) || null;
      },
      inventoryLineTotal: function (line) {
         var item = this.findInventoryItem(line.inventory_item_id);
         var quantity = parseInt(line.quantity, 10) || 0;
         var unitPrice = parseFloat(item?.selling_price) || 0;

         return quantity * unitPrice;
      },
      inventoryLineError: function (index, field) {
         return this.formErrors[`items.${index}.${field}`] || "";
      },
      syncWalkInAmount: function () {
         if (!this.selectedWalkInPlan) {
            return;
         }

         this.form.amount_paid = this.selectedWalkInPlan.price;
      },
      onHistorySearchInput: function () {
         clearTimeout(this.historySearchTimer);
         this.historySearchTimer = setTimeout(() => this.fetchHistory(1), 350);
      },
      buildPayload: function () {
         var payload = {
            branch_id: this.selectedBranch,
            type: this.saleType,
            payment_method: this.form.payment_method,
            amount_received: this.form.amount_received || null,
            payment_reference: this.requiresPaymentReference ? this.form.payment_reference || null : null,
            sold_at: this.form.sold_at,
            notes: this.form.notes || null,
         };

         if (this.saleType === "inventory") {
            payload.items = this.form.inventory_lines
               .filter(function (line) {
                  return !!line.inventory_item_id;
               })
               .map(function (line) {
                  return {
                     inventory_item_id: line.inventory_item_id,
                     quantity: line.quantity ? parseInt(line.quantity, 10) : null,
                  };
               });
         }

         if (this.saleType === "membership") {
            payload.member_mode = this.form.member_mode;
            payload.member_id = this.form.member_mode === "existing" ? this.form.member_id : null;
            payload.customer_name = this.form.member_mode === "new" ? this.form.customer_name : null;
            payload.customer_email = this.form.member_mode === "new" ? this.form.customer_email || null : null;
            payload.customer_phone = this.form.member_mode === "new" ? this.form.customer_phone || null : null;
            payload.rate_plan_id = this.form.membership_rate_plan_id || null;
            payload.start_date = this.form.start_date || null;
         }

         if (this.saleType === "pt_package") {
            payload.member_mode = this.form.member_mode;
            payload.member_id = this.form.member_mode === "existing" ? this.form.member_id : null;
            payload.customer_name = this.form.member_mode === "new" ? this.form.customer_name : null;
            payload.customer_email = this.form.member_mode === "new" ? this.form.customer_email || null : null;
            payload.customer_phone = this.form.member_mode === "new" ? this.form.customer_phone || null : null;
            payload.pt_product_id = this.form.pt_product_id || null;
            payload.assigned_at = this.form.assigned_at || null;
            payload.expires_at = this.form.expires_at || null;
         }

         if (this.saleType === "walk_in") {
            payload.customer_name = this.form.customer_name || null;
            payload.customer_phone = this.form.customer_phone || null;
            payload.rate_plan_id = this.form.walk_in_rate_plan_id || null;
            payload.amount_paid = this.form.amount_paid || null;
         }

         return payload;
      },
      submitSale: function () {
         if (!this.selectedBranch) {
            return;
         }

         this.processingSale = true;
         this.pageError = "";
         this.successMessage = "";
         this.formErrors = {};

         axios
            .post("/panel/sales", this.buildPayload())
            .then((response) => {
               this.lastCompletedSale = response.data;
               this.successMessage = `Sale recorded successfully for ${response.data.item_name || "the selected item"}.`;
               this.resetForm();
               this.fetchContext();
               this.fetchHistory(1);
            })
            .catch((error) => {
               if (error.response?.status === 422) {
                  this.formErrors = Object.fromEntries(
                     Object.entries(error.response.data.errors || {}).map(function ([field, messages]) {
                        return [field, Array.isArray(messages) ? messages[0] : messages];
                     }),
                  );
                  return;
               }

               this.pageError = error.response?.data?.message || "Failed to process sale.";
            })
            .finally(() => {
               this.processingSale = false;
            });
      },
      goToHistoryPage: function (link) {
         if (!link.url) {
            return;
         }

         var page = parseInt(new URL(link.url).searchParams.get("page") || "1", 10);
         this.fetchHistory(page);
      },
   },
   beforeUnmount: function () {
      clearTimeout(this.historySearchTimer);
   },
};
</script>

<style scoped>
.sales-type-group {
   width: 100%;
   display: grid;
   grid-template-columns: repeat(2, minmax(0, 1fr));
   gap: 0.5rem;
}

.sales-type-group > .btn {
   width: 100%;
   border-radius: 0.5rem !important;
}

@media (min-width: 768px) {
   .sales-type-group {
      width: auto;
      display: inline-flex;
      gap: 0;
   }

   .sales-type-group > .btn {
      width: auto;
      border-radius: 0 !important;
   }

   .sales-type-group > .btn:first-child {
      border-top-left-radius: 0.375rem !important;
      border-bottom-left-radius: 0.375rem !important;
   }

   .sales-type-group > .btn:last-child {
      border-top-right-radius: 0.375rem !important;
      border-bottom-right-radius: 0.375rem !important;
   }
}
</style>
