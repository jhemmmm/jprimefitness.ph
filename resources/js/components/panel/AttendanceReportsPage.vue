<template>
   <div class="attendance-reports-page">
      <div class="d-flex justify-content-between align-items-center mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Attendance Reports</h4>
            <p class="text-muted small mb-0">Review attendance trends, active check-ins, and location activity for {{ currentLocationLabel }}</p>
         </div>
      </div>
      <div class="panel-card mb-4">
            <div class="panel-card-header">
               <div>
                  <div class="panel-card-title">Filters</div>
                  <div class="panel-card-sub">Use the date range and attendee type to refine this report.</div>
               </div>
            </div>
            <div class="p-3 p-md-4">
               <div class="row g-3 align-items-end">
                  <div class="col-12 col-md-4">
                     <label class="form-label">Date From</label>
                     <input type="date" class="form-control" v-model="filters.date_from" />
                  </div>
                  <div class="col-12 col-md-4">
                     <label class="form-label">Date To</label>
                     <input type="date" class="form-control" v-model="filters.date_to" />
                  </div>
                  <div class="col-12 col-md-4">
                     <label class="form-label">Attendee Type</label>
                     <select class="form-select" v-model="filters.type">
                        <option value="">All Types</option>
                        <option v-for="option in attendeeTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
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
            <div class="col-6 col-xl" v-for="stat in statCards" :key="stat.label">
               <div class="stat-card h-100">
                  <div class="stat-card-icon" :class="stat.iconBg">
                     <i class="bi" :class="[stat.icon, stat.iconColor]"></i>
                  </div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">{{ stat.label }}</div>
                     <div class="stat-card-value" v-if="loading">
                        <div class="skeleton-box" style="width: 72px; height: 18px; border-radius: 5px"></div>
                     </div>
                     <div class="stat-card-value" v-else>{{ stat.value }}</div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12">
               <attendance-daily-trend-chart v-if="!loading && report.daily_trend.length > 0" :trend="report.daily_trend"></attendance-daily-trend-chart>
               <div v-else class="panel-card h-100">
                  <div class="panel-card-header">
                     <div>
                        <div class="panel-card-title">Attendance Trend</div>
                        <div class="panel-card-sub">Daily check-ins and unique attendees for the current report filter</div>
                     </div>
                  </div>
                  <div class="panel-card-body">
                     <div v-if="loading">
                        <div class="skeleton-box" style="width: 100%; height: 240px; border-radius: 8px"></div>
                     </div>
                     <div v-else class="text-center py-5 text-muted">
                        <i class="bi bi-activity empty-icon"></i>
                        <p class="mt-2 mb-1">No attendance trend available for this filter.</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-12 col-xl-6">
               <div class="panel-card h-100">
                  <div class="panel-card-header">
                     <div class="panel-card-title">Attendance by Type</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3 d-none d-md-block">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 3" :key="'type-sk-' + index"></div>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="index in 3" :key="'type-mobile-sk-' + index">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="member-avatar">
                                       <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
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
                                    <th>Check-ins</th>
                                    <th>Unique</th>
                                    <th>Currently In</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="row in report.type_breakdown" :key="row.type">
                                    <td>
                                       <span :class="['m-badge', $filters.roleBadge(row.type)]">{{ row.label }}</span>
                                    </td>
                                    <td>{{ row.check_in_count }}</td>
                                    <td>{{ row.unique_attendees }}</td>
                                    <td>{{ row.currently_in_count }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="row in report.type_breakdown" :key="'type-mobile-' + row.type">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="member-avatar">
                                       <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ row.label }}</div>
                                       <div class="member-card-sub">{{ row.check_in_count }} check-in{{ row.check_in_count !== 1 ? "s" : "" }}</div>
                                    </div>
                                 </div>
                                 <span :class="['m-badge', $filters.roleBadge(row.type)]">{{ row.label }}</span>
                              </div>
                              <div class="member-card-footer">
                                 <span>Unique {{ row.unique_attendees }}</span>
                                 <span>Currently In {{ row.currently_in_count }}</span>
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
                     <div class="panel-card-title">Location Summary</div>
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
                                    <div class="member-avatar">
                                       <i class="bi bi-geo-alt-fill"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div v-else-if="report.location_breakdown.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-geo-alt empty-icon"></i>
                        <p class="mt-2 mb-1">No location attendance found for this filter.</p>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Location</th>
                                    <th>Check-ins</th>
                                    <th>Unique</th>
                                    <th>Currently In</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="row in report.location_breakdown" :key="row.location_id">
                                    <td>{{ row.location_name }}</td>
                                    <td>{{ row.check_in_count }}</td>
                                    <td>{{ row.unique_attendees }}</td>
                                    <td>{{ row.currently_in_count }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="row in report.location_breakdown" :key="'location-mobile-' + row.location_id">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="member-avatar">
                                       <i class="bi bi-geo-alt-fill"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ row.location_name }}</div>
                                       <div class="member-card-sub">{{ row.check_in_count }} check-in{{ row.check_in_count !== 1 ? "s" : "" }}</div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <span>Unique {{ row.unique_attendees }}</span>
                                 <span>Currently In {{ row.currently_in_count }}</span>
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
                     <div class="panel-card-title">Daily Attendance Breakdown</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3 d-none d-md-block">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 5" :key="'trend-sk-' + index"></div>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="index in 4" :key="'trend-mobile-sk-' + index">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="member-avatar">
                                       <i class="bi bi-calendar3"></i>
                                    </div>
                                    <div>
                                       <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                       <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div v-else-if="report.daily_trend.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-calendar3 empty-icon"></i>
                        <p class="mt-2 mb-1">No attendance trend available for this filter.</p>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Date</th>
                                    <th>Check-ins</th>
                                    <th>Unique Attendees</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="row in report.daily_trend" :key="row.attendance_date">
                                    <td>{{ formatDate(row.attendance_date) }}</td>
                                    <td>{{ row.check_in_count }}</td>
                                    <td>{{ row.unique_attendees }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="row in report.daily_trend" :key="'trend-mobile-' + row.attendance_date">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="member-avatar">
                                       <i class="bi bi-calendar3"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ formatDate(row.attendance_date) }}</div>
                                       <div class="member-card-sub">{{ row.check_in_count }} check-in{{ row.check_in_count !== 1 ? "s" : "" }}</div>
                                    </div>
                                 </div>
                              </div>
                              <div class="member-card-footer">
                                 <span>Unique {{ row.unique_attendees }}</span>
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
                     <div class="panel-card-title">Busiest Check-in Hours</div>
                  </div>
                  <div class="panel-card-body p-0">
                     <div v-if="loading">
                        <div class="p-3">
                           <div class="skeleton-box mb-2" style="width: 100%; height: 18px; border-radius: 4px" v-for="index in 6" :key="'hour-sk-' + index"></div>
                        </div>
                     </div>
                     <div v-else-if="report.busiest_hours.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-clock-history empty-icon"></i>
                        <p class="mt-2 mb-1">No hourly check-in pattern available.</p>
                     </div>
                     <div v-else>
                        <div class="table-responsive d-none d-md-block">
                           <table class="table table-striped align-middle mb-0 panel-table text-nowrap">
                              <thead>
                                 <tr>
                                    <th>Hour</th>
                                    <th>Check-ins</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="row in report.busiest_hours" :key="row.hour_slot">
                                    <td>{{ row.label }}</td>
                                    <td>{{ row.check_in_count }}</td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                        <div class="d-md-none p-3">
                           <div class="member-card" v-for="row in report.busiest_hours" :key="'hour-mobile-' + row.hour_slot">
                              <div class="member-card-top">
                                 <div class="member-card-identity">
                                    <div class="member-avatar">
                                       <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div>
                                       <div class="member-card-name">{{ row.label }}</div>
                                       <div class="member-card-sub">{{ row.check_in_count }} check-in{{ row.check_in_count !== 1 ? "s" : "" }}</div>
                                    </div>
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
               <div>
                  <div class="panel-card-title">Recent Attendance Records</div>
                  <div class="panel-card-sub">Latest check-ins and active attendees within the selected report scope.</div>
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
                           <div class="member-card-identity">
                              <div class="member-avatar"></div>
                              <div>
                                 <div class="skeleton-box mb-1" style="width: 120px; height: 14px; border-radius: 4px"></div>
                                 <div class="skeleton-box" style="width: 96px; height: 11px; border-radius: 4px"></div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               <div v-else-if="report.recent_records.length === 0" class="text-center py-5 text-muted">
                  <i class="bi bi-calendar-check empty-icon"></i>
                  <p class="mt-2 mb-1">No attendance records found for this filter.</p>
               </div>
               <div v-else>
                  <div class="table-responsive d-none d-md-block">
                     <table class="table table-hover table-striped align-middle mb-0 panel-table text-nowrap">
                        <thead>
                           <tr>
                              <th>Name</th>
                              <th>Type</th>
                              <th>Location</th>
                              <th>Checked In</th>
                              <th>Checked Out</th>
                              <th>Duration</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-for="record in report.recent_records" :key="record.id">
                              <td>{{ record.name }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.roleBadge(record.attendee_type)]">{{ record.attendee_type_label }}</span>
                              </td>
                              <td>{{ record.location_name }}</td>
                              <td class="small text-muted">{{ formatDateTime(record.checked_in_at) }}</td>
                              <td class="small text-muted">{{ record.checked_out_at ? formatDateTime(record.checked_out_at) : "-" }}</td>
                              <td>{{ formatDuration(record.duration_minutes) }}</td>
                              <td>
                                 <span :class="['m-badge', $filters.statusBadge(record.is_currently_in ? 'active' : 'inactive')]">
                                    {{ record.is_currently_in ? "Currently In" : "Checked Out" }}
                                 </span>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                  </div>
                  <div class="d-md-none p-3">
                     <div class="member-card" v-for="record in report.recent_records" :key="'recent-mobile-' + record.id">
                        <div class="member-card-top">
                           <div class="member-card-identity">
                              <div class="member-avatar">
                                 {{ $filters.getNameInitials(record.name) }}
                              </div>
                              <div>
                                 <div class="member-card-name">{{ record.name }}</div>
                                 <div class="member-card-sub">{{ record.location_name }}</div>
                              </div>
                           </div>
                        </div>
                        <div class="member-card-tags">
                           <span :class="['m-badge', $filters.roleBadge(record.attendee_type)]">{{ record.attendee_type_label }}</span>
                           <span :class="['m-badge', $filters.statusBadge(record.is_currently_in ? 'active' : 'inactive')]">
                              {{ record.is_currently_in ? "Currently In" : "Checked Out" }}
                           </span>
                        </div>
                        <div class="member-card-footer flex-column align-items-start gap-1">
                           <span><i class="bi bi-box-arrow-in-right me-1"></i>{{ formatDateTime(record.checked_in_at) }}</span>
                           <span v-if="record.checked_out_at"><i class="bi bi-box-arrow-right me-1"></i>{{ formatDateTime(record.checked_out_at) }}</span>
                           <span><i class="bi bi-clock me-1"></i>{{ formatDuration(record.duration_minutes) }}</span>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
   </div>
