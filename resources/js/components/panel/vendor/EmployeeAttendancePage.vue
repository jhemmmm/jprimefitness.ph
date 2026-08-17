<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
         <template v-if="loading">
            <div class="col-6 col-md-3" v-for="i in 4" :key="'sk-s-' + i">
               <div class="stat-card">
                  <div class="skeleton-box rounded-circle flex-shrink-0" style="width: 40px; height: 40px"></div>
                  <div class="stat-card-body">
                     <div class="skeleton-box mb-2" style="height: 11px; width: 65%"></div>
                     <div class="skeleton-box" style="height: 18px; width: 40%"></div>
                  </div>
               </div>
            </div>
         </template>
         <template v-else>
            <div class="col-6 col-md-3" v-for="s in statCards" :key="s.label">
               <div class="stat-card">
                  <div class="stat-card-icon" :class="s.iconBg"><i class="bi" :class="[s.icon, s.iconColor]"></i></div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">{{ s.label }}</div>
                     <div class="stat-card-value">{{ s.value }}</div>
                  </div>
               </div>
            </div>
         </template>
      </div>

      <!-- Filters -->
      <div class="row g-2 mb-3">
         <div class="col-md-3">
            <input type="date" class="form-control" v-model="dateFrom" @change="fetchRecords(1)" placeholder="From" />
         </div>
         <div class="col-md-3">
            <input type="date" class="form-control" v-model="dateTo" @change="fetchRecords(1)" placeholder="To" />
         </div>
         <div class="col-md-6 text-end">
            <button v-if="canManage" class="btn btn-danger btn-sm" @click="openLogModal"><i class="bi bi-plus-lg me-1"></i>Log Attendance</button>
         </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading">
         <div class="skeleton-box" v-for="i in 5" :key="i" style="height: 44px; border-radius: 6px; margin-bottom: 8px"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="records.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-25"></i>
         <div>No attendance records found.</div>
      </div>

      <!-- Table (desktop) -->
      <div v-else class="d-none d-md-block">
         <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
               <tr>
                  <th>Checked In</th>
                  <th>Checked Out</th>
                  <th>Hours</th>
                  <th>Status</th>
                  <th>Source</th>
                  <th class="col-actions"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="r in records" :key="r.id" :class="{ 'table-success-soft': !r.checked_out_at }">
                  <td class="small">{{ formatDateTime(r.checked_in_at) }}</td>
                  <td>
                     <span v-if="r.checked_out_at" class="text-muted small">{{ formatDateTime(r.checked_out_at) }}</span>
                     <button v-else-if="canManage" class="btn btn-sm btn-outline-success py-0 px-2" @click="doCheckout(r)"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
                     <span v-else class="text-muted small">—</span>
                  </td>
                  <td class="small">
                     <span v-if="r.checked_out_at">{{ formatHours(r) }}</span>
                     <span v-else class="text-muted">—</span>
                  </td>
                  <td>
                     <span :class="['m-badge', $filters.statusBadge(r.checked_out_at ? 'inactive' : 'active')]">
                        {{ r.checked_out_at ? "Out" : "In" }}
                     </span>
                  </td>
                  <td>
                     <div>
                        <span :class="['m-badge', sourceBadgeClass(r.source)]">{{ sourceLabel(r.source) }}</span>
                     </div>
                     <div v-if="r.source_device_serial" class="small text-muted mt-1">{{ r.source_device_serial }}</div>
                  </td>
                  <td>
                     <button v-if="canManage" class="btn btn-sm btn-outline-danger" title="Delete" @click="confirmDelete(r)">
                        <i class="bi bi-trash tbl-icon"></i>
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
      </div>

      <!-- Mobile cards -->
      <div class="d-md-none" v-if="!loading && records.length">
         <div class="member-card" v-for="r in records" :key="'ma' + r.id">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold small">{{ formatDateTime(r.checked_in_at) }}</div>
               </div>
               <span :class="['m-badge', $filters.statusBadge(r.checked_out_at ? 'inactive' : 'active')]">
                  {{ r.checked_out_at ? "Out" : "In" }}
               </span>
            </div>
            <div class="member-card-tags">
               <span :class="['m-badge', sourceBadgeClass(r.source)]">{{ sourceLabel(r.source) }}</span>
            </div>
            <div class="member-card-footer">
               <span v-if="r.checked_out_at" class="text-muted small"><i class="bi bi-box-arrow-right me-1"></i>{{ formatDateTime(r.checked_out_at) }}</span>
               <span v-if="r.checked_out_at" class="text-muted small ms-2"><i class="bi bi-clock me-1"></i>{{ formatHours(r) }}</span>
               <button v-if="canManage && !r.checked_out_at" class="btn btn-sm btn-outline-success py-0 px-2 ms-auto" @click="doCheckout(r)"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
               <button v-if="canManage" class="btn btn-sm btn-outline-danger py-0 px-2 ms-auto" @click="confirmDelete(r)"><i class="bi bi-trash"></i></button>
            </div>
            <div v-if="r.source_device_serial" class="small text-muted mt-2 px-1">{{ r.source_device_serial }}</div>
         </div>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && pagination.lastPage > 1" class="d-flex justify-content-center pt-3 border-top">
         <panel-pagination :links="pagination.links" :current-page="currentPage" :last-page="pagination.lastPage" aria-label="Employee attendance pagination" @page-change="fetchRecords" />
      </div>

      <!-- Log Modal -->
      <div class="modal fade" tabindex="-1" ref="logModal">
         <div class="modal-dialog">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title fw-bold">Log Attendance</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                  <div class="mb-3" v-if="formError">
                     <div class="alert alert-danger py-2 small">{{ formError }}</div>
                  </div>
                  <div class="row g-3">
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Checked In <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" v-model="logForm.checked_in_at" :class="{ 'is-invalid': formErrors.checked_in_at }" />
                        <div class="invalid-feedback" v-if="formErrors.checked_in_at">{{ formErrors.checked_in_at }}</div>
                     </div>
                     <div class="col-md-6">
                        <label class="form-label form-label-sm">Checked Out</label>
                        <input type="datetime-local" class="form-control" v-model="logForm.checked_out_at" />
                     </div>
                     <div class="col-12">
                        <label class="form-label form-label-sm">Notes</label>
                        <textarea class="form-control" rows="2" v-model="logForm.notes" placeholder="Optional"></textarea>
                     </div>
                  </div>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger btn-sm" :disabled="submitting" @click="submitLog"><span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>Log</button>
               </div>
            </div>
         </div>
      </div>

      <!-- Delete Modal -->
      <div class="modal fade" tabindex="-1" ref="deleteModal">
         <div class="modal-dialog modal-sm">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold">Delete Record?</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body pt-1 text-muted small">This attendance record can be restored later from System Acitivty.</div>
               <div class="modal-footer border-0 pt-0">
                  <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-danger btn-sm" :disabled="deleting" @click="doDelete"><span v-if="deleting" class="spinner-border spinner-border-sm me-1"></span>Delete</button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDateTime, toDateTimeInputValue } from "../../../dates";

