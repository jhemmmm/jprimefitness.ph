<template>
   <div class="sales-reports-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Sales Reports</h4>
            <p class="text-muted small mb-0">Review sales performance, breakdowns, and recent transactions for {{ currentBranchLabel }}</p>
         </div>
      </div>

      <div v-if="!branchesData.length" class="panel-card p-5 text-center text-muted">
         <i class="bi bi-bar-chart-fill fs-1 d-block mb-2 opacity-25"></i>
         <div>No accessible branches found.</div>
      </div>

      <template v-else>
         <div class="panel-card mb-4">
            <div class="panel-card-header">
               <div>
                  <div class="panel-card-title">Filters</div>
                  <div class="panel-card-sub">Use the sidebar branch selector, date range, sale type, and payment method to refine this report.</div>
               </div>
            </div>
            <div class="p-3 p-md-4">
               <div class="row g-3 align-items-end">
                  <div class="col-12 col-md-6 col-xl-3">
                     <label class="form-label">Date From</label>
                     <input type="date" class="form-control" v-model="filters.date_from" />
                  </div>
                  <div class="col-12 col-md-6 col-xl-3">
                     <label class="form-label">Date To</label>
                     <input type="date" class="form-control" v-model="filters.date_to" />
                  </div>
                  <div class="col-12 col-md-6 col-xl-3">
                     <label class="form-label">Sale Type</label>
                     <select class="form-select" v-model="filters.type">
                        <option value="">All Types</option>
                        <option value="inventory">Inventory</option>
                        <option value="membership">Membership</option>
                        <option value="pt_package">PT Package</option>
                        <option value="walk_in">Walk-in</option>
                     </select>
                  </div>
                  <div class="col-12 col-md-6 col-xl-3">
                     <label class="form-label">Payment Method</label>
                     <select class="form-select" v-model="filters.payment_method">
                        <option value="">All Methods</option>
                        <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                     </select>
                  </div>
               </div>

               <div class="d-flex gap-2 mt-3">
                  <button type="button" class="btn btn-danger btn-sm px-3" @click="fetchReport" :disabled="loading">
                     <i class="bi bi-arrow-repeat me-1"></i>
                     Refresh
                  </button>
                  <a class="btn btn-outline-dark btn-sm px-3" :href="exportUrl">
                     <i class="bi bi-download me-1"></i>
                     Export CSV
                  </a>
                  <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="resetFilters" :disabled="loading">Reset</button>
               </div>
            </div>
         </div>

         <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

         <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-primary-soft">
                     <i class="bi bi-cash-stack text-primary"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Total Sales</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 90px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>₱{{ $filters.formatMoney(report.summary.total_sales) }}</div>
                  </div>
               </div>
            </div>
            <div class="col-6 col-xl-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-success-soft">
                     <i class="bi bi-receipt-cutoff text-success"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Transactions</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 54px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>{{ report.summary.transaction_count }}</div>
                  </div>
               </div>
            </div>
            <div class="col-6 col-xl-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-warning-soft">
                     <i class="bi bi-graph-up-arrow text-warning"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Average Sale</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 80px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>₱{{ $filters.formatMoney(report.summary.average_sale) }}</div>
                  </div>
               </div>
            </div>
            <div class="col-6 col-xl-3">
               <div class="stat-card">
                  <div class="stat-card-icon bg-danger-soft">
                     <i class="bi bi-wallet2 text-danger"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">Cash Collected</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 80px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>₱{{ $filters.formatMoney(report.summary.cash_sales) }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4" v-if="!loading && report.summary.transaction_count > 0">
            <div class="col-12 col-xl-8">
               <sales-daily-trend-chart :trend="report.daily_trend"></sales-daily-trend-chart>
            </div>
            <div class="col-12 col-xl-4">
               <sales-type-breakdown-chart :breakdown="report.type_breakdown.filter((row) => row.total_sales > 0)"></sales-type-breakdown-chart>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12 col-xl-4">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Sales by Type</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3 d-none d-md-block">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 4" :key="'type-sk-' + index"></div>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="index in 3" :key="'type-mobile-sk-' + index">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-pie-chart-fill"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <div class="skeleton-box" style="width: 72px; height: 12px; border-radius: 4px"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Type</th>
                                    <th>Transactions</th>
                                    <th>Total</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="row in report.type_breakdown" :key="row.type">
                                    <td>{{ row.label }}</td>
                                    <td>{{ row.transaction_count }}</td>
                                    <td class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="row in report.type_breakdown" :key="'type-mobile-' + row.type">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-pie-chart-fill"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ row.label }}</div>
                                       <div class="member-card-sub">{{ row.transaction_count }} transaction{{ row.transaction_count !== 1 ? "s" : "" }}</div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <span>Total Sales</span>
                                 <span class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</span>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-4">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Payment Methods</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3 d-none d-md-block">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 4" :key="'payment-sk-' + index"></div>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="index in 3" :key="'payment-mobile-sk-' + index">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-credit-card-2-front-fill"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <div class="skeleton-box" style="width: 72px; height: 12px; border-radius: 4px"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div v-else-if="report.payment_breakdown.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-credit-card-2-front empty-icon"></i>
                        <p class="mt-2 mb-1">No payment activity for this filter.</p>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Method</th>
                                    <th>Transactions</th>
                                    <th>Total</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="row in report.payment_breakdown" :key="row.payment_method">
                                    <td>{{ row.label }}</td>
                                    <td>{{ row.transaction_count }}</td>
                                    <td class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="row in report.payment_breakdown" :key="'payment-mobile-' + row.payment_method">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-credit-card-2-front-fill"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ row.label }}</div>
                                       <div class="member-card-sub">{{ row.transaction_count }} transaction{{ row.transaction_count !== 1 ? "s" : "" }}</div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <span>Total Sales</span>
                                 <span class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</span>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-4">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">{{ report.scope.is_all_branches ? "Branch Breakdown" : "Selected Branch" }}</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3 d-none d-md-block">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 4" :key="'branch-sk-' + index"></div>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="index in 3" :key="'branch-mobile-sk-' + index">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-diagram-3-fill"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <div class="skeleton-box" style="width: 72px; height: 12px; border-radius: 4px"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Branch</th>
                                    <th>Transactions</th>
                                    <th>Total</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-if="report.branch_breakdown.length === 0" class="empty-row">
                                    <td colspan="3">
                                       <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                       <div class="mt-1 text-muted small">No branch activity for this filter.</div>
                                    </td>
                                 </tr>
                                 <tr v-for="row in report.branch_breakdown" :key="row.branch_id">
                                    <td>{{ row.branch_name }}</td>
                                    <td>{{ row.transaction_count }}</td>
                                    <td class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div v-if="report.branch_breakdown.length === 0" class="text-center py-4 text-muted small">No branch activity for this filter.</div>
                           <div v-else class="member-card" v-for="row in report.branch_breakdown" :key="'branch-mobile-' + row.branch_id">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-diagram-3-fill"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ row.branch_name }}</div>
                                       <div class="member-card-sub">{{ row.transaction_count }} transaction{{ row.transaction_count !== 1 ? "s" : "" }}</div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <span>Total Sales</span>
                                 <span class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</span>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12 col-xl-7">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Daily Sales Trend</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3 d-none d-md-block">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 6" :key="'trend-sk-' + index"></div>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="index in 4" :key="'trend-mobile-sk-' + index">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-calendar3"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <div class="skeleton-box" style="width: 72px; height: 12px; border-radius: 4px"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Date</th>
                                    <th>Transactions</th>
                                    <th>Total</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-if="report.daily_trend.length === 0" class="empty-row">
                                    <td colspan="3">
                                       <i class="bi bi-calendar-x text-muted" style="font-size: 1.5rem"></i>
                                       <div class="mt-1 text-muted small">No sales trend data for this filter.</div>
                                    </td>
                                 </tr>
                                 <tr v-for="row in report.daily_trend" :key="row.sale_date">
                                    <td>{{ $filters.formatDate(row.sale_date) }}</td>
                                    <td>{{ row.transaction_count }}</td>
                                    <td class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div v-if="report.daily_trend.length === 0" class="text-center py-4 text-muted small">No sales trend data for this filter.</div>
                           <div v-else class="member-card" v-for="row in report.daily_trend" :key="'trend-mobile-' + row.sale_date">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-calendar3"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ $filters.formatDate(row.sale_date) }}</div>
                                       <div class="member-card-sub">{{ row.transaction_count }} transaction{{ row.transaction_count !== 1 ? "s" : "" }}</div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <span>Total Sales</span>
                                 <span class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</span>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-5">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Top Items</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3 d-none d-md-block">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 6" :key="'item-sk-' + index"></div>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="index in 4" :key="'item-mobile-sk-' + index">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-bag-fill"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-tags">
                                 <div class="skeleton-box" style="width: 84px; height: 22px; border-radius: 999px"></div>
                              </div>
                              <div class="member-card-footer">
                                 <div class="skeleton-box" style="width: 72px; height: 12px; border-radius: 4px"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-if="report.top_items.length === 0" class="empty-row">
                                    <td colspan="3">
                                       <i class="bi bi-bag-x text-muted" style="font-size: 1.5rem"></i>
                                       <div class="mt-1 text-muted small">No item sales for this filter.</div>
                                    </td>
                                 </tr>
                                 <tr v-for="row in report.top_items" :key="row.type + '-' + row.name">
                                    <td>
                                       <div class="fw-semibold">{{ row.name }}</div>
                                       <div class="text-muted small">{{ $filters.capitalize(row.type) }}</div>
                                    </td>
                                    <td>{{ $filters.formatQuantity(row.quantity) }}</td>
                                    <td class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div v-if="report.top_items.length === 0" class="text-center py-4 text-muted small">No item sales for this filter.</div>
                           <div v-else class="member-card" v-for="row in report.top_items" :key="'item-mobile-' + row.type + '-' + row.name">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="inventory-avatar">
                                       <i class="bi bi-bag-fill"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ row.name }}</div>
                                       <div class="member-card-sub">{{ $filters.capitalize(row.type) }}</div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-tags ps-0">
                                 <span class="m-badge m-badge--plan">Qty: {{ $filters.formatQuantity(row.quantity) }}</span>
                              </div>
                              <div class="member-card-footer">
                                 <span>Total Sales</span>
                                 <span class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</span>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="panel-card">
            <div class="panel-card-header">
               <div class="panel-card-title">Recent Transactions</div>
            </div>
            <div class="panel-card-body p-0">
               <div v-if="loading">
                  <div class="table-responsive d-none d-md-block">
                     <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>Branch</th>
                              <th>Customer</th>
                              <th>Type</th>
                              <th>Payment</th>
                              <th>Total</th>
                              <th>Sold At</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-for="index in 6" :key="'recent-sk-' + index">
                              <td><div class="skeleton-box" style="width: 90px; height: 14px; border-radius: 4px"></div></td>
                              <td><div class="skeleton-box" style="width: 120px; height: 14px; border-radius: 4px"></div></td>
                              <td><div class="skeleton-box" style="width: 80px; height: 22px; border-radius: 999px"></div></td>
                              <td><div class="skeleton-box" style="width: 90px; height: 14px; border-radius: 4px"></div></td>
                              <td><div class="skeleton-box" style="width: 80px; height: 14px; border-radius: 4px"></div></td>
                              <td><div class="skeleton-box" style="width: 120px; height: 14px; border-radius: 4px"></div></td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
                  <div class="d-md-none p-3">
                     <div class="member-card" v-for="index in 4" :key="'recent-mobile-sk-' + index">
                        <div class="member-card-top">
                           <div class="member-card-identity">
                              <div class="inventory-avatar">
                                 <i class="bi bi-receipt-cutoff"></i>
                              </div>
                              <div>
                                 <div class="skeleton-box mb-1" style="width: 126px; height: 14px; border-radius: 4px"></div>
                                 <div class="skeleton-box" style="width: 98px; height: 11px; border-radius: 4px"></div>
                              </div>
                           </div>
                        </div>
                        <div class="member-card-tags">
                           <div class="skeleton-box" style="width: 82px; height: 22px; border-radius: 999px"></div>
                           <div class="skeleton-box" style="width: 86px; height: 22px; border-radius: 999px"></div>
                        </div>
                        <div class="small text-muted mb-2">
                           <div class="skeleton-box mb-1" style="width: 110px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box mb-1" style="width: 118px; height: 12px; border-radius: 4px"></div>
                           <div class="skeleton-box" style="width: 96px; height: 12px; border-radius: 4px"></div>
                        </div>
                        <div class="member-card-footer">
                           <div class="skeleton-box" style="width: 78px; height: 12px; border-radius: 4px"></div>
                        </div>
                     </div>
                  </div>
               </div>
               <div v-else>
                  <div class="table-responsive d-none d-md-block">
                     <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>Branch</th>
                              <th>Customer</th>
                              <th>Type</th>
                              <th>Payment</th>
                              <th>Total</th>
                              <th>Sold At</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="report.recent_transactions.length === 0" class="empty-row">
                              <td colspan="6">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No recent transactions for this filter.</div>
                              </td>
                           </tr>
                           <tr v-for="transaction in report.recent_transactions" :key="transaction.id">
                              <td>{{ transaction.branch_name || "—" }}</td>
                              <td>
                                 <div class="fw-semibold">{{ transaction.customer_name || "Walk-in / Counter Sale" }}</div>
                                 <div class="small text-muted">{{ transaction.item_name || "—" }}</div>
                              </td>
                              <td>
                                 <span class="m-badge m-badge--open">{{ $filters.capitalize(transaction.type) }}</span>
                              </td>
                              <td>{{ transaction.payment_method_label }}</td>
                              <td class="fw-semibold">₱{{ $filters.formatMoney(transaction.total) }}</td>
                              <td class="small text-muted">
                                 <div>{{ $filters.formatDateTime(transaction.sold_at) }}</div>
                                 <div>{{ transaction.processed_by || "—" }}</div>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
                  <div class="d-md-none p-3">
                     <div v-if="report.recent_transactions.length === 0" class="text-center py-4 text-muted small">No recent transactions for this filter.</div>
                     <div v-else class="member-card" v-for="transaction in report.recent_transactions" :key="'recent-mobile-' + transaction.id">
                        <div class="member-card-top">
                           <div class="member-card-identity">
                              <div class="inventory-avatar">
                                 <i class="bi bi-receipt-cutoff"></i>
                              </div>
                              <div>
                                 <div class="member-card-name">{{ transaction.customer_name || "Walk-in / Counter Sale" }}</div>
                                 <div class="member-card-sub">{{ transaction.item_name || "—" }}</div>
                              </div>
                           </div>
                        </div>
                        <div class="member-card-tags ps-0">
                           <span class="m-badge m-badge--open">{{ $filters.capitalize(transaction.type) }}</span>
                           <span class="m-badge m-badge--plan">{{ transaction.payment_method_label }}</span>
                        </div>
                        <div class="small text-muted mb-2">
                           <div>Branch: {{ transaction.branch_name || "—" }}</div>
                           <div>Sold At: {{ $filters.formatDateTime(transaction.sold_at) }}</div>
                           <div>Processed By: {{ transaction.processed_by || "—" }}</div>
                        </div>
                        <div class="member-card-footer">
                           <span>Total</span>
                           <span class="fw-semibold">₱{{ $filters.formatMoney(transaction.total) }}</span>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </template>
   </div>
