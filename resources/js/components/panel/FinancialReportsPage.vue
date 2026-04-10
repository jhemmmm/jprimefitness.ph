<template>
   <div class="financial-reports-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Financial Reports</h4>
            <p class="text-muted small mb-0">Review profitability, commission load, payroll costs, and operating expenses for {{ currentLocationLabel }}</p>
         </div>
      </div>
      <div class="panel-card mb-4">
            <div class="panel-card-header">
               <div>
                  <div class="panel-card-title">Filters</div>
                  <div class="panel-card-sub">Use the date range to review this financial breakdown.</div>
               </div>
            </div>
            <div class="p-3 p-md-4">
               <div class="row g-3 align-items-end">
                  <div class="col-12 col-md-6">
                     <label class="form-label">Date From</label>
                     <input type="date" class="form-control" v-model="filters.date_from" />
                  </div>
                  <div class="col-12 col-md-6">
                     <label class="form-label">Date To</label>
                     <input type="date" class="form-control" v-model="filters.date_to" />
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
            <div class="col-6 col-xl-4" v-for="stat in statCards" :key="stat.label">
               <div class="stat-card">
                  <div class="stat-card-icon" :class="stat.iconBg">
                     <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">{{ stat.label }}</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 90px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else :class="stat.valueClass">{{ stat.amount < 0 ? "-₱" : "₱" }}{{ $filters.formatMoney(Math.abs(stat.amount)) }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="panel-card mb-4">
            <div class="panel-card-header">
               <div>
                  <div class="panel-card-title">Financial Breakdown</div>
                  <div class="panel-card-sub">This view separates revenue, PT and membership commissions, payroll wages, and manual operating expenses.</div>
               </div>
            </div>
            <div class="p-3 p-md-4">
               <div class="d-flex justify-content-between align-items-center py-2">
                  <span class="fw-semibold">Gross Revenue</span>
                  <span class="fw-semibold">₱{{ $filters.formatMoney(report.summary.gross_revenue) }}</span>
               </div>
               <div class="d-flex justify-content-between align-items-center py-2 text-danger">
                  <span>PT Commission</span>
                  <span>-₱{{ $filters.formatMoney(report.summary.pt_commission) }}</span>
               </div>
               <div class="d-flex justify-content-between align-items-center py-2 text-danger">
                  <span>Membership Commission</span>
                  <span>-₱{{ $filters.formatMoney(report.summary.membership_commission) }}</span>
               </div>
               <div class="border-top my-2"></div>
               <div class="d-flex justify-content-between align-items-center py-2 fw-semibold">
                  <span>Adjusted Revenue</span>
                  <span>₱{{ $filters.formatMoney(report.summary.adjusted_revenue) }}</span>
               </div>
               <div class="d-flex justify-content-between align-items-center py-2 text-danger">
                  <span>Payroll Wages</span>
                  <span>-₱{{ $filters.formatMoney(report.summary.payroll_wages) }}</span>
               </div>
               <div class="d-flex justify-content-between align-items-center py-2 text-danger">
                  <span>Operating Expenses</span>
                  <span>-₱{{ $filters.formatMoney(report.summary.other_operating_expenses) }}</span>
               </div>
               <div class="border-top my-2"></div>
               <div class="d-flex justify-content-between align-items-center py-2 fw-bold" :class="report.summary.net_profit >= 0 ? 'text-success' : 'text-danger'">
                  <span>Net Profit</span>
                  <span>{{ report.summary.net_profit < 0 ? "-₱" : "₱" }}{{ $filters.formatMoney(Math.abs(report.summary.net_profit)) }}</span>
               </div>
               <div class="text-muted small mt-2">Cash balance still lives in the cash ledger. This page focuses on period profitability.</div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12 col-xl-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Revenue Breakdown</div>
                  </div>
                  <div class="p-3 p-md-4">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="height: 18px; border-radius: 4px" v-for="index in 4" :key="'rev-sk-' + index"></div>
                     </div>
                     <div v-else-if="report.revenue_breakdown.length === 0" class="text-center py-4 text-muted">
                        <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 opacity-25"></i>
                        <div>No revenue records for this filter.</div>
                     </div>
                     <div v-else>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom" v-for="row in report.revenue_breakdown" :key="row.type">
                           <div>
                              <div class="fw-semibold">{{ row.label }}</div>
                              <div class="text-muted small">{{ row.transaction_count }} transaction{{ row.transaction_count !== 1 ? "s" : "" }}</div>
                           </div>
                           <div class="fw-semibold">₱{{ $filters.formatMoney(row.total_sales) }}</div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Expense Breakdown</div>
                  </div>
                  <div class="p-3 p-md-4">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="height: 18px; border-radius: 4px" v-for="index in 4" :key="'exp-sk-' + index"></div>
                     </div>
                     <div v-else>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom" v-for="row in report.expense_breakdown" :key="row.key">
                           <div>
                              <div class="fw-semibold">{{ row.label }}</div>
                              <div class="text-muted small">{{ row.description }}</div>
                           </div>
                           <div class="fw-semibold text-danger">-₱{{ $filters.formatMoney(row.amount) }}</div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3">
            <div class="col-12 col-xl-5">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Operating Expense Categories</div>
                  </div>
                  <div class="p-3 p-md-4">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="height: 18px; border-radius: 4px" v-for="index in 5" :key="'cat-sk-' + index"></div>
                     </div>
                     <div v-else-if="report.operating_expense_categories.length === 0" class="text-center py-4 text-muted">
                        <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-25"></i>
                        <div>No operating expense entries for this filter.</div>
                     </div>
                     <div v-else>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom" v-for="row in report.operating_expense_categories" :key="row.title">
                           <div>
                              <div class="fw-semibold">{{ row.title }}</div>
                              <div class="text-muted small">{{ row.entry_count }} entr{{ row.entry_count === 1 ? "y" : "ies" }}</div>
                           </div>
                           <div class="fw-semibold text-danger">-₱{{ $filters.formatMoney(row.total_amount) }}</div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-7">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Recent Operating Expenses</div>
                  </div>
                  <div class="p-3 p-md-4">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="height: 44px; border-radius: 4px" v-for="index in 5" :key="'recent-sk-' + index"></div>
                     </div>
                     <div v-else-if="report.recent_operating_expenses.length === 0" class="text-center py-4 text-muted">
                        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i>
                        <div>No recent operating expense entries for this filter.</div>
                     </div>
                     <div v-else>
                        <div class="border rounded-3 px-3 py-2 mb-2" v-for="entry in report.recent_operating_expenses" :key="entry.id">
                           <div class="d-flex justify-content-between align-items-start gap-3">
                              <div>
                                 <div class="fw-semibold">{{ entry.title }}</div>
                                 <div class="text-muted small">{{ entry.location_name || "-" }} · {{ formatDateTime(entry.occurred_at) }}</div>
                                 <div class="text-muted small" v-if="entry.description">{{ entry.description }}</div>
                                 <div class="text-muted small" v-if="entry.created_by_name">Logged by {{ entry.created_by_name }}</div>
                              </div>
                              <div class="fw-semibold text-danger flex-shrink-0">-₱{{ $filters.formatMoney(entry.amount) }}</div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
   </div>
