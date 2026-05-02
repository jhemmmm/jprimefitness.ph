<template>
   <div class="dashboard-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Dashboard</h4>
            <p class="text-muted small mb-0">Live operations, occupancy, and recent activity for your business</p>
         </div>
      </div>

      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-xl-3" v-for="stat in visibleStatsRow1" :key="stat.key">
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
                  <div class="stat-card-sub" v-if="loading">
                     <div class="skeleton-box" style="width: 120px; height: 11px; border-radius: 4px"></div>
                  </div>
                  <div class="stat-card-sub" v-else>{{ stat.sub }}</div>
               </div>
            </div>
         </div>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-xl-3" v-for="stat in visibleStatsRow2" :key="stat.key">
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
                  <div class="stat-card-sub" v-if="loading">
                     <div class="skeleton-box" style="width: 120px; height: 11px; border-radius: 4px"></div>
                  </div>
                  <div class="stat-card-sub" v-else>{{ stat.sub }}</div>
               </div>
            </div>
         </div>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-12 col-xl-8">
               <peak-hours-chart v-if="!loading && dashboard.peak_hours.length > 0" :hours="dashboard.peak_hours"></peak-hours-chart>
               <div v-else class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Peak Hours</div>
                        <div class="panel-card-sub">Month-to-date check-ins grouped by hour</div>
                     </div>
                  </div>
                  <div class="panel-card-body">
                     <div v-if="loading">
                        <div class="skeleton-box" style="width: 100%; height: 260px; border-radius: 8px"></div>
                     </div>
                     <div v-else class="text-center py-5 text-muted">
                        <i class="bi bi-bar-chart empty-icon"></i>
                        <p class="mt-2 mb-1">No peak-hour activity found for this business.</p>
                     </div>
                  </div>
               </div>
         </div>

         <div class="col-12 col-xl-4">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Today's Operations</div>
                        <div class="panel-card-sub">Live occupancy and front-desk activity</div>
                     </div>
                  </div>
                  <div class="panel-card-body">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 56px; border-radius: 12px" v-for="index in 4" :key="'operations-sk-' + index"></div>
                     </div>
                     <div v-else class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2" v-for="item in operationsSummaryItems" :key="item.label">
                           <div>
                              <div class="fw-semibold">{{ item.label }}</div>
                              <div class="text-muted small">{{ item.sub }}</div>
                           </div>
                           <div class="fw-bold fs-5">{{ item.value }}</div>
                        </div>
                     </div>
                  </div>
               </div>
         </div>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-12 col-xl-8">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Check-ins / Attendance</div>
                        <div class="panel-card-sub">Today’s latest attendance activity for your business</div>
                     </div>
                     <a href="/panel/attendance" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading" class="p-3">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 6" :key="'check-ins-sk-' + index"></div>
                     </div>
                     <table v-else class="panel-table">
                        <thead>
                           <tr>
                              <th>Name</th>
                              <th>Type</th>
                              <th>Plan / Rate</th>
                              <th>Time In</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.check_ins_today.length === 0" class="empty-row">
                              <td colspan="4">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No check-ins recorded today.</div>
                              </td>
                           </tr>
                           <tr v-for="row in dashboard.check_ins_today" :key="row.id" v-else>
                              <td>{{ row.name }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.roleBadge(row.attendee_type)]">{{ row.attendee_type_label }}</span>
                              </td>
                              <td>{{ row.plan_or_rate }}</td>
                              <td>{{ formatTime(row.checked_in_at) }}</td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
         </div>

         <div class="col-12 col-xl-4 d-flex flex-column gap-3">
               <div class="panel-card">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Business Snapshot</div>
                        <div class="panel-card-sub">Hours, status, and profile details</div>
                     </div>
                     <a href="/panel/business/settings" class="panel-card-action">Manage <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 48px; border-radius: 12px" v-for="index in 5" :key="'business-snapshot-sk-' + index"></div>
                     </div>
                     <div v-else class="d-flex flex-column gap-2">
                        <div class="border rounded-3 px-3 py-2" v-for="item in businessSnapshotItems" :key="item.label">
                           <div class="d-flex align-items-center justify-content-between">
                              <div class="text-muted small">{{ item.label }}</div>
                              <span v-if="item.badge" :class="['m-badge', $filters.statusBadge(item.badge)]">{{ item.value }}</span>
                              <div v-else class="fw-semibold text-end">{{ item.value }}</div>
                           </div>
                           <div class="text-muted small mt-1" v-if="item.sub">{{ item.sub }}</div>
                        </div>
                     </div>
                  </div>
               </div>

               <div class="panel-card">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Trainers</div>
                     <a href="/panel/employees" class="panel-card-action">View <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 20px; border-radius: 4px" v-for="index in 4" :key="'trainer-sk-' + index"></div>
                     </div>
                     <div v-else-if="dashboard.trainers.length === 0" class="text-muted small text-center py-2">No trainers added yet.</div>
                     <div v-else class="d-flex flex-column gap-2">
                        <div class="border rounded-3 px-3 py-2" v-for="trainer in dashboard.trainers" :key="'trainer-' + trainer.id">
                           <div class="d-flex align-items-center justify-content-between">
                              <div class="fw-semibold">{{ trainer.name }}</div>
                              <span :class="['m-badge', $filters.statusBadge(trainer.status)]">{{ $filters.capitalize(trainer.status) }}</span>
                           </div>
                           <div class="text-muted small mt-1">Ready for coaching sessions</div>
                        </div>
                     </div>
                  </div>
               </div>
         </div>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-12 col-lg-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Recent Members</div>
                        <div class="panel-card-sub">Latest registrations for your business</div>
                     </div>
                     <a href="/panel/members" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading" class="p-3">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 6" :key="'recent-members-sk-' + index"></div>
                     </div>
                     <table v-else class="panel-table">
                        <thead>
                           <tr>
                              <th>Name</th>
                              <th>Plan</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.recent_members.length === 0" class="empty-row">
                              <td colspan="3">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No members yet.</div>
                              </td>
                           </tr>
                           <tr v-for="member in dashboard.recent_members" :key="member.id" v-else>
                              <td>{{ member.name }}</td>
                              <td>{{ member.plan_name }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.statusBadge(member.status)]">{{ $filters.capitalize(member.status) }}</span>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
         </div>

         <div class="col-12 col-lg-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Recent Sales</div>
                        <div class="panel-card-sub">Latest transactions for your business</div>
                     </div>
                     <a href="/panel/sales" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading" class="p-3">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 6" :key="'recent-sales-sk-' + index"></div>
                     </div>
                     <table v-else class="panel-table">
                        <thead>
                           <tr>
                              <th>Name</th>
                              <th>Item</th>
                              <th>Amount</th>
                              <th>Date</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.recent_sales.length === 0" class="empty-row">
                              <td colspan="4">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No sales recorded yet.</div>
                              </td>
                           </tr>
                           <tr v-for="sale in dashboard.recent_sales" :key="sale.id" v-else>
                              <td>{{ sale.customer_name }}</td>
                              <td>{{ sale.item_name }}</td>
                              <td>{{ formatCurrency(sale.total) }}</td>
                              <td>{{ formatDateTime(sale.sold_at) }}</td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
         </div>
      </div>

      <div class="row g-3">
         <div :class="canViewFinancialData ? 'col-12 col-lg-6' : 'col-12'">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Expiring Soon</div>
                        <div class="panel-card-sub">Memberships expiring within 7 days</div>
                     </div>
                     <a href="/panel/members" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading" class="p-3">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 6" :key="'expiring-sk-' + index"></div>
                     </div>
                     <table v-else class="panel-table">
                        <thead>
                           <tr>
                              <th>Member</th>
                              <th>Plan</th>
                              <th>Expires</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.expiring_memberships.length === 0" class="empty-row">
                              <td colspan="3">
                                 <i class="bi bi-check-circle text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No memberships expiring soon.</div>
                              </td>
                           </tr>
                           <tr v-for="membership in dashboard.expiring_memberships" :key="membership.id" v-else>
                              <td>{{ membership.member_name }}</td>
                              <td>{{ membership.plan_name }}</td>
                              <td>{{ formatDate(membership.end_date) }}</td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
         </div>

         <div v-if="canViewFinancialData" class="col-12 col-lg-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Employee Payouts</div>
                        <div class="panel-card-sub">Approved payrolls that still have an outstanding balance</div>
                     </div>
                     <a href="/panel/reports/payroll" class="panel-card-action">Manage <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading" class="p-3">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 6" :key="'payroll-sk-' + index"></div>
                     </div>
                     <table v-else class="panel-table">
                        <thead>
                           <tr>
                              <th>Employee</th>
                              <th>Role</th>
                              <th>Amount</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.pending_payrolls.length === 0" class="empty-row">
                              <td colspan="4">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No pending payouts.</div>
                              </td>
                           </tr>
                           <tr v-for="payroll in dashboard.pending_payrolls" :key="payroll.id" v-else>
                              <td>{{ payroll.employee_name }}</td>
                              <td>{{ $filters.capitalize(payroll.employee_role) }}</td>
                              <td>{{ formatCurrency(payroll.outstanding_balance) }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.statusBadge(payroll.status)]">{{ $filters.capitalize(payroll.status) }}</span>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
         </div>
      </div>
   </div>
