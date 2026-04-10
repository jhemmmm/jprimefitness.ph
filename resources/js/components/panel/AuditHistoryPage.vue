<template>
   <div class="audit-history-page">
      <div v-if="successMessage" class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i>{{ successMessage }}</div>
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Audit History</h4>
            <p class="text-muted small mb-0">Review shared audit trails across the panel.</p>
         </div>

         <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="fetchAuditHistory(pagination.currentPage)">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
         </button>
      </div>

      <div class="panel-card mb-4 p-3">
         <div class="row g-2 align-items-center">
            <div class="col-12 col-lg-4">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0">
                     <i class="bi bi-search text-muted search-icon"></i>
                  </span>
                  <input type="text" class="form-control border-start-0" placeholder="Search event, subject, or actor..." v-model="filters.search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
               <select class="form-select" v-model="filters.subject_type" @change="fetchAuditHistory(1)">
                  <option value="">All Subjects</option>
                  <option v-for="option in subjectOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
               </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
               <input type="number" min="1" class="form-control" placeholder="Subject ID" v-model="filters.subject_id" @change="fetchAuditHistory(1)" />
            </div>
            <div class="col-6 col-md-3 col-lg-2">
               <select class="form-select" v-model="filters.event" @change="fetchAuditHistory(1)">
                  <option value="">All Events</option>
                  <option v-for="option in eventOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
               </select>
            </div>
            <div class="col-6 col-md-3 col-lg-1">
               <input type="date" class="form-control" v-model="filters.date_from" @change="fetchAuditHistory(1)" />
            </div>
            <div class="col-6 col-md-3 col-lg-1">
               <input type="date" class="form-control" v-model="filters.date_to" @change="fetchAuditHistory(1)" />
            </div>
         </div>
      </div>

      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <span class="panel-card-title">
               Audit Events
               <span class="badge-count ms-1">{{ loading ? "-" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</span>
         </div>

         <div v-if="loading" class="table-responsive">
            <table class="table table-striped align-middle mb-0 panel-table">
               <thead>
                  <tr>
                     <th>Occurred At</th>
                     <th>Event</th>
                     <th>Subject</th>
                     <th>Summary</th>
                     <th>Actor</th>
                     <th class="col-actions"></th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="index in 6" :key="'audit-sk-' + index">
                     <td><div class="skeleton-box" style="width: 132px; height: 12px; border-radius: 4px"></div></td>
                     <td><div class="skeleton-box" style="width: 88px; height: 22px; border-radius: 999px"></div></td>
                     <td>
                        <div class="skeleton-box mb-1" style="width: 160px; height: 14px; border-radius: 4px"></div>
                        <div class="skeleton-box" style="width: 84px; height: 11px; border-radius: 4px"></div>
                     </td>
                     <td>
                        <div class="skeleton-box mb-1" style="width: 180px; height: 14px; border-radius: 4px"></div>
                        <div class="skeleton-box mb-1" style="width: 240px; height: 11px; border-radius: 4px"></div>
                        <div class="skeleton-box" style="width: 160px; height: 11px; border-radius: 4px"></div>
                     </td>
                     <td><div class="skeleton-box" style="width: 96px; height: 12px; border-radius: 4px"></div></td>
                     <td><div class="skeleton-box ms-auto" style="width: 58px; height: 32px; border-radius: 6px"></div></td>
                  </tr>
               </tbody>
            </table>
         </div>

         <div v-else-if="events.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-clock-history empty-icon"></i>
            <p class="mt-2 mb-1">No audit events found</p>
            <p class="small mb-0">Try adjusting your filters</p>
         </div>

         <div v-else class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 panel-table">
               <thead>
                  <tr>
                     <th scope="col" :aria-sort="ariaSort('occurred_at')">
                        <button type="button" :class="sortButtonClass('occurred_at')" @click="toggleSort('occurred_at')">
                           <span>Occurred At</span>
                           <i :class="sortIcon('occurred_at')"></i>
                        </button>
                     </th>
                     <th scope="col" :aria-sort="ariaSort('event')">
                        <button type="button" :class="sortButtonClass('event')" @click="toggleSort('event')">
                           <span>Event</span>
                           <i :class="sortIcon('event')"></i>
                        </button>
                     </th>
                     <th scope="col" :aria-sort="ariaSort('subject_label')">
                        <button type="button" :class="sortButtonClass('subject_label')" @click="toggleSort('subject_label')">
                           <span>Subject</span>
                           <i :class="sortIcon('subject_label')"></i>
                        </button>
                     </th>
                     <th scope="col" :aria-sort="ariaSort('title')">
                        <button type="button" :class="sortButtonClass('title')" @click="toggleSort('title')">
                           <span>Summary</span>
                           <i :class="sortIcon('title')"></i>
                        </button>
                     </th>
                     <th scope="col" :aria-sort="ariaSort('actor_name')">
                        <button type="button" :class="sortButtonClass('actor_name')" @click="toggleSort('actor_name')">
                           <span>Actor</span>
                           <i :class="sortIcon('actor_name')"></i>
                        </button>
                     </th>
                     <th class="col-actions"></th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="event in events" :key="event.id">
                     <td class="text-muted small text-nowrap">{{ formatDateTime(event.occurred_at) }}</td>
                     <td>
                        <span class="badge rounded-pill text-bg-light border">{{ event.event_label }}</span>
                     </td>
                     <td>
                        <div class="member-name">{{ event.subject_label || event.subject_type_label }}</div>
                        <div class="text-muted small">#{{ event.subject_id }}</div>
                     </td>
                     <td>
                        <div class="member-name">{{ event.title }}</div>
                        <div class="text-muted small">{{ event.message }}</div>
                        <div v-if="event.caused_by" class="text-muted small mt-1">
                           Caused by {{ event.caused_by.subject_label || event.caused_by.subject_type }} · {{ event.caused_by.event_label }}
                        </div>
                     </td>
                     <td class="text-muted small">{{ event.actor_name || "-" }}</td>
                     <td class="col-actions">
                        <div class="d-flex flex-column align-items-end gap-1">
                           <button v-if="canRestoreEvent(event)" type="button" class="btn btn-sm btn-outline-success" @click="openRestoreModal(event)">
                              {{ event.restore.label || "Restore" }}
                           </button>
                           <a v-if="event.action_url" class="btn btn-sm btn-outline-secondary" :href="event.action_url">
                              Open
                           </a>
                           <template v-if="showUnavailableRestoreState(event)">
                              <span class="text-muted small text-end">Not recoverable</span>
                              <span class="text-muted small text-end">{{ event.restore.reason }}</span>
                           </template>
                           <span v-else-if="!event.action_url && !canRestoreEvent(event)" class="text-muted small">-</span>
                        </div>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>

         <div v-if="!loading && pagination.lastPage > 1" class="d-flex justify-content-center py-3 border-top">
            <nav>
               <ul class="pagination pagination-sm mb-0">
                  <li v-for="link in pagination.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                     <a class="page-link" href="#" @click.prevent="goToPage(link)" v-html="link.label"></a>
                  </li>
               </ul>
            </nav>
         </div>
      </div>

      <div class="modal fade" tabindex="-1" ref="restoreModal">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
               <div class="modal-header border-0 pb-0">
                  <h5 class="modal-title fw-bold text-success"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore Record</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body" v-if="restoreTarget">
                  <div v-if="restoreError" class="alert alert-danger py-2 small mb-3">{{ restoreError }}</div>
                  <p class="mb-1">Restore this deleted record from Audit History?</p>
                  <p class="fw-semibold mb-0">{{ restoreTarget.subject_label || restoreTarget.subject_type_label }}</p>
                  <p class="text-muted small mb-0">#{{ restoreTarget.subject_id }} · {{ restoreTarget.event_label }}</p>
               </div>
               <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="restoring">Cancel</button>
                  <button type="button" class="btn btn-success px-4" @click="confirmRestore" :disabled="restoring">
                     <span v-if="restoring" class="spinner-border spinner-border-sm me-1 spinner-sm-fixed"></span>
                     Restore
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
</template>

<script>
import { Modal } from "bootstrap";
import { formatDateTime } from "../../dates";

export default {
   data: function () {
      return {
         loading: false,
         restoring: false,
         pageError: "",
         restoreError: "",
         searchTimer: null,
         restoreModal: null,
         restoreTarget: null,
         successMessage: "",
         events: [],
         filters: {
            subject_type: "",
            subject_id: "",
            event: "",
            date_from: "",
            date_to: "",
            search: "",
         },
         pagination: {
            currentPage: 1,
            from: 0,
            to: 0,
            total: 0,
            lastPage: 1,
            links: [],
         },
         sort: {
            by: "occurred_at",
            direction: "desc",
         },
         subjectOptions: [],
         eventOptions: [],
      };
   },

   mounted: function () {
      this.hydrateFiltersFromUrl();
      this.restoreModal = new Modal(this.$refs.restoreModal);
      this.fetchAuditHistory();
   },

   beforeUnmount: function () {
      clearTimeout(this.searchTimer);
   },

   methods: {
      formatDateTime,
      hydrateFiltersFromUrl: function () {
         const params = new URLSearchParams(window.location.search);
         const allowedSortColumns = ["occurred_at", "event", "subject_label", "title", "actor_name"];
         const requestedSortBy = params.get("sort_by") || "occurred_at";
         const requestedSortDirection = params.get("sort_direction") || "desc";

         this.filters.subject_type = params.get("subject_type") || "";
         this.filters.subject_id = params.get("subject_id") || "";
         this.filters.event = params.get("event") || "";
         this.filters.date_from = params.get("date_from") || "";
         this.filters.date_to = params.get("date_to") || "";
         this.filters.search = params.get("search") || "";
         this.sort.by = allowedSortColumns.includes(requestedSortBy) ? requestedSortBy : "occurred_at";
         this.sort.direction = ["asc", "desc"].includes(requestedSortDirection) ? requestedSortDirection : "desc";
         this.pagination.currentPage = Number(params.get("page") || 1);
      },

      fetchAuditHistory: function (page = 1) {
         this.loading = true;
         this.pageError = "";

         return axios
            .get("/panel/audit-history/list", {
               params: {
                  subject_type: this.filters.subject_type || undefined,
                  subject_id: this.filters.subject_id || undefined,
                  event: this.filters.event || undefined,
                  date_from: this.filters.date_from || undefined,
                  date_to: this.filters.date_to || undefined,
                  search: this.filters.search || undefined,
                  sort_by: this.sort.by,
                  sort_direction: this.sort.direction,
                  per_page: 20,
                  page,
               },
            })
            .then((response) => {
               const payload = response.data.events || {};
               const meta = response.data.meta || {};

               this.events = payload.data || [];
               this.pagination = {
                  currentPage: payload.current_page || 1,
                  from: payload.from || 0,
                  to: payload.to || 0,
                  total: payload.total || 0,
                  lastPage: payload.last_page || 1,
                  links: payload.links || [],
               };
               this.subjectOptions = meta.subject_types || [];
               this.eventOptions = meta.event_options || [];
               this.syncUrl();
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to load audit history.";
            })
            .finally(() => {
               this.loading = false;
            });
      },

      canRestoreEvent: function (event) {
         return Boolean(event.restore && event.restore.available);
      },

      showUnavailableRestoreState: function (event) {
         return event.event === "deleted" && !this.canRestoreEvent(event) && Boolean(event.restore && event.restore.reason);
      },

      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchAuditHistory(1), 350);
      },

      openRestoreModal: function (event) {
         this.restoreTarget = event;
         this.restoreError = "";
         this.restoreModal.show();
      },

      confirmRestore: function () {
         if (!this.restoreTarget) {
            return;
         }

         this.restoring = true;
         this.restoreError = "";
         this.pageError = "";

         axios
            .post(`/panel/audit-history/${this.restoreTarget.id}/restore`)
            .then((response) => {
               this.successMessage = response.data.message || "Record restored successfully.";
               this.restoreModal.hide();
               this.restoreTarget = null;

               return this.fetchAuditHistory(this.pagination.currentPage);
            })
            .catch((error) => {
               this.restoreError = error.response?.data?.errors?.restore?.[0] || error.response?.data?.message || "Failed to restore record.";
            })
            .finally(() => {
               this.restoring = false;
            });
      },

      toggleSort: function (column) {
         if (this.sort.by === column) {
            this.sort.direction = this.sort.direction === "asc" ? "desc" : "asc";
         } else {
            this.sort.by = column;
            this.sort.direction = column === "occurred_at" ? "desc" : "asc";
         }

         this.fetchAuditHistory(1);
      },

      sortButtonClass: function (column) {
         return [
            "btn",
            "btn-link",
            "btn-sm",
            "px-0",
            "py-0",
            "text-decoration-none",
            "d-inline-flex",
            "align-items-center",
            "gap-1",
            this.sort.by === column ? "text-dark fw-semibold" : "text-muted",
         ];
      },

      sortIcon: function (column) {
         if (this.sort.by !== column) {
            return "bi bi-arrow-down-up";
         }

         return this.sort.direction === "asc" ? "bi bi-caret-up-fill" : "bi bi-caret-down-fill";
      },

      ariaSort: function (column) {
         if (this.sort.by !== column) {
            return "none";
         }

         return this.sort.direction === "asc" ? "ascending" : "descending";
      },

      goToPage: function (link) {
         if (!link.url) {
            return;
         }

         const url = new URL(link.url);
         const page = Number(url.searchParams.get("page") || 1);

         this.fetchAuditHistory(page);
      },

      syncUrl: function () {
         const params = new URLSearchParams();

         Object.entries(this.filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== "") {
               params.set(key, String(value));
            }
         });

         if (this.pagination.currentPage > 1) {
            params.set("page", String(this.pagination.currentPage));
         }

         if (this.sort.by !== "occurred_at" || this.sort.direction !== "desc") {
            params.set("sort_by", this.sort.by);
            params.set("sort_direction", this.sort.direction);
         }

         const query = params.toString();
         const url = query ? `${window.location.pathname}?${query}` : window.location.pathname;

         window.history.replaceState({}, "", url);
      },
   },
};
</script>
