<template>
   <div class="pt-session-detail-page">
      <div class="d-flex align-items-center justify-content-between mb-4">
         <div>
            <h4 class="fw-bold mb-0">Client Details</h4>
            <div class="text-muted small">Packages assigned to you and every session logged for this client</div>
         </div>
         <a href="/panel/pt-sessions" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
      </div>

      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>
      <div v-if="saved" class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i>Session logged.</div>

      <!-- Header card -->
      <div class="panel-card p-4 mb-4">
         <div class="d-flex align-items-start gap-3 flex-wrap">
            <div class="member-avatar employee-avatar-xl flex-shrink-0">{{ $filters.getNameInitials(localClient.name) }}</div>
            <div class="flex-grow-1 min-w-0">
               <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <h4 class="fw-bold mb-0">{{ localClient.name }}</h4>
                  <span :class="['m-badge', $filters.statusBadge(localClient.status)]">{{ $filters.capitalize(localClient.status) }}</span>
                  <span v-if="activePackage" class="m-badge m-badge--plan-active">{{ activePackage.plan_name }} • {{ activePackage.remaining_sessions }}/{{ activePackage.total_sessions }} left</span>
                  <span v-else-if="!loading" class="m-badge m-badge--plan-expired">No active package</span>
               </div>
               <div class="d-flex gap-3 flex-wrap small text-muted">
                  <span v-if="localClient.client_since"><i class="bi bi-calendar3 me-1"></i>Client since {{ formatDate(localClient.client_since) }}</span>
                  <span><i class="bi bi-lightning-charge me-1"></i>{{ sessionsLogged }} session{{ sessionsLogged === 1 ? "" : "s" }} logged</span>
                  <span v-if="usages.length"><i class="bi bi-clock-history me-1"></i>Last session {{ formatDateTime(usages[0].used_at) }}</span>
               </div>
            </div>
            <button type="button" class="btn btn-danger px-3 flex-shrink-0" @click="openLogModal(activePackage?.id)" :disabled="loggablePackages.length === 0">
               <i class="bi bi-plus-lg me-1"></i>
               Log Session
            </button>
         </div>
      </div>

      <!-- Packages -->
      <div class="panel-card mb-4">
         <div class="panel-card-header">
            <div>
               <div class="panel-card-title">Packages <span class="badge-count ms-1">{{ loading ? "-" : packages.length }}</span></div>
               <div class="panel-card-sub">PT packages for this client that are assigned to you</div>
            </div>
         </div>
         <div class="panel-card-body">
            <div v-if="loading" class="row g-3">
               <div class="col-md-6 col-xl-4" v-for="i in 2" :key="'pk-sk' + i">
                  <div class="skeleton-box" style="height: 120px; border-radius: 8px"></div>
               </div>
            </div>
            <div v-else-if="packages.length === 0" class="text-center py-4 text-muted small">
               <i class="bi bi-inbox d-block mb-1" style="font-size: 1.5rem"></i>
               No packages assigned to you for this client.
            </div>
            <div v-else class="row g-3">
               <div class="col-md-6 col-xl-4" v-for="pkg in packages" :key="pkg.id">
                  <div class="border rounded p-3 h-100">
                     <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                           <div class="fw-semibold">{{ pkg.plan_name || "PT Package" }}</div>
                           <div class="text-muted small">{{ formatDate(pkg.assigned_at) }}<span v-if="pkg.expires_at"> – {{ formatDate(pkg.expires_at) }}</span></div>
                        </div>
                        <span :class="['m-badge', $filters.statusBadge(pkg.status)]">{{ $filters.capitalize(pkg.status) }}</span>
                     </div>
                     <div class="small mb-1"><span class="fw-semibold" :class="{ 'text-danger': pkg.status === 'active' && pkg.remaining_sessions <= 2 }">{{ pkg.remaining_sessions }}</span> / {{ pkg.total_sessions }} sessions left</div>
                     <div class="progress mb-3" style="height: 6px">
                        <div class="progress-bar" :class="$filters.sessionBarClass(pkg)" :style="{ width: $filters.sessionPercent(pkg) + '%' }"></div>
                     </div>
                     <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">{{ pkg.usages.length }} log{{ pkg.usages.length === 1 ? "" : "s" }}</span>
                        <button type="button" class="btn btn-outline-success btn-sm" v-if="isLoggable(pkg)" @click="openLogModal(pkg.id)"><i class="bi bi-plus-lg me-1"></i>Log session</button>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <!-- Session logs -->
      <div class="panel-card">
         <div class="panel-card-header d-flex justify-content-between align-items-center">
            <span class="panel-card-title">
               Session Logs
               <span class="badge-count ms-1">{{ loading ? "-" : usages.length }}</span>
            </span>
         </div>
         <div v-if="loading" class="p-3">
            <div class="skeleton-box mb-2" style="width: 100%; height: 28px; border-radius: 4px" v-for="index in 5" :key="'log-sk-' + index"></div>
         </div>
         <div v-else>
            <div class="d-none d-md-block table-responsive">
               <table class="table table-striped align-middle mb-0 panel-table">
                  <thead>
                     <tr>
                        <th>When</th>
                        <th>Package</th>
                        <th>Sessions</th>
                        <th>Coach</th>
                        <th>Confirmed By</th>
                        <th>Notes</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-if="usages.length === 0" class="empty-row">
                        <td colspan="6">
                           <i class="bi bi-inbox text-muted" style="font-size: 1.5rem"></i>
                           <div class="mt-1 text-muted small">No sessions logged for this client yet.</div>
                        </td>
                     </tr>
                     <tr v-for="row in usages" :key="row.id" v-else>
                        <td class="text-nowrap">{{ formatDateTime(row.used_at) }}</td>
                        <td>{{ row.plan_name || "PT Package" }}</td>
                        <td>{{ row.sessions_used }}</td>
                        <td>{{ row.coach_name || "-" }}</td>
                        <td>{{ row.confirmed_by || "-" }}</td>
                        <td class="text-muted small" style="max-width: 280px">{{ row.notes || "-" }}</td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <div class="d-md-none">
               <div v-if="usages.length === 0" class="text-center py-4 text-muted small">
                  <i class="bi bi-inbox d-block mb-1" style="font-size: 1.5rem"></i>
                  No sessions logged for this client yet.
               </div>
               <div class="member-card" v-for="row in usages" :key="'mc' + row.id" v-else>
                  <div class="member-card-top">
                     <div>
                        <div class="member-card-name">{{ formatDateTime(row.used_at) }}</div>
                        <div class="member-card-sub">{{ row.plan_name || "PT Package" }} • {{ row.sessions_used }} session{{ row.sessions_used === 1 ? "" : "s" }}</div>
                     </div>
                  </div>
                  <div class="small text-muted mt-2" v-if="row.notes">{{ row.notes }}</div>
                  <div class="member-card-footer">
                     <span><i class="bi bi-person me-1"></i>{{ row.coach_name || "-" }}</span>
                     <span v-if="row.confirmed_by"><i class="bi bi-check2-circle me-1"></i>{{ row.confirmed_by }}</span>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <log-pt-session-modal ref="logModal" :packages="loggablePackages" @logged="onLogged"></log-pt-session-modal>
   </div>
