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
                     <label class="form-label">Status</label>
                     <select class="form-select" v-model="filters.status">
                        <option value="">All Active Statuses</option>
                        <option v-for="status in payrollStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                     </select>
                  </div>
                  <div class="col-12 col-md-6 col-xl-3">
                     <label class="form-label">Pay Frequency</label>
                     <select class="form-select" v-model="filters.pay_frequency">
                        <option value="">All Frequencies</option>
                        <option v-for="frequency in payFrequencies" :key="frequency.value" :value="frequency.value">{{ frequency.label }}</option>
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
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom" v-for="row in report.status_breakdown" :key="row.status">
                           <div>
                              <span :class="['m-badge', $filters.statusBadge(row.status)]">{{ row.label }}</span>
                              <div class="text-muted small mt-1">{{ row.payroll_count }} payroll run{{ row.payroll_count !== 1 ? "s" : "" }}</div>
                           </div>
                           <div class="fw-semibold">₱{{ $filters.formatMoney(row.net_payroll) }}</div>
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
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom" v-for="row in report.pay_frequency_breakdown" :key="row.pay_frequency">
                           <div>
                              <div class="fw-semibold">{{ row.label }}</div>
                              <div class="text-muted small">{{ row.payroll_count }} payroll run{{ row.payroll_count !== 1 ? "s" : "" }}</div>
                           </div>
                           <div class="fw-semibold">₱{{ $filters.formatMoney(row.net_payroll) }}</div>
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
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom" v-for="row in report.payout_method_breakdown" :key="row.method">
                           <div>
                              <div class="fw-semibold">{{ row.label }}</div>
                              <div class="text-muted small">{{ row.payout_count }} payout{{ row.payout_count !== 1 ? "s" : "" }}</div>
                           </div>
                           <div class="fw-semibold">₱{{ $filters.formatMoney(row.total_paid) }}</div>
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
                  <div class="panel-card-title">Recent Payrolls</div>
                  <div class="panel-card-sub">Latest payroll runs, payout progress, and approval context for the selected report scope.</div>
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
               <div v-else-if="report.recent_payrolls.length === 0" class="text-center py-5 text-muted">
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
                           <tr v-for="payroll in report.recent_payrolls" :key="payroll.id">
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
                     <div class="member-card" v-for="payroll in report.recent_payrolls" :key="'recent-mobile-' + payroll.id">
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
            </div>
         </div>
   </div>
</template>

<script>
import PayrollStatusBreakdownChart from "./charts/PayrollStatusBreakdownChart.vue";
import PayrollTrendChart from "./charts/PayrollTrendChart.vue";
import { formatDate, startOfCurrentMonthDate, todayDate } from "../../dates";

export default {
   components: {
      PayrollStatusBreakdownChart,
      PayrollTrendChart,
   },
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
            status: "",
            pay_frequency: "",
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
      exportUrl: function () {
         const params = new URLSearchParams();
         const payload = {
            date_from: this.report.filters.date_from || undefined,
            date_to: this.report.filters.date_to || undefined,
            status: this.report.filters.status || undefined,
            pay_frequency: this.report.filters.pay_frequency || undefined,
         };

         Object.keys(payload).forEach((key) => {
            if (payload[key] !== undefined && payload[key] !== null && payload[key] !== "") {
               params.append(key, payload[key]);
            }
         });

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
               label: "Bonuses",
               value: this.formatCurrencyLabel(this.report.summary.total_bonus),
               sub: "Approved bonuses added",
               icon: "bi-gift",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
               valueClass: "text-success",
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
               label: "Employer Gov't Contributions",
               value: this.formatCurrencyLabel(this.report.summary.employer_government_contributions),
               sub: "Informational employer share",
               icon: "bi-building",
               iconBg: "bg-info-soft",
               iconColor: "text-info",
               valueClass: "text-info",
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
               total_bonus: 0,
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
            recent_payrolls: [],
         };
      },
      defaultDateFrom: function () {
         return startOfCurrentMonthDate();
      },
      defaultDateTo: function () {
         return todayDate();
      },
      formatCurrencyLabel: function (amount) {
         return `₱${this.$filters.formatMoney(amount || 0)}`;
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
      buildParams: function () {
         return {
            date_from: this.filters.date_from || undefined,
            date_to: this.filters.date_to || undefined,
            status: this.filters.status || undefined,
            pay_frequency: this.filters.pay_frequency || undefined,
         };
      },
      fetchReport: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/reports/payroll/data", {
               params: this.buildParams(),
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
   },
};
</script>