</template>

<script>
import { formatDateTime, startOfCurrentMonthDate, todayDate } from "../../dates";

export default {
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
         },
         report: this.emptyReport(),
      };
   },
   computed: {
      currentLocationLabel: function () {
         return this.report.scope.location?.name || this.businessProfile?.name || window.JPrime?.profile?.name || "this location";
      },
      exportUrl: function () {
         const params = new URLSearchParams();
         const payload = {
            date_from: this.report.filters.date_from || undefined,
            date_to: this.report.filters.date_to || undefined,
         };

         Object.keys(payload).forEach((key) => {
            if (payload[key] !== undefined && payload[key] !== null && payload[key] !== "") {
               params.append(key, payload[key]);
            }
         });

         return `/panel/reports/financial/export${params.toString() ? `?${params.toString()}` : ""}`;
      },
      statCards: function () {
         return [
            {
               label: "Gross Revenue",
               amount: this.report.summary.gross_revenue,
               icon: "bi-cash-stack",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
               valueClass: "",
            },
            {
               label: "PT Commission",
               amount: -1 * this.report.summary.pt_commission,
               icon: "bi-person-video3",
               iconBg: "bg-danger-soft",
               iconColor: "text-danger",
               valueClass: "text-danger",
            },
            {
               label: "Membership Commission",
               amount: -1 * this.report.summary.membership_commission,
               icon: "bi-person-check",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
               valueClass: "text-danger",
            },
            {
               label: "Payroll Wages",
               amount: -1 * this.report.summary.payroll_wages,
               icon: "bi-wallet2",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
               valueClass: "text-danger",
            },
            {
               label: "Operating Expenses",
               amount: -1 * this.report.summary.other_operating_expenses,
               icon: "bi-receipt-cutoff",
               iconBg: "bg-secondary-soft",
               iconColor: "text-secondary",
               valueClass: "text-danger",
            },
            {
               label: "Net Profit",
               amount: this.report.summary.net_profit,
               icon: "bi-pie-chart-fill",
               iconBg: this.report.summary.net_profit >= 0 ? "bg-success-soft" : "bg-danger-soft",
               iconColor: this.report.summary.net_profit >= 0 ? "text-success" : "text-danger",
               valueClass: this.report.summary.net_profit >= 0 ? "text-success" : "text-danger",
            },
         ];
      },
   },
   mounted: function () {
      this.fetchReport();
   },
   methods: {
      formatDateTime,
      emptyReport: function () {
         return {
            scope: {
               location: null,
            },
            filters: {
               date_from: null,
               date_to: null,
            },
            summary: {
               gross_revenue: 0,
               pt_commission: 0,
               membership_commission: 0,
               adjusted_revenue: 0,
               payroll_wages: 0,
               other_operating_expenses: 0,
               net_profit: 0,
            },
            revenue_breakdown: [],
            expense_breakdown: [],
            operating_expense_categories: [],
            recent_operating_expenses: [],
         };
      },
      defaultDateFrom: function () {
         return startOfCurrentMonthDate();
      },
      defaultDateTo: function () {
         return todayDate();
      },
      buildParams: function () {
         return {
            date_from: this.filters.date_from || undefined,
            date_to: this.filters.date_to || undefined,
         };
      },
      fetchReport: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/reports/financial/data", {
               params: this.buildParams(),
            })
            .then((response) => {
               this.report = response.data;
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Unable to load financial reports right now.";
               this.report = this.emptyReport();
            })
            .finally(() => {
               this.loading = false;
            });
      },
   },
};
</script>