</template>

<script>
import LogPtSessionModal from "./vendor/LogPtSessionModal.vue";
import { formatDate, formatDateTime } from "../../dates";

export default {
   components: { LogPtSessionModal },
   props: {
      client: { type: Object, required: true },
   },
   data: function () {
      return {
         loading: true,
         pageError: "",
         saved: false,
         localClient: { ...this.client },
         packages: [],
      };
   },
   computed: {
      activePackage: function () {
         return this.packages.find((pkg) => pkg.status === "active") || null;
      },
      loggablePackages: function () {
         return this.packages.filter((pkg) => this.isLoggable(pkg));
      },
      usages: function () {
         return this.packages
            .flatMap((pkg) => pkg.usages.map((usage) => ({ ...usage, plan_name: pkg.plan_name })))
            .sort((a, b) => (a.used_at < b.used_at ? 1 : -1));
      },
      sessionsLogged: function () {
         return this.usages.reduce((sum, usage) => sum + Number(usage.sessions_used || 0), 0);
      },
   },
   mounted: function () {
      this.fetchData();
   },
   methods: {
      formatDate,
      formatDateTime,
      fetchData: function () {
         this.loading = true;
         this.pageError = "";
         axios
            .get(`/panel/pt-session/${this.client.id}/data`)
            .then((res) => this.applyData(res.data))
            .catch((err) => (this.pageError = err.response?.data?.message || "Failed to load this client."))
            .finally(() => (this.loading = false));
      },
      applyData: function (payload) {
         this.localClient = { ...this.localClient, ...(payload.client || {}) };
         this.packages = payload.packages || [];
      },
      isLoggable: function (pkg) {
         return pkg.status === "active" && pkg.remaining_sessions > 0;
      },
      openLogModal: function (packageId) {
         this.saved = false;
         this.$refs.logModal.open(packageId || this.loggablePackages[0]?.id || "");
      },
      onLogged: function (payload) {
         this.applyData(payload);
         this.saved = true;
         setTimeout(() => (this.saved = false), 3000);
      },
   },
};
</script>
