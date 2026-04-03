<template>
   <div class="dashboard-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Dashboard</h4>
            <p class="text-muted small mb-0">Live operations, branch load, and recent activity for {{ currentBranchLabel }}</p>
         </div>
         <button type="button" class="btn btn-danger btn-sm px-3" @click="fetchDashboard" :disabled="loading">
            <i class="bi bi-arrow-repeat me-1"></i>
            Refresh
         </button>
      </div>

      <div v-if="!branchesData.length" class="panel-card p-5 text-center text-muted">
         <i class="bi bi-speedometer2 fs-1 d-block mb-2 opacity-25"></i>
         <div>No accessible branches found.</div>
      </div>

      <template v-else>
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
                     <div class="stat-card-sub">{{ stat.sub }}</div>
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
                     <div class="stat-card-sub">{{ stat.sub }}</div>
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
                        <p class="mt-2 mb-1">No peak-hour activity found for this scope.</p>
                     </div>
                  </div>
               </div>
            </div>

            <div class="col-12 col-xl-4">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Branch Load</div>
                        <div class="panel-card-sub">Live occupancy and today’s traffic by branch</div>
                     </div>
                  </div>
                  <div class="panel-card-body">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 20px; border-radius: 4px" v-for="index in 5" :key="'branch-load-sk-' + index"></div>
                     </div>
                     <div v-else-if="dashboard.branch_load.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-diagram-3 empty-icon"></i>
                        <p class="mt-2 mb-1">No branch load data available.</p>
                     </div>
                     <div v-else class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2" v-for="row in dashboard.branch_load" :key="'branch-load-' + row.branch_id">
                           <div>
                              <div class="fw-semibold">{{ row.branch_name }}</div>
                              <div class="text-muted small">{{ row.today_check_ins }} check-in{{ row.today_check_ins !== 1 ? "s" : "" }} today</div>
                           </div>
                           <div class="text-end">
                              <div class="fw-bold fs-5">{{ row.current_occupancy }}</div>
                              <div class="text-muted small">currently in</div>
                           </div>
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
                        <div class="panel-card-sub">Today’s latest attendance activity across the current scope</div>
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
                              <th>Branch</th>
                              <th>Plan / Rate</th>
                              <th>Time In</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.check_ins_today.length === 0" class="empty-row">
                              <td colspan="5">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No check-ins recorded today.</div>
                              </td>
                           </tr>
                           <tr v-for="row in dashboard.check_ins_today" :key="row.id" v-else>
                              <td>{{ row.name }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.roleBadge(row.attendee_type)]">{{ row.attendee_type_label }}</span>
                              </td>
                              <td>{{ row.branch_name }}</td>
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
                     <div class="panel-card-title">Branches</div>
                     <a href="/panel/branches" class="panel-card-action">Manage <i class="bi bi-arrow-right ms-1"></i></a>
                  </div>
                  <div class="panel-card-body">
                     <div v-if="loading">
                        <div class="skeleton-box mb-2" style="width: 100%; height: 20px; border-radius: 4px" v-for="index in 4" :key="'branch-status-sk-' + index"></div>
                     </div>
                     <div v-else-if="dashboard.branch_status.length === 0" class="text-muted small text-center py-2">No branch summary available.</div>
                     <div v-else class="d-flex flex-column gap-2">
                        <div class="border rounded-3 px-3 py-2" v-for="branch in dashboard.branch_status" :key="'branch-status-' + branch.branch_id">
                           <div class="d-flex align-items-center justify-content-between">
                              <div class="fw-semibold">{{ branch.branch_name }}</div>
                              <span :class="['m-badge', $filters.statusBadge(branch.status)]">{{ $filters.capitalize(branch.status) }}</span>
                           </div>
                           <div class="text-muted small mt-1">{{ branch.current_occupancy }} currently in · {{ branch.today_check_ins }} check-in{{ branch.today_check_ins !== 1 ? "s" : "" }} today</div>
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
                           <div class="text-muted small mt-1">{{ trainer.branch_names.join(", ") || "No branch assigned" }}</div>
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
                        <div class="panel-card-sub">Latest registrations in the current scope</div>
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
                              <th>Branch</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.recent_members.length === 0" class="empty-row">
                              <td colspan="4">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No members yet.</div>
                              </td>
                           </tr>
                           <tr v-for="member in dashboard.recent_members" :key="member.id" v-else>
                              <td>{{ member.name }}</td>
                              <td>{{ member.plan_name }}</td>
                              <td>{{ member.branch_name }}</td>
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
                        <div class="panel-card-sub">Latest transactions in the current scope</div>
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
                              <td>{{ $filters.formatDateTime(sale.sold_at) }}</td>
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
                              <th>Branch</th>
                              <th>Expires</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="dashboard.expiring_memberships.length === 0" class="empty-row">
                              <td colspan="4">
                                 <i class="bi bi-check-circle text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No memberships expiring soon.</div>
                              </td>
                           </tr>
                           <tr v-for="membership in dashboard.expiring_memberships" :key="membership.id" v-else>
                              <td>{{ membership.member_name }}</td>
                              <td>{{ membership.plan_name }}</td>
                              <td>{{ membership.branch_name }}</td>
                              <td>{{ $filters.formatDate(membership.end_date) }}</td>
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
      </template>
   </div>