</template>

<script>
import AttendanceDailyTrendChart from "./charts/AttendanceDailyTrendChart.vue";
import { formatDate, formatDateTime, startOfCurrentMonthDate, todayDate } from "../../dates";

export default {
   components: {
      AttendanceDailyTrendChart,
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
            type: "",
         },
         report: this.emptyReport(),
      };
   },
   computed: {
      attendeeTypes: function () {
         return [
            { value: "member", label: "Members" },
            { value: "walk_in", label: "Walk-ins" },
            { value: "employee", label: "Employees" },
         ];
      },
      currentLocationLabel: function () {
         return this.report.scope.location?.name || this.businessProfile?.name || window.JPrime?.profile?.name || "this location";
      },
      averageVisitLabel: function () {
         return this.formatDuration(this.report.summary.average_visit_minutes);
      },
      exportUrl: function () {
         const params = new URLSearchParams();
         const payload = {
            date_from: this.report.filters.date_from || undefined,
            date_to: this.report.filters.date_to || undefined,
            type: this.report.filters.type || undefined,
         };

         Object.keys(payload).forEach((key) => {
            if (payload[key] !== undefined && payload[key] !== null && payload[key] !== "") {
               params.append(key, payload[key]);
            }
         });

         return `/panel/reports/attendance/export${params.toString() ? `?${params.toString()}` : ""}`;
      },
      statCards: function () {
         return [
            {
               label: "Total Check-ins",
               value: this.report.summary.total_check_ins,
               icon: "bi-calendar-check",
               iconBg: "bg-primary-soft",
               iconColor: "text-primary",
            },
            {
               label: "Unique Attendees",
               value: this.report.summary.unique_attendees,
               icon: "bi-people-fill",
               iconBg: "bg-success-soft",
               iconColor: "text-success",
            },
            {
               label: "Checked Out",
               value: this.report.summary.checked_out_count,
               icon: "bi-box-arrow-right",
               iconBg: "bg-secondary-soft",
               iconColor: "text-secondary",
            },
            {
               label: "Currently In",
               value: this.report.summary.currently_in_count,
               icon: "bi-door-open-fill",
               iconBg: "bg-warning-soft",
               iconColor: "text-warning",
            },
            {
               label: "Avg Visit",
               value: this.averageVisitLabel,
               icon: "bi-clock-history",
               iconBg: "bg-danger-soft",
               iconColor: "text-danger",
            },
         ];
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
            scope: {
               location: null,
            },
            filters: {
               date_from: null,
               date_to: null,
               type: null,
               type_label: null,
            },
            summary: {
               total_check_ins: 0,
               unique_attendees: 0,
               checked_out_count: 0,
               currently_in_count: 0,
               average_visit_minutes: 0,
            },
            type_breakdown: [],
            location_breakdown: [],
            daily_trend: [],
            busiest_hours: [],
            recent_records: [],
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
            type: this.filters.type || undefined,
         };
      },
      fetchReport: function () {
         this.loading = true;
         this.pageError = "";

         axios
            .get("/panel/reports/attendance/data", {
               params: this.buildParams(),
            })
            .then((response) => {
               this.report = response.data;
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Unable to load attendance reports right now.";
               this.report = this.emptyReport();
            })
            .finally(() => {
               this.loading = false;
            });
      },
      formatDuration: function (minutes) {
         if (!minutes && minutes !== 0) {
            return "-";
         }

         const roundedMinutes = Math.round(parseFloat(minutes));
         if (roundedMinutes <= 0) {
            return "0 min";
         }

         const hours = Math.floor(roundedMinutes / 60);
         const remainingMinutes = roundedMinutes % 60;

         if (hours === 0) {
            return `${remainingMinutes} min`;
         }

         if (remainingMinutes === 0) {
            return `${hours} hr${hours > 1 ? "s" : ""}`;
         }

         return `${hours} hr${hours > 1 ? "s" : ""} ${remainingMinutes} min`;
      },
   },
};
</script>