</template>

<script>
import PeakHoursChart from "./charts/PeakHoursChart.vue";
import { formatDate, formatDateTime, formatTime as formatClockTime } from "../../dates";

export default {
   components: {
      PeakHoursChart,
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
         financialAccessState: null,
         dashboard: this.emptyDashboard(),
      };
   },
   computed: {
      canViewFinancialData: function () {
         return this.financialAccessState === true;
      },
      showFinancialStats: function () {
         return this.financialAccessState === null ? true : this.canViewFinancialData;
      },
      currentLocationLabel: function () {
         return this.dashboard.scope.location?.name || this.businessProfile?.name || window.JPrime?.profile?.name || "this location";
      },
      businessHoursLabel: function () {
         const opening = this.formatBusinessTime(this.businessProfile?.opening_time);
         const closing = this.formatBusinessTime(this.businessProfile?.closing_time);

         if (opening && closing) {
            return `${opening} - ${closing}`;
         }

         return opening || closing || "Not set";
      },
      operationsSummaryItems: function () {
         const locationLoad = this.dashboard.location_load?.[0] || {};

         return [
            {
               label: "Current Occupancy",
               value: this.formatCount(locationLoad.current_occupancy),
               sub: "People currently checked in",
            },
            {
               label: "Check-ins Today",
               value: this.formatCount(this.dashboard.stats_row_1.check_ins_today),
               sub: "Attendance activity recorded today",
            },
            {
               label: "Guest Check-ins Today",
               value: this.formatCount(this.dashboard.stats_row_2.guest_check_ins_today),
               sub: "Attendance walk-in check-ins today",
            },
            {
               label: "Active Trainers",
               value: this.formatCount(this.dashboard.stats_row_2.active_trainers),
               sub: "Coaches currently active on roster",
            },
         ];
      },
      businessSnapshotItems: function () {
         return [
            {
               label: "Business",
               value: this.currentLocationLabel,
            },
            {
               label: "Address",
               value: [this.businessProfile?.city, this.businessProfile?.province].filter(Boolean).join(", ") || "Not set",
               sub: this.businessProfile?.address || "",
            },
            {
               label: "Hours",
               value: this.businessHoursLabel,
            },
            {
               label: "Timezone",
               value: this.businessProfile?.timezone || "Not set",
            },
         ];
      },
      visibleStatsRow1: function () {
         const stats = [
            {
               key: "total_members",
               label: "Total Members",
               value: this.formatCount(this.dashboard.stats_row_1.total_members),
               sub: "registered in your business",
               icon: "bi-people-fill",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
            },
            {
               key: "check_ins_today",
               label: "Check-ins Today",
               value: this.formatCount(this.dashboard.stats_row_1.check_ins_today),
               sub: "attendance activity today",
               icon: "bi-person-check-fill",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
            },
         ];

         if (this.showFinancialStats) {
            stats.push(
               {
                  key: "revenue_today",
                  label: "Revenue Today",
                  value: this.formatCurrency(this.dashboard.stats_row_1.revenue_today),
                  sub: "recorded sales today",
                  icon: "bi-receipt",
                  iconBg: "bg-warning-soft",
                  iconColor: "text-warning",
               },
               {
                  key: "revenue_this_month",
                  label: "Revenue This Month",
                  value: this.formatCurrency(this.dashboard.stats_row_1.revenue_this_month),
                  sub: "month-to-date gross revenue",
                  icon: "bi-cash-stack",
                  iconBg: "bg-danger-soft",
                  iconColor: "text-danger",
               },
            );
         }

         return stats;
      },
      visibleStatsRow2: function () {
         const stats = [
            {
               key: "active_trainers",
               label: "Active Trainers",
               value: this.formatCount(this.dashboard.stats_row_2.active_trainers),
               sub: "coaches assigned in scope",
               icon: "bi-person-badge-fill",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
            },
            {
               key: "active_employees",
               label: "Employees",
               value: this.formatCount(this.dashboard.stats_row_2.active_employees),
               sub: "active non-coach staff",
               icon: "bi-person-workspace",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
            },
            {
               key: "guest_check_ins_today",
               label: "Guest Check-ins Today",
               value: this.formatCount(this.dashboard.stats_row_2.guest_check_ins_today),
               sub: "attendance guest activity",
               icon: "bi-person-fill-exclamation",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
            },
         ];

         if (this.showFinancialStats) {
            stats.push({
               key: "pending_payroll_balance",
               label: "Pending Payroll",
               value: this.formatCurrency(this.dashboard.stats_row_2.pending_payroll_balance),
               sub: "outstanding approved payroll balance",
               icon: "bi-wallet2",
               iconBg: "bg-danger-soft",
               iconColor: "text-danger",
            });
         }

         return stats;
      },
   },
   mounted: function () {
      this.fetchDashboard();
   },
   methods: {
      formatDate,
      formatDateTime,
      emptyDashboard: function () {
         return {
            scope: {
               location: null,
            },
            permissions: {
               can_view_financial_data: false,
            },
            stats_row_1: {
               total_members: 0,
               check_ins_today: 0,
               revenue_today: null,
               revenue_this_month: null,
            },
            stats_row_2: {
               active_trainers: 0,
               active_employees: 0,
               guest_check_ins_today: 0,
               pending_payroll_balance: null,
            },
            peak_hours: [],
            location_load: [],
            location_status: [],
            check_ins_today: [],
            trainers: [],
            recent_members: [],
            recent_sales: [],
            expiring_memberships: [],
            pending_payrolls: [],
         };
      },
      normalizeDashboard: function (payload) {
         const empty = this.emptyDashboard();

         return {
            ...empty,
            ...payload,
            scope: {
               ...empty.scope,
               ...(payload.scope || {}),
            },
            permissions: {
               ...empty.permissions,
               ...(payload.permissions || {}),
            },
            stats_row_1: {
               ...empty.stats_row_1,
               ...(payload.stats_row_1 || {}),
            },
            stats_row_2: {
               ...empty.stats_row_2,
               ...(payload.stats_row_2 || {}),
            },
            peak_hours: payload.peak_hours || [],
            location_load: payload.location_load || [],
            location_status: payload.location_status || [],
            check_ins_today: payload.check_ins_today || [],
            trainers: payload.trainers || [],
            recent_members: payload.recent_members || [],
            recent_sales: payload.recent_sales || [],
            expiring_memberships: payload.expiring_memberships || [],
            pending_payrolls: payload.pending_payrolls || [],
         };
      },
      buildParams: function () {
         return {};
      },
      fetchDashboard: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/dashboard/data", {
               params: this.buildParams(),
            })
            .then((response) => {
               this.dashboard = this.normalizeDashboard(response.data || {});
               this.financialAccessState = Boolean(this.dashboard.permissions.can_view_financial_data);
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Unable to load the dashboard right now.";
               this.dashboard = this.emptyDashboard();
            })
            .finally(() => {
               this.loading = false;
            });
      },
      formatCount: function (value) {
         return Number(value || 0).toLocaleString("en-PH");
      },
      formatCurrency: function (value) {
         return `₱${this.$filters.formatMoney(value || 0)}`;
      },
      formatTime: function (value) {
         return formatClockTime(value);
      },
      formatBusinessTime: function (value) {
         if (!value) {
            return "";
         }

         const parts = String(value).split(":");
         let hour = parseInt(parts[0], 10);
         const minute = parts[1];
         const suffix = hour >= 12 ? "PM" : "AM";

         hour = hour % 12 || 12;

         return `${hour}:${minute} ${suffix}`;
      },
   },
};
</script>