</template>

<script>
import PeakHoursChart from "./charts/PeakHoursChart.vue";

export default {
   components: {
      PeakHoursChart,
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
         dashboard: this.emptyDashboard(),
      };
   },
   computed: {
      canViewFinancialData: function () {
         return Boolean(this.dashboard.permissions.can_view_financial_data);
      },
      currentBranchLabel: function () {
         if (!this.selectedBranch) {
            return "all accessible branches";
         }

         const branch = this.branchesData.find((item) => item.id === this.selectedBranch);
         return branch ? branch.name : "the selected branch";
      },
      visibleStatsRow1: function () {
         const stats = [
            {
               key: "total_members",
               label: "Total Members",
               value: this.formatCount(this.dashboard.stats_row_1.total_members),
               sub: "assigned to the current scope",
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

         if (this.canViewFinancialData) {
            stats.push(
               {
                  key: "revenue_today",
                  label: "Revenue Today",
                  value: this.formatCurrency(this.dashboard.stats_row_1.revenue_today),
                  sub: "sales plus direct walk-ins today",
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
               }
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
               key: "walk_ins_today",
               label: "Walk-ins Today",
               value: this.formatCount(this.dashboard.stats_row_2.walk_ins_today),
               sub: "front-desk walk-in activity",
               icon: "bi-person-fill-exclamation",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
            },
         ];

         if (this.canViewFinancialData) {
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
      this.selectedBranch = this.resolveSelectedBranch();
      this.fetchDashboard();
   },
   methods: {
      emptyDashboard: function () {
         return {
            scope: {
               branch: null,
               is_all_branches: true,
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
               walk_ins_today: 0,
               pending_payroll_balance: null,
            },
            peak_hours: [],
            branch_load: [],
            branch_status: [],
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
            branch_load: payload.branch_load || [],
            branch_status: payload.branch_status || [],
            check_ins_today: payload.check_ins_today || [],
            trainers: payload.trainers || [],
            recent_members: payload.recent_members || [],
            recent_sales: payload.recent_sales || [],
            expiring_memberships: payload.expiring_memberships || [],
            pending_payrolls: payload.pending_payrolls || [],
         };
      },
      resolveSelectedBranch: function () {
         const storedBranchId = localStorage.getItem("selectedBranch");

         if (!storedBranchId || storedBranchId === "null") {
            return null;
         }

         const branchId = parseInt(storedBranchId, 10);
         if (Number.isNaN(branchId)) {
            return null;
         }

         return this.branchesData.some((branch) => branch.id === branchId) ? branchId : null;
      },
      buildParams: function () {
         return {
            branch: this.selectedBranch || undefined,
         };
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
         if (!value) {
            return "—";
         }

         return new Date(value).toLocaleTimeString("en-PH", {
            hour: "numeric",
            minute: "2-digit",
         });
      },
   },
};
</script>