</template>

<script>
import SalesDailyTrendChart from "./charts/SalesDailyTrendChart.vue";
import SalesTypeBreakdownChart from "./charts/SalesTypeBreakdownChart.vue";

export default {
   components: {
      SalesDailyTrendChart,
      SalesTypeBreakdownChart,
   },
   props: {
      branchesData: {
         type: Array,
         default: function () {
            return [];
         },
      },
   },
   data: function () {
      return {
         loading: false,
         pageError: "",
         selectedBranch: null,
         filters: {
            date_from: this.defaultDateFrom(),
            date_to: this.defaultDateTo(),
            type: "",
            payment_method: "",
         },
         report: this.emptyReport(),
         paymentMethods: [
            { value: "cash", label: "Cash" },
            { value: "gcash", label: "GCash" },
            { value: "card", label: "Card" },
            { value: "bank_transfer", label: "Bank Transfer" },
            { value: "online_payment", label: "Online Payment" },
         ],
      };
   },
   computed: {
      currentBranchLabel: function () {
         if (!this.selectedBranch) {
            return "all accessible branches";
         }

         const branch = this.branchesData.find((item) => item.id === this.selectedBranch);
         return branch ? branch.name : "the selected branch";
      },
      exportUrl: function () {
         const params = new URLSearchParams();
         const payload = this.buildParams();

         Object.keys(payload).forEach((key) => {
            if (payload[key] !== undefined && payload[key] !== null && payload[key] !== "") {
               params.append(key, payload[key]);
            }
         });

         return `/panel/reports/sales/export${params.toString() ? `?${params.toString()}` : ""}`;
      },
   },
   mounted: function () {
      this.selectedBranch = this.resolveSelectedBranch();
      this.fetchReport();
   },
   methods: {
      emptyReport: function () {
         return {
            scope: {
               branch: null,
               is_all_branches: true,
            },
            summary: {
               total_sales: 0,
               transaction_count: 0,
               average_sale: 0,
               cash_sales: 0,
            },
            type_breakdown: [],
            payment_breakdown: [],
            daily_trend: [],
            top_items: [],
            branch_breakdown: [],
            recent_transactions: [],
         };
      },
      defaultDateFrom: function () {
         const now = new Date();
         const month = String(now.getMonth() + 1).padStart(2, "0");
         return `${now.getFullYear()}-${month}-01`;
      },
      defaultDateTo: function () {
         const now = new Date();
         const month = String(now.getMonth() + 1).padStart(2, "0");
         const day = String(now.getDate()).padStart(2, "0");
         return `${now.getFullYear()}-${month}-${day}`;
      },
      resolveSelectedBranch: function () {
         const storedBranchId = localStorage.getItem("selectedBranch");

         if (!storedBranchId || storedBranchId === "null") {
            return null;
         }

         const branchId = parseInt(storedBranchId, 10);
         return Number.isNaN(branchId) ? null : branchId;
      },
      buildParams: function () {
         return {
            branch: this.selectedBranch || undefined,
            date_from: this.filters.date_from || undefined,
            date_to: this.filters.date_to || undefined,
            type: this.filters.type || undefined,
            payment_method: this.filters.payment_method || undefined,
         };
      },
      fetchReport: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/reports/sales/data", {
               params: this.buildParams(),
            })
            .then((response) => {
               this.report = response.data;
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Unable to load sales reports right now.";
               this.report = this.emptyReport();
            })
            .finally(() => {
               this.loading = false;
            });
      },
      resetFilters: function () {
         this.filters.date_from = this.defaultDateFrom();
         this.filters.date_to = this.defaultDateTo();
         this.filters.type = "";
         this.filters.payment_method = "";
         this.fetchReport();
      },
   },
};
</script>
