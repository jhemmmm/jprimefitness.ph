<template>
   <div class="pt-sessions-page">
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">PT Sessions</h4>
            <p class="text-muted small mb-0">{{ loading ? "Your clients" : `${stats.total} client${stats.total === 1 ? "" : "s"} • ${stats.active} with an active package` }}</p>
         </div>
      </div>

      <!-- Filters -->
      <div class="panel-card mb-4 p-3">
         <div class="row g-2">
            <div class="col-md-8">
               <div class="input-group">
                  <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted search-icon"></i></span>
                  <input type="text" class="form-control border-start-0" placeholder="Search client name…" v-model="search" @input="onSearchInput" />
               </div>
            </div>
            <div class="col-md-4">
               <select class="form-select" v-model="filter" @change="fetchClients(1)">
                  <option value="">All Clients</option>
                  <option value="active">With Active Package</option>
                  <option value="inactive">No Active Package</option>
               </select>
            </div>
         </div>
      </div>

      <!-- Table Card -->
      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Client List
               <span class="badge-count ms-1">{{ loading ? "-" : pagination.total }}</span>
            </span>
            <span class="text-muted small" v-if="!loading && pagination.total > 0"> Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} </span>
         </div>

         <!-- Loading state -->
         <div v-if="loading">
            <div class="d-none d-md-block table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th class="col-num">#</th>
                        <th>Client</th>
                        <th>Package</th>
                        <th>Sessions Left</th>
                        <th>Last Session</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="i in 8" :key="'sk' + i">
                        <td><div class="skeleton-box sk-num"></div></td>
                        <td>
                           <div class="d-flex align-items-center gap-2">
                              <div class="skeleton-box sk-avatar"></div>
                              <div class="skeleton-box sk-name"></div>
                           </div>
                        </td>
                        <td>
                           <div class="skeleton-box sk-plan-name mb-1"></div>
                           <div class="skeleton-box sk-plan-badge"></div>
                        </td>
                        <td><div class="skeleton-box sk-status"></div></td>
                        <td><div class="skeleton-box sk-joined"></div></td>
                        <td><div class="skeleton-box sk-btn"></div></td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <div class="d-md-none">
               <div class="member-card" v-for="i in 5" :key="'skm' + i">
                  <div class="member-card-top">
                     <div class="member-card-identity">
                        <div class="skeleton-box sk-avatar"></div>
                        <div class="skeleton-box sk-name"></div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Empty state -->
         <div v-else-if="clients.length === 0" class="p-5 text-center text-muted">
            <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
            <div>{{ search || filter ? "No clients match your search." : "No clients assigned to you yet." }}</div>
         </div>

         <!-- Data -->
         <div v-else>
            <div class="d-none d-md-block table-responsive">
               <table class="table table-hover table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th class="col-num">#</th>
                        <th>Client</th>
                        <th>Package</th>
                        <th>Sessions Left</th>
                        <th>Last Session</th>
                        <th class="col-actions"></th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="(client, i) in clients" :key="client.id">
                        <td class="text-muted small">{{ pagination.from + i }}</td>
                        <td>
                           <a :href="`/panel/pt-session/${client.id}`" class="text-decoration-none d-flex align-items-center gap-2">
                              <div class="member-avatar">{{ $filters.getNameInitials(client.name) }}</div>
                              <div>
                                 <div class="member-name">{{ client.name }}</div>
                                 <div class="member-email">{{ client.packages_count }} package{{ client.packages_count === 1 ? "" : "s" }}</div>
                              </div>
                           </a>
                        </td>
                        <td>
                           <template v-if="client.active_packages > 0">
                              <div class="plan-name mb-1">{{ client.current_plan || "PT Package" }}</div>
                              <span class="m-badge m-badge--plan-active">Active<span v-if="client.active_packages > 1"> ×{{ client.active_packages }}</span></span>
                           </template>
                           <span v-else class="m-badge m-badge--plan-expired">No active package</span>
                        </td>
                        <td>
                           <template v-if="client.active_packages > 0">
                              <div class="small mb-1"><span class="fw-semibold" :class="{ 'text-danger': client.remaining_sessions <= 2 }">{{ client.remaining_sessions }}</span> / {{ client.total_sessions }}</div>
                              <div class="progress" style="height: 5px; width: 110px">
                                 <div class="progress-bar" :class="$filters.sessionBarClass(client)" :style="{ width: $filters.sessionPercent(client) + '%' }"></div>
                              </div>
                           </template>
                           <span v-else class="text-muted small">-</span>
                        </td>
                        <td class="text-muted small">{{ client.last_session_at ? formatDateTime(client.last_session_at) : "-" }}</td>
                        <td>
                           <a :href="`/panel/pt-session/${client.id}`" class="btn btn-sm btn-outline-secondary" title="View sessions"><i class="bi bi-eye tbl-icon"></i></a>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <!-- Mobile cards -->
            <div class="d-md-none">
               <div class="member-card" v-for="client in clients" :key="'mc' + client.id">
                  <div class="member-card-top">
                     <a :href="`/panel/pt-session/${client.id}`" class="member-card-identity text-decoration-none">
                        <div class="member-avatar">{{ $filters.getNameInitials(client.name) }}</div>
                        <div>
                           <div class="member-card-name">{{ client.name }}</div>
                           <div class="member-card-sub">{{ client.current_plan || "No active package" }}</div>
                        </div>
                     </a>
                  </div>
                  <div class="member-card-tags">
                     <span v-if="client.active_packages > 0" class="m-badge m-badge--plan-active">{{ client.remaining_sessions }}/{{ client.total_sessions }} left</span>
                     <span v-else class="m-badge m-badge--plan-expired">No active package</span>
                  </div>
                  <div class="member-card-footer">
                     <span><i class="bi bi-clock-history me-1"></i>{{ client.last_session_at ? formatDateTime(client.last_session_at) : "No sessions yet" }}</span>
                     <span class="member-card-num">#{{ client.id }}</span>
                  </div>
               </div>
            </div>
         </div>

         <!-- Pagination -->
         <div v-if="!loading && pagination.lastPage > 1" class="d-flex justify-content-center py-3 border-top">
            <panel-pagination :links="pagination.links" :current-page="pagination.currentPage" :last-page="pagination.lastPage" aria-label="Clients pagination" @page-change="fetchClients" />
         </div>
      </div>
   </div>
</template>

<script>
import { formatDateTime } from "../../dates";
import { debounce } from "../../debounce";

export default {
   data: function () {
      return {
         loading: true,
         pageError: "",
         clients: [],
         stats: { total: 0, active: 0 },
         search: "",
         filter: "",
         pagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0, links: [] },
      };
   },
   mounted: function () {
      this.fetchClients();
   },
   methods: {
      formatDateTime,
      fetchClients: function (page = 1) {
         this.loading = true;
         this.pageError = "";
         axios
            .get("/panel/pt-sessions/list", {
               params: {
                  search: this.search || undefined,
                  filter: this.filter || undefined,
                  page,
               },
            })
            .then((res) => {
               this.clients = res.data.clients.data;
               this.pagination = {
                  currentPage: res.data.clients.current_page,
                  lastPage: res.data.clients.last_page,
                  total: res.data.clients.total,
                  from: res.data.clients.from || 0,
                  to: res.data.clients.to || 0,
                  links: res.data.clients.links,
               };
               this.stats = res.data.stats;
            })
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load clients."))
            .finally(() => (this.loading = false));
      },
      onSearchInput: debounce(function () {
         this.fetchClients();
      }),
   },
};
</script>
