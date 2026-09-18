<template>
   <div class="dashboard-page">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">My Dashboard</h4>
            <p class="text-muted small mb-0" v-if="isCoach">Your trainees, sessions, and pay at a glance</p>
            <p class="text-muted small mb-0" v-else>Your pay, attendance, and cash advances at a glance</p>
         </div>
         <div class="d-flex align-items-center gap-2">
            <a href="/panel/pt-sessions" class="btn btn-outline-secondary btn-sm" v-if="isCoach"><i class="bi bi-lightning-charge me-1"></i>PT Sessions</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="fetchData"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
         </div>
      </div>

      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>
      <div v-if="saved" class="alert alert-success py-2 small mb-3">Session logged.</div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-xl-3" v-for="stat in stats" :key="stat.key">
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

      <!-- Coach: trainees first -->
      <div class="row g-3 mb-4" v-if="isCoach">
         <div class="col-12 col-xl-7">
            <div class="panel-card h-100">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">My Trainees</div>
                     <div class="panel-card-sub">Members with an active PT package assigned to you</div>
                  </div>
                  <a href="/panel/pt-sessions" class="panel-card-action">Search clients & logs <i class="bi bi-arrow-right ms-1"></i></a>
               </div>
               <div class="panel-card-body p-0">
                  <div v-if="loading" class="p-3">
                     <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 4" :key="'trainee-sk-' + index"></div>
                  </div>
                  <div class="table-responsive" v-else>
                     <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>Member</th>
                              <th>Package</th>
                              <th>Sessions</th>
                              <th>Expires</th>
                              <th>Last Session</th>
                              <th class="text-end"></th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="data.trainees.length === 0" class="empty-row">
                              <td colspan="6">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No active trainees assigned to you.</div>
                              </td>
                           </tr>
                           <tr v-for="row in data.trainees" :key="row.id" v-else>
                              <td class="fw-semibold">{{ row.member_name }}</td>
                              <td>{{ row.plan_name }}</td>
                              <td style="min-width: 140px">
                                 <div class="small">{{ row.remaining_sessions }} / {{ row.total_sessions }} left</div>
                                 <div class="progress" style="height: 5px">
                                    <div class="progress-bar" :class="$filters.sessionBarClass(row)" :style="{ width: $filters.sessionPercent(row) + '%' }"></div>
                                 </div>
                              </td>
                              <td>{{ row.expires_at ? formatDate(row.expires_at) : "—" }}</td>
                              <td>{{ row.last_session_at ? formatDateTime(row.last_session_at) : "—" }}</td>
                              <td class="text-end">
                                 <button type="button" class="btn btn-outline-success btn-sm" @click="openUsageModal(row)" :disabled="row.remaining_sessions <= 0">Log session</button>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
         <div class="col-12 col-xl-5">
            <div class="panel-card h-100">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Recent Sessions</div>
                     <div class="panel-card-sub">Your last 10 logged sessions</div>
                  </div>
               </div>
               <div class="panel-card-body p-0">
                  <div v-if="loading" class="p-3">
                     <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 4" :key="'session-sk-' + index"></div>
                  </div>
                  <div class="table-responsive" v-else>
                     <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>When</th>
                              <th>Member</th>
                              <th class="text-end">Used</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="data.recent_sessions.length === 0" class="empty-row">
                              <td colspan="3">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No sessions logged yet.</div>
                              </td>
                           </tr>
                           <tr v-for="row in data.recent_sessions" :key="row.id" v-else>
                              <td>{{ formatDateTime(row.used_at) }}</td>
                              <td>
                                 <div>{{ row.member_name }}</div>
                                 <div class="text-muted small text-truncate" style="max-width: 180px" v-if="row.notes">{{ row.notes }}</div>
                              </td>
                              <td class="text-end">{{ row.sessions_used }}</td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-12 col-xl-7">
            <div class="panel-card h-100">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Recent Payrolls</div>
                     <div class="panel-card-sub">Your last 5 payroll runs</div>
                  </div>
                  <a href="/panel/my/payroll" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
               </div>
               <div class="panel-card-body p-0">
                  <div v-if="loading" class="p-3">
                     <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 4" :key="'payroll-sk-' + index"></div>
                  </div>
                  <div class="table-responsive" v-else>
                     <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>Period</th>
                              <th class="text-end">Gross</th>
                              <th class="text-end" v-if="isCoach">Commission</th>
                              <th class="text-end">Gov't contrib.</th>
                              <th class="text-end">Net</th>
                              <th>Status</th>
                              <th class="text-end"></th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="data.payrolls.length === 0" class="empty-row">
                              <td :colspan="isCoach ? 7 : 6">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No payroll yet.</div>
                              </td>
                           </tr>
                           <tr v-for="row in data.payrolls" :key="row.id" v-else>
                              <td>{{ formatDate(row.period_start) }} – {{ formatDate(row.period_end) }}</td>
                              <td class="text-end">{{ $filters.formatPeso(row.gross_amount) }}</td>
                              <td class="text-end" v-if="isCoach">{{ $filters.formatPeso(row.commission_amount) }}</td>
                              <td class="text-end text-danger">{{ row.employee_contributions_total > 0 ? "-" + $filters.formatPeso(row.employee_contributions_total) : "—" }}</td>
                              <td class="text-end fw-semibold">{{ $filters.formatPeso(row.net_amount) }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.statusBadge(row.status)]">{{ $filters.capitalize(row.status.replace("_", " ")) }}</span>
                              </td>
                              <td class="text-end">
                                 <a :href="row.payslip_url" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-text me-1"></i>Payslip</a>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
         <div class="col-12 col-xl-5">
            <div class="panel-card h-100">
               <div class="panel-card-header">
                  <div>
                     <div class="panel-card-title">Recent Attendance</div>
                     <div class="panel-card-sub">Your last 5 check-ins</div>
                  </div>
                  <a href="/panel/my/attendance" class="panel-card-action">View all <i class="bi bi-arrow-right ms-1"></i></a>
               </div>
               <div class="panel-card-body p-0">
                  <div v-if="loading" class="p-3">
                     <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 4" :key="'att-sk-' + index"></div>
                  </div>
                  <div class="table-responsive" v-else>
                     <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>Date</th>
                              <th>In</th>
                              <th>Out</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-if="data.attendance.length === 0" class="empty-row">
                              <td colspan="3">
                                 <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                                 <div class="mt-1 text-muted small">No check-ins recorded yet.</div>
                              </td>
                           </tr>
                           <tr v-for="row in data.attendance" :key="row.id" v-else>
                              <td>{{ formatDate(row.checked_in_at) }}</td>
                              <td>{{ formatTime(row.checked_in_at) }}</td>
                              <td>{{ row.checked_out_at ? formatTime(row.checked_out_at) : "—" }}</td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <log-pt-session-modal v-if="isCoach" ref="logModal" :packages="data.trainees" @logged="onLogged"></log-pt-session-modal>
   </div>
