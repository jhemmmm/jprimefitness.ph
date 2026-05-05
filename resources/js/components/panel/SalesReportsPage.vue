<template>
   <div class="sales-reports-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Sales Reports</h4>
            <p class="text-muted small mb-0">Review sales performance, breakdowns, and recent transactions.</p>
         </div>
      </div>
      <div class="panel-card mb-4">
         <div class="panel-card-header">
            <div>
               <div class="panel-card-title">Filters</div>
               <div class="panel-card-sub">Use the date range, sale type, and payment method filters to refine this report.</div>
            </div>
            <div class="d-none d-md-flex align-items-center gap-2">
               <span class="text-muted small">{{ activeRangeLabel }}</span>
            </div>
         </div>
         <div class="p-3 p-md-4">
            <div class="d-flex flex-wrap gap-2 mb-3">
               <button
                  v-for="preset in rangePresets"
                  :key="preset.key"
                  type="button"
                  class="btn btn-sm"
                  :class="activeRangeKey === preset.key ? 'btn-danger' : 'btn-outline-secondary'"
                  @click="applyRangePreset(preset.key)"
               >
                  {{ preset.label }}
               </button>
            </div>

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

            <div class="d-flex flex-wrap gap-2 mt-3">
               <button type="button" class="btn btn-danger btn-sm px-3" @click="fetchReport" :disabled="loading">
                  <i class="bi bi-arrow-repeat me-1"></i>
                  Refresh
               </button>
               <a class="btn btn-outline-dark btn-sm px-3" :href="exportUrl">
                  <i class="bi bi-download me-1"></i>
                  Export CSV
               </a>
               <button
                  type="button"
                  class="btn btn-outline-secondary btn-sm px-3 ms-auto"
                  @click="resetFilters"
                  :disabled="!hasNonDefaultFilters"
               >
                  <i class="bi bi-x-circle me-1"></i>
                  Reset
               </button>
            </div>

            <div v-if="activeFilterChips.length > 0" class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
               <span class="text-muted small align-self-center">Active filters:</span>
               <span v-for="chip in activeFilterChips" :key="chip.key" class="m-badge m-badge--plan d-inline-flex align-items-center gap-1">
                  {{ chip.label }}
                  <button type="button" class="btn-close btn-close-sm ms-1" style="font-size: 0.55rem" aria-label="Clear" @click="clearChip(chip.key)"></button>
               </span>
            </div>
         </div>
      </div>

      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-xl-3" v-for="stat in statCards" :key="stat.label">
            <div class="stat-card h-100">
               <div class="stat-card-icon" :class="stat.iconBg">
                  <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ stat.label }}</div>
                  <div class="stat-card-value" v-if="loading">
                     <div class="skeleton-box" style="width: 90px; height: 18px; border-radius: 5px"></div>
                  </div>
                  <div class="stat-card-value" v-else>{{ stat.value }}</div>
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
         <div class="col-12 col-xl-6">
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
                                 <th class="text-end">Share</th>
                              </tr>
                           </thead>
                           <tbody>
                              <tr v-for="row in typeBreakdownWithShare" :key="row.type">
                                 <td>{{ row.label }}</td>
                                 <td>{{ row.transaction_count }}</td>
                                 <td class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</td>
                                 <td class="text-end text-muted small" style="min-width: 64px">{{ row.share }}%</td>
                              </tr>
                           </tbody>
                        </table>
                     </div>
                     <div class="d-md-none p-3">
                        <div class="member-card" v-for="row in typeBreakdownWithShare" :key="'type-mobile-' + row.type">
                           <div class="member-card-top">
                              <div class="member-card-identity">
                                 <div class="inventory-avatar">
                                    <i class="bi bi-pie-chart-fill"></i>
                                 </div>
                                 <div>
                                    <div class="member-card-name">{{ row.label }}</div>
                                    <div class="member-card-sub">{{ row.transaction_count }} transaction{{ row.transaction_count !== 1 ? "s" : "" }} &middot; {{ row.share }}%</div>
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

         <div class="col-12 col-xl-6">
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
                                 <th class="text-end">Share</th>
                              </tr>
                           </thead>
                           <tbody>
                              <tr v-for="row in paymentBreakdownWithShare" :key="row.payment_method">
                                 <td>{{ row.label }}</td>
                                 <td>{{ row.transaction_count }}</td>
                                 <td class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</td>
                                 <td class="text-end text-muted small" style="min-width: 64px">{{ row.share }}%</td>
                              </tr>
                           </tbody>
                        </table>
                     </div>
                     <div class="d-md-none p-3">
                        <div class="member-card" v-for="row in paymentBreakdownWithShare" :key="'payment-mobile-' + row.payment_method">
                           <div class="member-card-top">
                              <div class="member-card-identity">
                                 <div class="inventory-avatar">
                                    <i class="bi bi-credit-card-2-front-fill"></i>
                                 </div>
                                 <div>
                                    <div class="member-card-name">{{ row.label }}</div>
                                    <div class="member-card-sub">{{ row.transaction_count }} transaction{{ row.transaction_count !== 1 ? "s" : "" }} &middot; {{ row.share }}%</div>
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
                                 <td>{{ formatDate(row.sale_date) }}</td>
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
                                    <div class="member-card-name">{{ formatDate(row.sale_date) }}</div>
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
                  <span v-if="!loading && report.top_items.length > 0" class="text-muted small">Top {{ report.top_items.length }}</span>
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
                     <div v-if="report.top_items.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-bag-x empty-icon"></i>
                        <p class="mt-2 mb-1">No item sales for this filter.</p>
                     </div>
                     <div v-else class="p-3">
                        <div class="share-row" v-for="(row, index) in topItemsWithShare" :key="row.type + '-' + row.name">
                           <div class="share-row-rank">{{ index + 1 }}</div>
                           <div class="share-row-body">
                              <div class="d-flex justify-content-between align-items-baseline gap-2">
                                 <div class="share-row-name">{{ row.name }}</div>
                                 <div class="fw-semibold text-nowrap">₱{{ $filters.formatMoney(row.total_sales) }}</div>
                              </div>
                              <div class="d-flex justify-content-between align-items-center text-muted small mb-1">
                                 <span>{{ $filters.capitalize(row.type) }} &middot; Qty {{ $filters.formatQuantity(row.quantity) }}</span>
                                 <span>{{ row.share }}%</span>
                              </div>
                              <div class="share-bar">
                                 <div class="share-bar-fill" :style="{ width: row.share + '%' }"></div>
                              </div>
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
            <a href="/panel/sales" class="panel-card-action">View all sales</a>
         </div>
         <div class="panel-card-body p-0">
            <div v-if="loading">
               <div class="table-responsive d-none d-md-block">
                  <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                     <thead>
                        <tr>
                           <th>Customer</th>
                           <th>Type</th>
                           <th>Payment</th>
                           <th>Total</th>
                           <th>Sold At</th>
                        </tr>
                     </thead>
                     <tbody>
                        <tr v-for="index in 6" :key="'recent-sk-' + index">
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
                           <th>Customer</th>
                           <th>Type</th>
                           <th>Payment</th>
                           <th>Total</th>
                           <th>Sold At</th>
                        </tr>
                     </thead>
                     <tbody>
                        <tr v-if="report.recent_transactions.length === 0" class="empty-row">
                           <td colspan="5">
                              <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                              <div class="mt-1 text-muted small">No recent transactions for this filter.</div>
                           </td>
                        </tr>
                        <tr v-for="transaction in report.recent_transactions" :key="transaction.id">
                           <td>
                              <div class="fw-semibold">{{ transaction.customer_name || "Walk-in / Counter Sale" }}</div>
                              <div class="small text-muted">{{ transaction.item_name || "-" }}</div>
                           </td>
                           <td>
                              <span class="m-badge m-badge--open">{{ $filters.capitalize(transaction.type) }}</span>
                           </td>
                           <td>{{ transaction.payment_method_label }}</td>
                           <td class="fw-semibold">₱{{ $filters.formatMoney(transaction.total) }}</td>
                           <td class="small text-muted">
                              <div>{{ formatDateTime(transaction.sold_at) }}</div>
                              <div>{{ transaction.processed_by || "-" }}</div>
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
                              <div class="member-card-sub">{{ transaction.item_name || "-" }}</div>
                           </div>
                        </div>
                     </div>
                     <div class="member-card-tags ps-0">
                        <span class="m-badge m-badge--open">{{ $filters.capitalize(transaction.type) }}</span>
                        <span class="m-badge m-badge--plan">{{ transaction.payment_method_label }}</span>
                     </div>
                     <div class="small text-muted mb-2">
                        <div>Sold At: {{ formatDateTime(transaction.sold_at) }}</div>
                        <div>Processed By: {{ transaction.processed_by || "-" }}</div>
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
   </div>
