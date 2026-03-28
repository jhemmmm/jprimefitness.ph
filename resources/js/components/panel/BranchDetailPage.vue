<template>
   <div>
      <div class="d-flex align-items-center justify-content-between mb-4">
         <div>
            <h4 class="fw-bold mb-0">Branch Details</h4>
            <div class="text-muted small">Business information, cash ledger, government contributions, gallery, and settings</div>
         </div>
         <a href="/panel/branches" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
      </div>

      <div class="panel-card p-4 mb-4">
         <div class="d-flex align-items-start gap-3 flex-wrap">
            <div class="member-avatar employee-avatar-xl flex-shrink-0">
               <i class="bi bi-geo-alt-fill"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
               <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <h4 class="fw-bold mb-0">{{ localBranch.name }}</h4>
                  <span :class="['m-badge', $filters.statusBadge(localBranch.status)]">{{ $filters.capitalize(localBranch.status) }}</span>
                  <span class="m-badge m-badge--plan">{{ (localBranch.country_code || "PH").toUpperCase() }}</span>
               </div>
               <div class="text-muted small mb-2">
                  <i class="bi bi-pin-map me-1"></i>{{ branchLocation }}
               </div>
               <div class="d-flex gap-3 flex-wrap small text-muted">
                  <span v-if="localBranch.email"><i class="bi bi-envelope me-1"></i>{{ localBranch.email }}</span>
                  <span v-if="localBranch.phone"><i class="bi bi-telephone me-1"></i>{{ localBranch.phone }}</span>
                  <span v-if="localBranch.opening_time && localBranch.closing_time">
                     <i class="bi bi-clock me-1"></i>{{ formatTime(localBranch.opening_time) }} – {{ formatTime(localBranch.closing_time) }}
                  </span>
               </div>
            </div>
         </div>
      </div>

      <div class="row g-3 mb-4">
         <div class="col-6 col-lg-3" v-for="card in summaryCards" :key="card.label">
            <div class="stat-card">
               <div class="stat-card-icon" :class="card.iconBg">
                  <i class="bi" :class="[card.icon, card.iconColor]"></i>
               </div>
               <div class="stat-card-body">
                  <div class="stat-card-label">{{ card.label }}</div>
                  <div class="stat-card-value">{{ card.value }}</div>
               </div>
            </div>
         </div>
      </div>

      <ul class="nav nav-tabs mb-0" style="border-bottom: none">
         <li class="nav-item" v-for="tab in tabs" :key="tab.key">
            <button class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key"><i class="bi me-1" :class="tab.icon"></i>{{ tab.label }}</button>
         </li>
      </ul>
      <div class="panel-card" style="border-top-left-radius: 0">
         <component :is="activeComponent" :branch="localBranch" @updated="onBranchUpdated" />
      </div>
   </div>
</template>

<script>
import BranchGalleryPage from "./vendor/BranchGalleryPage.vue";
import BranchCashLedgerPage from "./vendor/BranchCashLedgerPage.vue";
import BranchGovernmentContributionsPage from "./vendor/BranchGovernmentContributionsPage.vue";
import BranchInformationPage from "./vendor/BranchInformationPage.vue";
import BranchSettingsPage from "./vendor/BranchSettingsPage.vue";

export default {
   components: {
      BranchCashLedgerPage,
      BranchGalleryPage,
      BranchGovernmentContributionsPage,
      BranchInformationPage,
      BranchSettingsPage,
   },

   props: {
      branch: { type: Object, required: true },
   },

   data() {
      return {
         localBranch: { ...this.branch },
         activeTab: "information",
         tabs: [
            { key: "information", label: "Information", icon: "bi-building" },
            { key: "cashLedger", label: "Cash Ledger", icon: "bi-cash-stack" },
            { key: "governmentContributions", label: "Government Contributions", icon: "bi-bank2" },
            { key: "gallery", label: "Photo / Gallery", icon: "bi-images" },
            { key: "settings", label: "Settings", icon: "bi-gear" },
         ],
      };
   },

   computed: {
      activeComponent() {
         return {
            information: "BranchInformationPage",
            cashLedger: "BranchCashLedgerPage",
            governmentContributions: "BranchGovernmentContributionsPage",
            gallery: "BranchGalleryPage",
            settings: "BranchSettingsPage",
         }[this.activeTab];
      },

      branchLocation() {
         const location = [this.localBranch.city, this.localBranch.province].filter(Boolean).join(", ");

         return location || "Location not set";
      },

      summaryCards() {
         const cashBalance = Number(this.localBranch.cash_ledger_summary?.balance || 0);

         return [
            { label: "Cash Balance", value: `₱${this.$filters.formatMoney(cashBalance)}`, icon: "bi-wallet2", iconBg: "bg-info-soft", iconColor: "text-info" },
            { label: "Assigned People", value: this.localBranch.users_count || 0, icon: "bi-people", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "Rate Plans", value: this.localBranch.rate_plans_count || 0, icon: "bi-postcard", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "PT Products", value: this.localBranch.pt_products_count || 0, icon: "bi-stopwatch", iconBg: "bg-warning-soft", iconColor: "text-warning" },
         ];
      },
   },

   methods: {
      onBranchUpdated(updatedBranch) {
         this.localBranch = { ...this.localBranch, ...updatedBranch };
      },

      formatTime(timeStr) {
         if (!timeStr) {
            return "";
         }

         const parts = String(timeStr).split(":");
         let hour = parseInt(parts[0], 10);
         const minute = parts[1];
         const suffix = hour >= 12 ? "PM" : "AM";

         hour = hour % 12 || 12;

         return `${hour}:${minute} ${suffix}`;
      },
   },
};
</script>