export default {
   props: {
      employee: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         submitting: false,
         deleting: false,
         records: [],
         pagination: { lastPage: 1, links: [] },
         stats: { total: 0, this_month: 0, currently_in: 0, total_hours: 0 },
         dateFrom: "",
         dateTo: "",
         currentPage: 1,
         pageError: "",
         logForm: { checked_in_at: "", checked_out_at: "", notes: "" },
         formError: "",
         formErrors: {},
         deleteTarget: null,
         logModalInst: null,
         deleteModalInst: null,
      };
   },

   mounted: function () {
      this.logModalInst = new Modal(this.$refs.logModal);
      this.deleteModalInst = new Modal(this.$refs.deleteModal);
      this.logForm.checked_in_at = toDateTimeInputValue();
      this.fetchRecords();
   },

   computed: {
      canManage: function () {
         return this.can("manage employees");
      },
      statCards: function () {
         return [
            { label: "Total", value: this.stats.total, icon: "bi-calendar-check", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "This Month", value: this.stats.this_month, icon: "bi-calendar-month", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Total Hours", value: this.stats.total_hours + "h", icon: "bi-clock-history", iconBg: "bg-warning-soft", iconColor: "text-warning" },
            { label: "Currently In", value: this.stats.currently_in, icon: "bi-door-open-fill", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },

   methods: {
      formatDateTime,
      fetchRecords: function (page = 1) {
         this.loading = true;
         this.pageError = "";
         axios
            .post(`/panel/employees/${this.employee.id}/attendance`, {
               date_from: this.dateFrom,
               date_to: this.dateTo,
               page: page,
            })
            .then((res) => {
               const d = res.data;
               this.records = d.records.data;
               this.currentPage = d.records.current_page;
               this.pagination = {
                  lastPage: d.records.last_page,
                  links: d.records.links,
               };
               this.stats = d.stats;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load attendance records."))
            .finally(() => (this.loading = false));
      },

      openLogModal: function () {
         this.logForm = { checked_in_at: toDateTimeInputValue(), checked_out_at: "", notes: "" };
         this.formError = "";
         this.formErrors = {};
         this.logModalInst.show();
      },

      submitLog: function () {
         this.submitting = true;
         this.formError = "";
         this.formErrors = {};
         axios
            .post("/panel/attendance", {
               attendee_type: "employee",
               user_id: this.employee.id,
               checked_in_at: this.logForm.checked_in_at,
               checked_out_at: this.logForm.checked_out_at || null,
               notes: this.logForm.notes || null,
            })
            .then(() => {
               this.logModalInst.hide();
               this.fetchRecords(this.currentPage);
            })
            .catch((err) => {
               if (err.response?.status === 422) {
                  const errors = err.response.data.errors || {};
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
               } else {
                  this.formError = "Something went wrong. Please try again.";
               }
            })
            .finally(() => (this.submitting = false));
      },

      doCheckout: function (r) {
         this.pageError = "";
         axios
            .post(`/panel/attendance/${r.id}/checkout`)
            .then(() => this.fetchRecords(this.currentPage))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to check out attendance record."));
      },

      confirmDelete: function (r) {
         this.deleteTarget = r;
         this.deleteModalInst.show();
      },

      doDelete: function () {
         if (!this.deleteTarget) return;
         this.deleting = true;
         this.pageError = "";
         axios
            .delete(`/panel/attendance/${this.deleteTarget.id}`)
            .then(() => {
               this.deleteModalInst.hide();
               this.deleteTarget = null;
               this.fetchRecords(this.currentPage);
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to delete attendance record."))
            .finally(() => (this.deleting = false));
      },
      formatHours: function (r) {
         if (!r.checked_in_at || !r.checked_out_at) return "—";
         const ms = new Date(r.checked_out_at).getTime() - new Date(r.checked_in_at).getTime();
         if (!isFinite(ms) || ms < 0) return "—";
         const totalMinutes = Math.round(ms / 60000);
         const h = Math.floor(totalMinutes / 60);
         const m = totalMinutes % 60;
         if (h === 0) return m + "m";
         if (m === 0) return h + "h";
         return h + "h " + m + "m";
      },
      sourceLabel: function (source) {
         return source === "hikvision" ? "Hikvision" : "Manual";
      },
      sourceBadgeClass: function (source) {
         return source === "hikvision" ? "m-badge--open" : "m-badge--draft";
      },
   },
};
</script>