</template>

<script>
import LogPtSessionModal from "./vendor/LogPtSessionModal.vue";
import { formatDate, formatDateTime, formatTime } from "../../dates";

export default {
   components: { LogPtSessionModal },
   data: function () {
      return {
         loading: true,
         pageError: "",
         saved: false,
         data: this.emptyData(),
      };
   },
   computed: {
      isCoach: function () {
         return this.data.is_coach === true;
      },
      stats: function () {
         const s = this.data.stats;
         const latest = s.latest_payroll;
         const payrollTiles = [
            {
               key: "latest_payroll",
               label: "Latest Payroll",
               value: latest ? this.$filters.formatPeso(latest.net_amount) : "—",
               sub: latest ? `${formatDate(latest.period_start)} – ${formatDate(latest.period_end)} • ${latest.status.replace("_", " ")}` : "no payroll yet",
               icon: "bi-receipt",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
            },
            {
               key: "outstanding",
               label: "Outstanding Balance",
               value: this.$filters.formatPeso(s.outstanding_payroll_balance),
               sub: "approved pay not yet released",
               icon: "bi-cash-stack",
               iconBg: "bg-danger-soft",
               iconColor: "text-danger",
            },
         ];

         if (this.isCoach) {
            return [
               {
                  key: "trainees",
                  label: "Active Trainees",
                  value: String(s.active_trainees ?? 0),
                  sub: "members with sessions left",
                  icon: "bi-people-fill",
                  iconBg: "bg-primary-soft",
                  iconColor: "text-primary",
               },
               {
                  key: "sessions",
                  label: "Sessions This Month",
                  value: String(s.sessions_this_month ?? 0),
                  sub: "PT sessions you ran",
                  icon: "bi-lightning-charge-fill",
                  iconBg: "bg-success-soft",
                  iconColor: "text-success",
               },
               ...payrollTiles,
            ];
         }

         return [
            ...payrollTiles,
            {
               key: "attendance",
               label: "Attendance This Month",
               value: `${s.attendance_this_month.days} days`,
               sub: `${s.attendance_this_month.hours} hrs${s.currently_in ? " • currently in" : ""}`,
               icon: "bi-person-check-fill",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
            },
            {
               key: "cash_advance",
               label: "Cash Advance Balance",
               value: this.$filters.formatPeso(s.cash_advance_balance),
               sub: s.pending_cash_advance_requests ? `${s.pending_cash_advance_requests} request${s.pending_cash_advance_requests === 1 ? "" : "s"} pending approval` : "still to be repaid",
               icon: "bi-wallet2",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
            },
         ];
      },
   },
   mounted: function () {
      this.fetchData();
   },
   methods: {
      formatDate,
      formatDateTime,
      formatTime,
      emptyData: function () {
         return {
            is_coach: false,
            stats: {
               latest_payroll: null,
               outstanding_payroll_balance: 0,
               attendance_this_month: { days: 0, hours: 0 },
               currently_in: false,
               cash_advance_balance: 0,
               pending_cash_advance_requests: 0,
            },
            payrolls: [],
            attendance: [],
            trainees: [],
            recent_sessions: [],
         };
      },
      applyData: function (payload) {
         this.data = { ...this.emptyData(), ...payload, stats: { ...this.emptyData().stats, ...(payload.stats || {}) } };
      },
      fetchData: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/my-dashboard/data")
            .then((res) => this.applyData(res.data))
            .catch((err) => {
               this.pageError = err.response?.data?.message || "Failed to load your dashboard.";
            })
            .finally(() => (this.loading = false));
      },
      openUsageModal: function (row) {
         this.saved = false;
         this.$refs.logModal.open(row?.id || "");
      },
      onLogged: function () {
         this.saved = true;
         this.fetchData();
         setTimeout(() => (this.saved = false), 3000);
      },
   },
};
</script>
