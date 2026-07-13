<template>
   <div class="payroll-reports-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Payroll Reports</h4>
            <p class="text-muted small mb-0">Review payroll runs and current payout progress for payrolls ending within the selected period.</p>
         </div>
      </div>
      <div class="panel-card mb-4">
         <div class="panel-card-header">
            <div>
               <div class="panel-card-title">Filters</div>
               <div class="panel-card-sub">Use the payroll period end date range, payroll status, and pay frequency to review this report.</div>
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
                  <input type="date" class="form-control" v-model="filters.date_from" @change="fetchReport(1)" />
               </div>
               <div class="col-12 col-md-6 col-xl-3">
                  <label class="form-label">Date To</label>
                  <input type="date" class="form-control" v-model="filters.date_to" @change="fetchReport(1)" />
               </div>
               <div class="col-12 col-md-6 col-xl-3">
                  <label class="form-label">Status</label>
                  <MultiSelect
                     v-model="filters.status"
                     :options="payrollStatuses"
                     placeholder="All Active Statuses"
                     @update:modelValue="fetchReport(1)"
                  />
               </div>
               <div class="col-12 col-md-6 col-xl-3">
                  <label class="form-label">Pay Frequency</label>
                  <MultiSelect
                     v-model="filters.pay_frequency"
                     :options="payFrequencies"
                     placeholder="All Frequencies"
                     @update:modelValue="fetchReport(1)"
                  />
               </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
               <button type="button" class="btn btn-danger btn-sm px-3" @click="fetchReport(detailPagination.current_page)" :disabled="loading">
                  <i class="bi bi-arrow-repeat me-1"></i>
                  Refresh
               </button>
               <a class="btn btn-outline-dark btn-sm px-3" :href="exportUrl">
                  <i class="bi bi-download me-1"></i>
                  Export CSV
               </a>
            </div>

            <div v-if="activeFilterChips.length > 0" class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-3 border-top">
               <span class="text-muted small">Active filters:</span>
               <span v-for="chip in activeFilterChips" :key="chip.key" class="m-badge m-badge--plan d-inline-flex align-items-center gap-1">
                  {{ chip.label }}
                  <button type="button" class="btn-close btn-close-sm ms-1" style="font-size: 0.55rem" aria-label="Clear" @click="clearChip(chip)"></button>
               </span>
               <button type="button" class="btn btn-link btn-sm text-danger px-2 py-0 ms-1" @click="resetFilters">Clear all</button>
            </div>
         </div>
      </div>

         <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

         <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3" v-for="stat in statCards" :key="stat.label">
               <div class="stat-card h-100">
                  <div class="stat-card-icon" :class="stat.iconBg">
                     <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">{{ stat.label }}</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 88px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else :class="stat.valueClass">
                        {{ stat.value }}
                     </div>
                     <div class="stat-card-sub" v-if="stat.sub">{{ stat.sub }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
               <div v-if="loading" class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Payroll Trend</div>
                        <div class="panel-card-sub">Net payroll by period end for the current report filter</div>
                     </div>
                  </div>
                  <div class="panel-card-body chart-wrapper">
                     <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 6" :key="'chart-trend-sk-' + index"></div>
                  </div>
               </div>
               <div v-else-if="report.payroll_trend.length === 0" class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Payroll Trend</div>
                        <div class="panel-card-sub">Net payroll by period end for the current report filter</div>
                     </div>
                  </div>
                  <div class="panel-card-body text-center py-5 text-muted">
                     <i class="bi bi-graph-up-arrow empty-icon"></i>
                     <p class="mt-2 mb-1">No payroll trend data for this filter.</p>
                  </div>
               </div>
               <payroll-trend-chart v-else :trend="report.payroll_trend"></payroll-trend-chart>
            </div>

            <div class="col-12 col-xl-4">
               <div v-if="loading" class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Payroll Status Mix</div>
                        <div class="panel-card-sub">Share of net payroll grouped by payroll status</div>
                     </div>
                  </div>
                  <div class="panel-card-body chart-wrapper chart-wrapper-pie d-flex flex-column justify-content-center">
                     <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 5" :key="'chart-status-sk-' + index"></div>
                  </div>
               </div>
               <div v-else-if="chartStatusBreakdown.length === 0" class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Payroll Status Mix</div>
                        <div class="panel-card-sub">Share of net payroll grouped by payroll status</div>
                     </div>
                  </div>
                  <div class="panel-card-body text-center py-5 text-muted">
                     <i class="bi bi-pie-chart empty-icon"></i>
                     <p class="mt-2 mb-1">No payroll status totals for this filter.</p>
                  </div>
               </div>
               <payroll-status-breakdown-chart v-else :breakdown="chartStatusBreakdown"></payroll-status-breakdown-chart>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12 col-xl-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Status Breakdown</div>
                  </div>
                  <div class="p-3 p-md-4">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="height: 18px; border-radius: 4px" v-for="index in 4" :key="'status-sk-' + index"></div>
                     </div>
                     <div v-else-if="report.status_breakdown.length === 0" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                        <div>No payroll records for this filter.</div>
                     </div>
                     <div v-else>
                        <div class="share-row share-row--block" v-for="row in statusBreakdownWithShare" :key="row.status">
                           <div class="d-flex justify-content-between align-items-center gap-2">
                              <div>
                                 <span :class="['m-badge', $filters.statusBadge(row.status)]">{{ row.label }}</span>
                                 <div class="text-muted small mt-1">{{ row.payroll_count }} payroll run{{ row.payroll_count !== 1 ? "s" : "" }}</div>
                              </div>
                              <div class="text-end">
                                 <div class="fw-semibold">₱{{ $filters.formatMoney(row.net_payroll) }}</div>
                                 <div class="text-muted small">{{ row.share }}%</div>
                              </div>
                           </div>
                           <div class="share-bar mt-2">
                              <div class="share-bar-fill" :style="{ width: row.share + '%' }"></div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Pay Frequency Breakdown</div>
                  </div>
                  <div class="p-3 p-md-4">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="height: 18px; border-radius: 4px" v-for="index in 2" :key="'frequency-sk-' + index"></div>
                     </div>
                     <div v-else-if="report.pay_frequency_breakdown.length === 0" class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-week fs-1 d-block mb-2 opacity-25"></i>
                        <div>No pay frequency activity for this filter.</div>
                     </div>
                     <div v-else>
                        <div class="share-row share-row--block" v-for="row in payFrequencyBreakdownWithShare" :key="row.pay_frequency">
                           <div class="d-flex justify-content-between align-items-center gap-2">
                              <div>
                                 <div class="fw-semibold">{{ row.label }}</div>
                                 <div class="text-muted small">{{ row.payroll_count }} payroll run{{ row.payroll_count !== 1 ? "s" : "" }}</div>
                              </div>
                              <div class="text-end">
                                 <div class="fw-semibold">₱{{ $filters.formatMoney(row.net_payroll) }}</div>
                                 <div class="text-muted small">{{ row.share }}%</div>
                              </div>
                           </div>
                           <div class="share-bar mt-2">
                              <div class="share-bar-fill" :style="{ width: row.share + '%' }"></div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Payout Methods for Selected Payrolls</div>
                        <div class="panel-card-sub">Current payout methods recorded against payrolls within the selected period.</div>
                     </div>
                  </div>
                  <div class="p-3 p-md-4">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="height: 18px; border-radius: 4px" v-for="index in 3" :key="'method-sk-' + index"></div>
                     </div>
                     <div v-else-if="report.payout_method_breakdown.length === 0" class="text-center py-4 text-muted">
                        <i class="bi bi-cash-stack fs-1 d-block mb-2 opacity-25"></i>
                        <div>No payouts recorded for this filter.</div>
                     </div>
                     <div v-else>
                        <div class="share-row share-row--block" v-for="row in payoutMethodBreakdownWithShare" :key="row.method">
                           <div class="d-flex justify-content-between align-items-center gap-2">
                              <div>
                                 <div class="fw-semibold">{{ row.label }}</div>
                                 <div class="text-muted small">{{ row.payout_count }} payout{{ row.payout_count !== 1 ? "s" : "" }}</div>
                              </div>
                              <div class="text-end">
                                 <div class="fw-semibold">₱{{ $filters.formatMoney(row.total_paid) }}</div>
                                 <div class="text-muted small">{{ row.share }}%</div>
                              </div>
                           </div>
                           <div class="share-bar mt-2">
                              <div class="share-bar-fill" :style="{ width: row.share + '%' }"></div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Payroll Trend</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 5" :key="'trend-sk-' + index"></div>
                        </div>
                     </div>
                     <div v-else-if="report.payroll_trend.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-graph-up-arrow empty-icon"></i>
                        <p class="mt-2 mb-1">No payroll trend data for this filter.</p>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Period End</th>
                                    <th>Payroll Runs</th>
                                    <th>Net Payroll</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="row in report.payroll_trend" :key="row.period_end">
                                    <td>{{ formatDate(row.period_end) }}</td>
                                    <td>{{ row.payroll_count }}</td>
                                    <td class="fw-semibold">₱{{ $filters.formatMoney(row.net_payroll) }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="row in report.payroll_trend" :key="'trend-mobile-' + row.period_end">
                              <div class="member-card-top">
                                 <div>
                                    <div class="member-card-name">{{ formatDate(row.period_end) }}</div>
                                    <div class="member-card-sub">{{ row.payroll_count }} payroll run{{ row.payroll_count !== 1 ? "s" : "" }}</div>
                                 </div>
                                 <div class="fw-semibold">₱{{ $filters.formatMoney(row.net_payroll) }}</div>
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
               <div>
                  <div class="panel-card-title">
                     Payroll Runs
                     <span class="badge-count ms-1">{{ loading ? "-" : detailPagination.total }}</span>
                  </div>
                  <div class="panel-card-sub" v-if="!loading && detailPagination.total > 0">Showing {{ detailPagination.from }}–{{ detailPagination.to }} of {{ detailPagination.total }}</div>
               </div>
            </div>
            <div class="panel-card-body p-0">
               <div v-if="loading">
                  <div class="p-3 d-none d-md-block">
                     <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 6" :key="'recent-sk-' + index"></div>
                  </div>
                  <div class="d-md-none p-3">
                     <div class="member-card" v-for="index in 4" :key="'recent-mobile-sk-' + index">
                        <div class="member-card-top">
                           <div>
                              <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                              <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div v-else-if="detailRows.length === 0" class="text-center py-5 text-muted">
                  <i class="bi bi-receipt empty-icon"></i>
                  <p class="mt-2 mb-1">No payroll records found for this filter.</p>
               </div>
               <div v-else>
                  <div class="table-responsive d-none d-md-block">
                     <table class="table table-hover table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>Employee</th>
                              <th>Period</th>
                              <th>Frequency</th>
                              <th>Status</th>
                              <th>Gross</th>
                              <th>Net</th>
                              <th>Paid Out To Date</th>
                              <th>Outstanding To Date</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-for="payroll in detailRows" :key="payroll.id">
                              <td>
                                 <div class="fw-semibold">{{ payroll.employee_name || "-" }}</div>
                                 <div class="small text-muted" v-if="payroll.approved_by_name">Approved by {{ payroll.approved_by_name }}</div>
                              </td>
                              <td>{{ payroll.period_label }}</td>
                              <td>{{ payroll.pay_frequency_label }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.statusBadge(payroll.status)]">{{ payroll.status_label }}</span>
                              </td>
                              <td>₱{{ $filters.formatMoney(payroll.gross_amount) }}</td>
                              <td class="fw-semibold">₱{{ $filters.formatMoney(payroll.net_amount) }}</td>
                              <td class="text-success">₱{{ $filters.formatMoney(payroll.total_paid) }}</td>
                              <td :class="payroll.outstanding_balance > 0 ? 'text-danger' : 'text-success'">
                                 {{ payroll.outstanding_balance > 0 ? "₱" + $filters.formatMoney(payroll.outstanding_balance) : "✓" }}
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
                  <div class="d-md-none p-3">
                     <div class="member-card" v-for="payroll in detailRows" :key="'payroll-mobile-' + payroll.id">
                        <div class="member-card-top">
                           <div>
                              <div class="member-card-name">{{ payroll.employee_name || "-" }}</div>
                              <div class="member-card-sub">{{ payroll.period_label }}</div>
                           </div>
                           <span :class="['m-badge', $filters.statusBadge(payroll.status)]">{{ payroll.status_label }}</span>
                        </div>
                        <div class="member-card-tags ps-0">
                           <span class="m-badge m-badge--plan">{{ payroll.pay_frequency_label }}</span>
                        </div>
                        <div class="small text-muted mb-2" v-if="payroll.approved_by_name">Approved by {{ payroll.approved_by_name }}</div>
                        <div class="member-card-footer flex-column align-items-start gap-1">
                           <span>Gross: ₱{{ $filters.formatMoney(payroll.gross_amount) }}</span>
                           <span>Net: ₱{{ $filters.formatMoney(payroll.net_amount) }}</span>
                           <span>Paid Out To Date: ₱{{ $filters.formatMoney(payroll.total_paid) }}</span>
                           <span :class="payroll.outstanding_balance > 0 ? 'text-danger' : 'text-success'">
                              {{ payroll.outstanding_balance > 0 ? "Outstanding To Date: ₱" + $filters.formatMoney(payroll.outstanding_balance) : "Outstanding To Date: None" }}
                           </span>
                        </div>
                     </div>
                  </div>
               </div>
               <div v-if="!loading && detailPagination.last_page > 1" class="d-flex justify-content-center py-3 border-top">
                  <nav>
                     <ul class="pagination pagination-sm mb-0">
                        <li v-for="link in detailPagination.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                           <a class="page-link" href="#" @click.prevent="goToPage(link)" v-html="link.label"></a>
                        </li>
                     </ul>
                  </nav>
               </div>
            </div>
         </div>
   </div>
</template>

<script>
import PayrollStatusBreakdownChart from "./charts/PayrollStatusBreakdownChart.vue";
import PayrollTrendChart from "./charts/PayrollTrendChart.vue";
import MultiSelect from "./vendor/MultiSelect.vue";
import dateRangePresets from "../../mixins/dateRangePresets";
import { formatDate } from "../../dates";

export default {
   components: {
      PayrollStatusBreakdownChart,
      PayrollTrendChart,
      MultiSelect,
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
            status: [],
            pay_frequency: [],
         },
         report: this.emptyReport(),
      };
   },
   computed: {
      payrollStatuses: function () {
         return [
            { value: "draft", label: "Draft" },
            { value: "approved", label: "Approved" },
            { value: "partially_paid", label: "Partially Paid" },
            { value: "paid", label: "Paid" },
            { value: "canceled", label: "Canceled" },
         ];
      },
      payFrequencies: function () {
         return [
            { value: "semi_monthly", label: "Semi Monthly" },
            { value: "monthly", label: "Monthly" },
         ];
      },
      activeFilterChips: function () {
         const chips = [];

         this.filters.status.forEach((value) => {
            const match = this.payrollStatuses.find((option) => option.value === value);
            chips.push({ key: `status:${value}`, group: "status", value: value, label: `Status: ${match ? match.label : value}` });
         });

         this.filters.pay_frequency.forEach((value) => {
            const match = this.payFrequencies.find((option) => option.value === value);
            chips.push({ key: `pay_frequency:${value}`, group: "pay_frequency", value: value, label: `Frequency: ${match ? match.label : value}` });
         });

         return chips;
      },
      hasNonDefaultFilters: function () {
         return (
            this.filters.status.length > 0 ||
            this.filters.pay_frequency.length > 0 ||
            this.filters.date_from !== this.defaultDateFrom() ||
            this.filters.date_to !== this.defaultDateTo()
         );
      },
      statusBreakdownWithShare: function () {
         const total = Number(this.report.summary.net_payroll || 0);

         return this.report.status_breakdown.map((row) => {
            const share = total > 0 ? Math.round((Number(row.net_payroll || 0) / total) * 100) : 0;

            return { ...row, share };
         });
      },
      payFrequencyBreakdownWithShare: function () {
         const total = Number(this.report.summary.net_payroll || 0);

         return this.report.pay_frequency_breakdown.map((row) => {
            const share = total > 0 ? Math.round((Number(row.net_payroll || 0) / total) * 100) : 0;

            return { ...row, share };
         });
      },
      payoutMethodBreakdownWithShare: function () {
         const total = Number(this.report.summary.total_paid || 0);

         return this.report.payout_method_breakdown.map((row) => {
            const share = total > 0 ? Math.round((Number(row.total_paid || 0) / total) * 100) : 0;

            return { ...row, share };
         });
      },
      exportUrl: function () {
         const params = new URLSearchParams();
         const reportFilters = this.report.filters || {};

         if (reportFilters.date_from) {
            params.append("date_from", reportFilters.date_from);
         }
         if (reportFilters.date_to) {
            params.append("date_to", reportFilters.date_to);
         }

         const statuses = Array.isArray(reportFilters.status) ? reportFilters.status : reportFilters.status ? [reportFilters.status] : [];
         statuses.forEach((value) => params.append("status[]", value));

         const frequencies = Array.isArray(reportFilters.pay_frequency) ? reportFilters.pay_frequency : reportFilters.pay_frequency ? [reportFilters.pay_frequency] : [];
         frequencies.forEach((value) => params.append("pay_frequency[]", value));

         return `/panel/reports/payroll/export${params.toString() ? `?${params.toString()}` : ""}`;
      },
      statCards: function () {
         return [
            {
               label: "Payroll Runs",
               value: `${this.report.summary.payroll_count}`,
               sub: this.currentPayrollScopeLabel(),
               icon: "bi-receipt",
               iconBg: "bg-secondary-soft",
               iconColor: "text-secondary",
               valueClass: "",
            },
            {
               label: "Gross Payroll",
               value: this.formatCurrencyLabel(this.report.summary.gross_payroll),
               sub: "Base wages in scope",
               icon: "bi-cash-stack",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
               valueClass: "",
            },
            {
               label: "Withholding Tax",
               value: this.formatCurrencyLabel(this.report.summary.withholding_tax),
               sub: "Payroll withholding",
               icon: "bi-percent",
               iconBg: "bg-danger-soft",
               iconColor: "text-danger",
               valueClass: "text-danger",
            },
            {
               label: "Employee Gov't Contributions",
               value: this.formatCurrencyLabel(this.report.summary.employee_government_contributions),
               sub: "Employee-side statutory deductions",
               icon: "bi-shield-check",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
               valueClass: "text-warning",
            },
            {
               label: "Net Payroll",
               value: this.formatCurrencyLabel(this.report.summary.net_payroll),
               sub: "Before payout progress",
               icon: "bi-calculator",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
               valueClass: "",
            },
            {
               label: "Paid Out To Date",
               value: this.formatCurrencyLabel(this.report.summary.total_paid),
               sub: this.payoutProgressLabel(),
               icon: "bi-cash-coin",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
               valueClass: "text-success",
            },
            {
               label: "Outstanding To Date",
               value: this.formatCurrencyLabel(this.report.summary.outstanding_balance),
               sub: this.report.summary.outstanding_balance > 0 ? "Remaining payout balance" : "Fully settled",
               icon: "bi-exclamation-circle",
               iconBg: this.report.summary.outstanding_balance > 0 ? "bg-danger-soft" : "bg-success-soft",
               iconColor: this.report.summary.outstanding_balance > 0 ? "text-danger" : "text-success",
               valueClass: this.report.summary.outstanding_balance > 0 ? "text-danger" : "text-success",
            },
         ];
      },
      chartStatusBreakdown: function () {
         return this.report.status_breakdown.filter((row) => Number(row.net_payroll) > 0);
      },
      detailPagination: function () {
         return this.report.payrolls || this.emptyPagination();
      },
      detailRows: function () {
         return this.detailPagination.data || [];
      },
   },
   mounted: function () {
      this.fetchReport();
   },
   methods: {
      formatDate,
      emptyReport: function () {
         return {
            filters: {
               date_from: null,
               date_to: null,
               status: null,
               status_label: null,
               pay_frequency: null,
               pay_frequency_label: null,
            },
            summary: {
               payroll_count: 0,
               gross_payroll: 0,
               withholding_tax: 0,
               employee_government_contributions: 0,
               employer_government_contributions: 0,
               total_deductions: 0,
               net_payroll: 0,
               total_paid: 0,
               outstanding_balance: 0,
            },
            status_breakdown: [],
            pay_frequency_breakdown: [],
            payout_method_breakdown: [],
            payroll_trend: [],
            payrolls: this.emptyPagination(),
         };
      },
      emptyPagination: function () {
         return {
            data: [],
            current_page: 1,
            per_page: 25,
            total: 0,
            last_page: 1,
            from: 0,
            to: 0,
            links: [],
         };
      },
      defaultDateFrom: function () {
         return "";
      },
      defaultDateTo: function () {
         return "";
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
         this.fetchReport(1);
      },
      resetFilters: function () {
         this.filters.date_from = this.defaultDateFrom();
         this.filters.date_to = this.defaultDateTo();
         this.filters.status = [];
         this.filters.pay_frequency = [];
         this.fetchReport(1);
      },
      clearChip: function (chip) {
         if (chip && chip.group && Array.isArray(this.filters[chip.group])) {
            this.filters[chip.group] = this.filters[chip.group].filter((value) => value !== chip.value);
         }

         this.fetchReport(1);
      },
      formatCurrencyLabel: function (amount) {
         return this.$filters.formatPeso(amount);
      },
      currentPayrollScopeLabel: function () {
         const statusLabel = this.report.filters.status_label || "Active statuses";
         const payFrequencyLabel = this.report.filters.pay_frequency_label || "All frequencies";

         return `${statusLabel} · ${payFrequencyLabel}`;
      },
      payoutProgressLabel: function () {
         if (this.report.summary.net_payroll <= 0) {
            return "No payouts released yet";
         }

         const progress = Math.min(100, Math.round((this.report.summary.total_paid / this.report.summary.net_payroll) * 100));

         return `${progress}% released so far`;
      },
      buildParams: function (page = 1) {
         return {
            date_from: this.filters.date_from || undefined,
            date_to: this.filters.date_to || undefined,
            status: this.filters.status.length ? this.filters.status : undefined,
            pay_frequency: this.filters.pay_frequency.length ? this.filters.pay_frequency : undefined,
            page: page,
            per_page: this.detailPagination.per_page || 25,
         };
      },
      fetchReport: function (page = 1) {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/reports/payroll/data", {
               params: this.buildParams(page),
            })
            .then((response) => {
               this.report = response.data;
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Unable to load payroll reports right now.";
               this.report = this.emptyReport();
            })
            .finally(() => {
               this.loading = false;
            });
      },
      goToPage: function (link) {
         if (!link.url) {
            return;
         }

         const page = parseInt(new URL(link.url).searchParams.get("page") || "1", 10);
         this.fetchReport(page);
      },
   },
};
</script>
