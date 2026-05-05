<template>
   <div class="system-activity-page">
      <div v-if="successMessage" class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i>{{ successMessage }}</div>
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">System Activity</h4>
            <p class="text-muted small mb-0">Review shared system activity across the panel.</p>
         </div>

         <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="fetchSystemActivity(pagination.currentPage)">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
         </button>
      </div>

      <div class="panel-card mb-4">
         <div class="panel-card-header">
            <div>
               <div class="panel-card-title">Filters</div>
               <div class="panel-card-sub">Search, narrow by subject or event, and pick a date range.</div>
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
                  <select class="form-select" v-model="filters.subject_type" @change="fetchSystemActivity(1)">
                     <option value="">All Subjects</option>
                     <option v-for="option in subjectOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
               </div>
               <div class="col-6 col-md-3 col-lg-2">
                  <input type="number" min="1" class="form-control" placeholder="Subject ID" v-model="filters.subject_id" @change="fetchSystemActivity(1)" />
               </div>
               <div class="col-6 col-md-3 col-lg-2">
                  <select class="form-select" v-model="filters.event" @change="fetchSystemActivity(1)">
                     <option value="">All Events</option>
                     <option v-for="option in eventOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
               </div>
               <div class="col-6 col-md-3 col-lg-1">
                  <input type="date" class="form-control" v-model="filters.date_from" @change="fetchSystemActivity(1)" />
               </div>
               <div class="col-6 col-md-3 col-lg-1">
                  <input type="date" class="form-control" v-model="filters.date_to" @change="fetchSystemActivity(1)" />
               </div>
            </div>

            <div v-if="activeFilterChips.length > 0" class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-3 border-top">
               <span class="text-muted small">Active filters:</span>
               <span v-for="chip in activeFilterChips" :key="chip.key" class="m-badge m-badge--plan d-inline-flex align-items-center gap-1">
                  {{ chip.label }}
                  <button type="button" class="btn-close btn-close-sm ms-1" style="font-size: 0.55rem" aria-label="Clear" @click="clearChip(chip.key)"></button>
               </span>
               <button type="button" class="btn btn-link btn-sm text-danger px-2 py-0 ms-1" @click="resetFilters">Clear all</button>
            </div>
         </div>
      </div>

      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <span class="panel-card-title">
               Activity Events
               <span class="badge-count ms-1">{{ loading ? "-" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</span>
         </div>

         <div v-if="loading">
            <div class="d-none d-md-block table-responsive">
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
                     <tr v-for="index in 6" :key="'activity-sk-' + index">
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

            <div class="d-md-none">
               <div class="member-card" v-for="index in 4" :key="'activity-mobile-sk-' + index">
                  <div class="member-card-top">
                     <div>
                        <div class="skeleton-box mb-1" style="width: 150px; height: 14px; border-radius: 4px"></div>
                        <div class="skeleton-box" style="width: 118px; height: 11px; border-radius: 4px"></div>
                     </div>
                     <div class="skeleton-box" style="width: 90px; height: 22px; border-radius: 999px"></div>
                  </div>
                  <div class="member-card-tags mt-2">
                     <div class="skeleton-box" style="width: 96px; height: 22px; border-radius: 999px"></div>
                     <div class="skeleton-box" style="width: 112px; height: 22px; border-radius: 999px"></div>
                  </div>
                  <div class="skeleton-box mb-1" style="width: 170px; height: 12px; border-radius: 4px"></div>
                  <div class="skeleton-box mb-1" style="width: 100%; height: 11px; border-radius: 4px"></div>
                  <div class="skeleton-box" style="width: 78%; height: 11px; border-radius: 4px"></div>
                  <div class="member-card-footer mt-3">
                     <div class="skeleton-box" style="width: 90px; height: 12px; border-radius: 4px"></div>
                     <div class="skeleton-box" style="width: 64px; height: 12px; border-radius: 4px"></div>
                  </div>
               </div>
            </div>
         </div>

         <div v-else-if="events.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-clock-history empty-icon"></i>
            <p class="mt-2 mb-1">No activity events found</p>
            <p class="small mb-0">Try adjusting your filters</p>
         </div>

         <div v-else>
            <div class="d-none d-md-block table-responsive">
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
                           <span :class="['m-badge', systemActivityBadgeClass(event.event)]">{{ event.event_label }}</span>
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
                           <div class="d-flex justify-content-end gap-1 flex-wrap">
                              <button
                                 v-if="canRestoreEvent(event)"
                                 type="button"
                                 class="btn btn-sm btn-outline-success"
                                 :title="event.restore.label || 'Restore'"
                                 :aria-label="event.restore.label || 'Restore'"
                                 @click="openRestoreModal(event)"
                              >
                                 <i class="bi bi-arrow-counterclockwise tbl-icon"></i>
                              </button>
                              <a
                                 v-if="event.action_url"
                                 class="btn btn-sm btn-outline-secondary"
                                 :href="event.action_url"
                                 title="Open"
                                 aria-label="Open"
                              >
                                 <i class="bi bi-box-arrow-up-right tbl-icon"></i>
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

            <div class="d-md-none">
               <div class="member-card" v-for="event in events" :key="'activity-mobile-' + event.id">
                  <div class="member-card-top">
                     <div>
                        <div class="member-card-name">{{ event.title }}</div>
                        <div class="member-card-sub">{{ formatDateTime(event.occurred_at) }}</div>
                     </div>
                     <div class="d-flex align-items-start gap-2">
                        <span :class="['m-badge', systemActivityBadgeClass(event.event)]">{{ event.event_label }}</span>
                        <div v-if="hasEventActionMenu(event)" class="dropdown">
                           <button class="btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false">
                              <i class="bi bi-three-dots-vertical"></i>
                           </button>
                           <ul class="dropdown-menu dropdown-menu-end">
                              <li v-if="event.action_url">
                                 <a class="dropdown-item" :href="event.action_url"><i class="bi bi-box-arrow-up-right me-2"></i>Open</a>
                              </li>
                              <li v-if="event.action_url && canRestoreEvent(event)"><hr class="dropdown-divider" /></li>
                              <li v-if="canRestoreEvent(event)">
                                 <a class="dropdown-item text-success" href="#" @click.prevent="openRestoreModal(event)">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>{{ event.restore.label || "Restore" }}
                                 </a>
                              </li>
                           </ul>
                        </div>
                     </div>
                  </div>

                  <div class="member-card-tags">
                     <span class="m-badge m-badge--plan">{{ event.subject_type_label }}</span>
                     <span v-if="canRestoreEvent(event)" class="m-badge m-badge--active">{{ event.restore.label || "Restore" }} ready</span>
                  </div>

                  <div class="small text-muted">{{ event.subject_label || event.subject_type_label }} · #{{ event.subject_id }}</div>
                  <div class="small text-muted mt-2">{{ event.message }}</div>
                  <div v-if="event.caused_by" class="small text-muted mt-2">
                     Caused by {{ event.caused_by.subject_label || event.caused_by.subject_type }} · {{ event.caused_by.event_label }}
                  </div>

                  <div class="member-card-footer flex-column align-items-start gap-2 mt-3">
                     <div class="d-flex justify-content-between align-items-center gap-2 w-100">
                        <span>{{ event.actor_name || "System" }}</span>
                        <span class="member-card-num">#{{ event.subject_id }}</span>
                     </div>

                     <template v-if="showUnavailableRestoreState(event)">
                        <span class="text-muted small">Not recoverable</span>
                        <span class="text-muted small">{{ event.restore.reason }}</span>
                     </template>
                  </div>
               </div>
            </div>
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
                  <p class="mb-1">Restore this deleted record from System Activity?</p>
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
import dateRangePresets from "../../mixins/dateRangePresets";
import { formatDateTime } from "../../dates";

export default {
   mixins: [dateRangePresets],
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

   computed: {
      subjectTypeLabel: function () {
         const match = this.subjectOptions.find((option) => option.value === this.filters.subject_type);

         return match ? match.label : this.filters.subject_type;
      },
      eventLabel: function () {
         const match = this.eventOptions.find((option) => option.value === this.filters.event);

         return match ? match.label : this.filters.event;
      },
      activeFilterChips: function () {
         const chips = [];

         if (this.filters.search) {
            chips.push({ key: "search", label: `Search: "${this.filters.search}"` });
         }

         if (this.filters.subject_type) {
            chips.push({ key: "subject_type", label: `Subject: ${this.subjectTypeLabel}` });
         }

         if (String(this.filters.subject_id).trim() !== "") {
            chips.push({ key: "subject_id", label: `Subject ID: #${this.filters.subject_id}` });
         }

         if (this.filters.event) {
            chips.push({ key: "event", label: `Event: ${this.eventLabel}` });
         }

         return chips;
      },
      hasActiveFilters: function () {
         return (
            Boolean(this.filters.search) ||
            Boolean(this.filters.subject_type) ||
            String(this.filters.subject_id).trim() !== "" ||
            Boolean(this.filters.event) ||
            Boolean(this.filters.date_from) ||
            Boolean(this.filters.date_to)
         );
      },
   },

   mounted: function () {
      this.hydrateFiltersFromUrl();
      this.restoreModal = new Modal(this.$refs.restoreModal);
      this.fetchSystemActivity();
   },

   beforeUnmount: function () {
      clearTimeout(this.searchTimer);
   },

   methods: {
      formatDateTime,
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
         this.fetchSystemActivity(1);
      },
      resetFilters: function () {
         this.filters.subject_type = "";
         this.filters.subject_id = "";
         this.filters.event = "";
         this.filters.date_from = "";
         this.filters.date_to = "";
         this.filters.search = "";
         this.fetchSystemActivity(1);
      },
      clearChip: function (key) {
         if (key === "search") {
            this.filters.search = "";
         } else if (key === "subject_type") {
            this.filters.subject_type = "";
         } else if (key === "subject_id") {
            this.filters.subject_id = "";
         } else if (key === "event") {
            this.filters.event = "";
         }

         this.fetchSystemActivity(1);
      },
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

      fetchSystemActivity: function (page = 1) {
         this.loading = true;
         this.pageError = "";

         return axios
            .get("/panel/system-activity/list", {
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
               this.pageError = error.response?.data?.message || "Failed to load system activity.";
            })
            .finally(() => {
               this.loading = false;
            });
      },

      canRestoreEvent: function (event) {
         return Boolean(event.restore && event.restore.available);
      },

      hasEventActionMenu: function (event) {
         return Boolean(this.canRestoreEvent(event) || event.action_url);
      },

      systemActivityBadgeClass: function (eventName) {
         const normalizedEvent = String(eventName ?? "")
            .trim()
            .toLowerCase()
            .replace(/[\s-]+/g, "_");

         return (
            {
               created: "m-badge--active",
               updated: "m-badge--approved",
               deleted: "m-badge--suspended",
               restored: "m-badge--active",
               configured: "m-badge--approved",
               removed: "m-badge--suspended",
               photo_added: "m-badge--approved",
               photo_removed: "m-badge--suspended",
               plan_changed: "m-badge--approved",
               status_updated: "m-badge--approved",
               manager_assigned: "m-badge--approved",
               assigned: "m-badge--approved",
               recorded: "m-badge--open",
               checked_in: "m-badge--active",
               checked_out: "m-badge--inactive",
               stock_deducted: "m-badge--partial",
            }[normalizedEvent] ||
            this.$filters.statusBadge(normalizedEvent) ||
            "m-badge--draft"
         );
      },

      showUnavailableRestoreState: function (event) {
         return event.event === "deleted" && !this.canRestoreEvent(event) && Boolean(event.restore && event.restore.reason);
      },

      onSearchInput: function () {
         clearTimeout(this.searchTimer);
         this.searchTimer = setTimeout(() => this.fetchSystemActivity(1), 350);
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
            .post(`/panel/system-activity/${this.restoreTarget.id}/restore`)
            .then((response) => {
               this.successMessage = response.data.message || "Record restored successfully.";
               this.restoreModal.hide();
               this.restoreTarget = null;

               return this.fetchSystemActivity(this.pagination.currentPage);
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

         this.fetchSystemActivity(1);
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

         this.fetchSystemActivity(page);
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
