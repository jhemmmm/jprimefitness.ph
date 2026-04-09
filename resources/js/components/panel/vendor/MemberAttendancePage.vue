<template>
   <div class="p-3">
      <div class="alert alert-danger py-2 small" v-if="pageError">{{ pageError }}</div>

      <div class="row g-3 mb-4">
         <template v-if="loading">
            <div class="col-4" v-for="i in 3" :key="'sk-s-' + i">
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
            <div class="col-4" v-for="stat in statCards" :key="stat.label">
               <div class="stat-card">
                  <div class="stat-card-icon" :class="stat.iconBg"><i class="bi" :class="[stat.icon, stat.iconColor]"></i></div>
                  <div class="stat-card-body">
                     <div class="stat-card-label">{{ stat.label }}</div>
                     <div class="stat-card-value">{{ stat.value }}</div>
                  </div>
               </div>
            </div>
         </template>
      </div>

      <div class="row g-2 mb-3">
         <div class="col-md-3">
            <input type="date" class="form-control" v-model="dateFrom" @change="fetchRecords(1)" placeholder="From" />
         </div>
         <div class="col-md-3">
            <input type="date" class="form-control" v-model="dateTo" @change="fetchRecords(1)" placeholder="To" />
         </div>
         <div class="col-md-6 text-end">
            <button class="btn btn-danger btn-sm" @click="openLogModal"><i class="bi bi-plus-lg me-1"></i>Log Attendance</button>
         </div>
      </div>

      <div v-if="loading">
         <div class="skeleton-box" v-for="i in 5" :key="i" style="height: 44px; border-radius: 6px; margin-bottom: 8px"></div>
      </div>

      <div v-else-if="records.length === 0" class="text-center py-5 text-muted">
         <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-25"></i>
         <div>No attendance records found.</div>
      </div>

      <div v-else class="d-none d-md-block">
         <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
               <tr>
                  <th>Checked In</th>
                  <th>Checked Out</th>
                  <th>Status</th>
                  <th class="col-actions"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="record in records" :key="record.id" :class="{ 'table-success-soft': !record.checked_out_at }">
                  <td class="small">{{ $filters.formatDateTime(record.checked_in_at) }}</td>
                  <td>
                     <span v-if="record.checked_out_at" class="text-muted small">{{ $filters.formatDateTime(record.checked_out_at) }}</span>
                     <button v-else class="btn btn-sm btn-outline-success py-0 px-2" @click="doCheckout(record)"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
                  </td>
                  <td>
                     <span :class="['m-badge', $filters.statusBadge(record.checked_out_at ? 'inactive' : 'active')]">
                        {{ record.checked_out_at ? "Out" : "In" }}
                     </span>
                  </td>
                  <td>
                     <button class="btn btn-sm btn-outline-danger" title="Delete" @click="confirmDelete(record)">
                        <i class="bi bi-trash tbl-icon"></i>
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
      </div>

      <div class="d-md-none" v-if="!loading && records.length">
         <div class="member-card" v-for="record in records" :key="'ma' + record.id">
            <div class="member-card-top">
               <div>
                  <div class="fw-semibold small">{{ $filters.formatDateTime(record.checked_in_at) }}</div>
               </div>
               <span :class="['m-badge', $filters.statusBadge(record.checked_out_at ? 'inactive' : 'active')]">
                  {{ record.checked_out_at ? "Out" : "In" }}
               </span>
            </div>
            <div class="member-card-footer">
               <span v-if="record.checked_out_at" class="text-muted small"><i class="bi bi-box-arrow-right me-1"></i>{{ $filters.formatDateTime(record.checked_out_at) }}</span>
               <button v-if="!record.checked_out_at" class="btn btn-sm btn-outline-success py-0 px-2 ms-auto" @click="doCheckout(record)"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
               <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-auto" @click="confirmDelete(record)"><i class="bi bi-trash"></i></button>
            </div>
         </div>
      </div>

      <div v-if="!loading && pagination.lastPage > 1" class="d-flex justify-content-center pt-3 border-top">
         <nav>
            <ul class="pagination pagination-sm mb-0">
               <li v-for="link in pagination.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                  <a class="page-link" href="#" @click.prevent="goToPage(link)" v-html="link.label"></a>
               </li>
            </ul>
         </nav>
      </div>

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

      <div class="modal fade" tabindex="-1" ref="deleteModal">
         <div class="modal-dialog modal-sm">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold">Delete Record?</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body pt-1 text-muted small">This attendance record will be permanently deleted.</div>
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

export default {
   props: {
      member: { type: Object, required: true },
   },

   data: function () {
      return {
         loading: true,
         submitting: false,
         deleting: false,
         records: [],
         pagination: { lastPage: 1, links: [] },
         stats: { total: 0, this_month: 0, currently_in: 0 },
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
      this.fetchRecords();
   },

   computed: {
      statCards: function () {
         return [
            { label: "Total", value: this.stats.total, icon: "bi-calendar-check", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "This Month", value: this.stats.this_month, icon: "bi-calendar-month", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Currently In", value: this.stats.currently_in, icon: "bi-door-open-fill", iconBg: "bg-danger-soft", iconColor: "text-danger" },
         ];
      },
   },

   methods: {
      fetchRecords: function (page) {
         this.loading = true;
         this.pageError = "";
         const currentPage = page || this.currentPage;
         axios
            .get(`/panel/members/${this.member.id}/attendance`, {
               params: {
                  date_from: this.dateFrom || undefined,
                  date_to: this.dateTo || undefined,
                  page: currentPage > 1 ? currentPage : undefined,
               },
            })
            .then((res) => {
               const data = res.data;
               this.records = data.records.data;
               this.currentPage = data.records.current_page;
               this.pagination = {
                  lastPage: data.records.last_page,
                  links: data.records.links,
               };
               this.stats = data.stats;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load attendance records."))
            .finally(() => (this.loading = false));
      },

      goToPage: function (link) {
         if (!link.url) return;
         const page = parseInt(new URL(link.url).searchParams.get("page") || "1");
         this.currentPage = page;
         this.fetchRecords(page);
      },

      openLogModal: function () {
         this.logForm = {
            checked_in_at: new Date().toISOString().slice(0, 16),
            checked_out_at: "",
            notes: "",
         };
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
               attendee_type: "member",
               user_id: this.member.id,
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
                  this.formErrors = Object.fromEntries(Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]));
               } else {
                  this.formError = err.response?.data?.message || "Something went wrong. Please try again.";
               }
            })
            .finally(() => (this.submitting = false));
      },

      doCheckout: function (record) {
         this.pageError = "";
         axios
            .post(`/panel/attendance/${record.id}/checkout`)
            .then(() => this.fetchRecords(this.currentPage))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to check out attendance record."));
      },

      confirmDelete: function (record) {
         this.deleteTarget = record;
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
   },
};
</script>