</template>

<script>
import SalesDailyTrendChart from "./charts/SalesDailyTrendChart.vue";
import SalesTypeBreakdownChart from "./charts/SalesTypeBreakdownChart.vue";
import dateRangePresets from "../../mixins/dateRangePresets";
import { formatDate, formatDateTime, startOfCurrentMonthDate, todayDate } from "../../dates";

export default {
   components: {
      SalesDailyTrendChart,
      SalesTypeBreakdownChart,
   },
   mixins: [dateRangePresets],
   props: {
      businessProfile: {
         type: Object,
         default: function () {
            return null;
         },
      },
   },
   data: function () {
      return {
         loading: false,
         pageError: "",
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
      paymentMethodLabel: function () {
         const match = this.paymentMethods.find((option) => option.value === this.filters.payment_method);

         return match ? match.label : "";
      },

      saleTypeLabel: function () {
         const map = {
            inventory: "Inventory",
            membership: "Membership",
            pt_package: "PT Package",
            walk_in: "Walk-in",
         };

         return map[this.filters.type] || "";
      },

      activeFilterChips: function () {
         const chips = [];

         if (this.filters.type) {
            chips.push({ key: "type", label: `Type: ${this.saleTypeLabel}` });
         }

         if (this.filters.payment_method) {
            chips.push({ key: "payment_method", label: `Payment: ${this.paymentMethodLabel}` });
         }

         return chips;
      },

      hasNonDefaultFilters: function () {
         return (
            this.filters.type !== "" ||
            this.filters.payment_method !== "" ||
            this.filters.date_from !== this.defaultDateFrom() ||
            this.filters.date_to !== this.defaultDateTo()
         );
      },

      statCards: function () {
         return [
            {
               label: "Total Sales",
               value: `₱${this.$filters.formatMoney(this.report.summary.total_sales)}`,
               icon: "bi-cash-stack",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
            },
            {
               label: "Transactions",
               value: this.report.summary.transaction_count,
               icon: "bi-receipt-cutoff",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
            },
            {
               label: "Average Sale",
               value: `₱${this.$filters.formatMoney(this.report.summary.average_sale)}`,
               icon: "bi-graph-up-arrow",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
            },
            {
               label: "Cash Collected",
               value: `₱${this.$filters.formatMoney(this.report.summary.cash_sales)}`,
               icon: "bi-wallet2",
               iconBg: "bg-danger-soft",
               iconColor: "text-danger",
            },
         ];
      },

      typeBreakdownWithShare: function () {
         const total = this.report.summary.total_sales || 0;

         return this.report.type_breakdown.map((row) => {
            const share = total > 0 ? Math.round((row.total_sales / total) * 100) : 0;

            return { ...row, share };
         });
      },

      paymentBreakdownWithShare: function () {
         const total = this.report.summary.total_sales || 0;

         return this.report.payment_breakdown.map((row) => {
            const share = total > 0 ? Math.round((row.total_sales / total) * 100) : 0;

            return { ...row, share };
         });
      },

      topItemsWithShare: function () {
         const max = this.report.top_items.reduce((m, row) => Math.max(m, row.total_sales || 0), 0);

         return this.report.top_items.map((row) => {
            const share = max > 0 ? Math.round((row.total_sales / max) * 100) : 0;

            return { ...row, share };
         });
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
      this.fetchReport();
   },
   methods: {
      formatDate,
      formatDateTime,
      emptyReport: function () {
         return {
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
            recent_transactions: [],
         };
      },
      defaultDateFrom: function () {
         return startOfCurrentMonthDate();
      },
      defaultDateTo: function () {
         return todayDate();
      },
      applyRangePreset: function (key) {
         if (this.activeRangeKey === key) {
            return;
         }

         const bounds = this.rangeBoundsByKey[key];

         if (!bounds) {
            return;
         }

         this.filters.date_from = bounds.from;
         this.filters.date_to = bounds.to;
         this.fetchReport();
      },
      resetFilters: function () {
         this.filters.date_from = this.defaultDateFrom();
         this.filters.date_to = this.defaultDateTo();
         this.filters.type = "";
         this.filters.payment_method = "";
         this.fetchReport();
      },
      clearChip: function (key) {
         if (key === "type") {
            this.filters.type = "";
         } else if (key === "payment_method") {
            this.filters.payment_method = "";
         }

         this.fetchReport();
      },
      buildParams: function () {
         return {
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
   },
};
</script>
