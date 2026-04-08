<template>
   <div>
      <div class="d-flex align-items-center justify-content-between mb-4">
         <div>
            <h4 class="fw-bold mb-0">Settings</h4>
            <div class="text-muted small">Business profile, cash ledger, gallery, and public-site copy</div>
         </div>
      </div>

      <div class="panel-card p-4 mb-4">
         <div class="d-flex align-items-start gap-3 flex-wrap">
            <div class="member-avatar employee-avatar-xl flex-shrink-0">
               <i class="bi bi-shop"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
               <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <h4 class="fw-bold mb-0">{{ localProfile.name }}</h4>
                  <span :class="['m-badge', $filters.statusBadge(localProfile.status)]">{{ $filters.capitalize(localProfile.status) }}</span>
                  <span class="m-badge m-badge--plan">{{ (localProfile.country_code || "PH").toUpperCase() }}</span>
               </div>
               <div class="text-muted small mb-2">
                  <i class="bi bi-pin-map me-1"></i>{{ locationLabel }}
               </div>
               <div class="d-flex gap-3 flex-wrap small text-muted">
                  <span v-if="localProfile.email"><i class="bi bi-envelope me-1"></i>{{ localProfile.email }}</span>
                  <span v-if="localProfile.phone"><i class="bi bi-telephone me-1"></i>{{ localProfile.phone }}</span>
                  <span v-if="localProfile.opening_time && localProfile.closing_time">
                     <i class="bi bi-clock me-1"></i>{{ formatTime(localProfile.opening_time) }} – {{ formatTime(localProfile.closing_time) }}
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
         <component :is="activeComponent" :profile="localProfile" @updated="onProfileUpdated" />
      </div>
   </div>
</template>

<script>
import BranchGalleryPage from "./vendor/BranchGalleryPage.vue";
import BranchCashLedgerPage from "./vendor/BranchCashLedgerPage.vue";
import BranchInformationPage from "./vendor/BranchInformationPage.vue";
import BranchSettingsPage from "./vendor/BranchSettingsPage.vue";

export default {
   components: {
      BusinessProfileCashLedgerPage: BranchCashLedgerPage,
      BusinessProfileGalleryPage: BranchGalleryPage,
      BusinessProfileInformationPage: BranchInformationPage,
      BusinessProfileSettingsPage: BranchSettingsPage,
   },

   props: {
      profile: { type: Object, required: true },
   },

   data: function () {
      return {
         localProfile: { ...this.profile },
         activeTab: "information",
         tabs: [
            { key: "information", label: "Information", icon: "bi-building" },
            { key: "cashLedger", label: "Cash Ledger", icon: "bi-cash-stack" },
            { key: "gallery", label: "Photo / Gallery", icon: "bi-images" },
            { key: "settings", label: "Settings", icon: "bi-gear" },
         ],
      };
   },

   computed: {
      activeComponent: function () {
         return {
            information: "BusinessProfileInformationPage",
            cashLedger: "BusinessProfileCashLedgerPage",
            gallery: "BusinessProfileGalleryPage",
            settings: "BusinessProfileSettingsPage",
         }[this.activeTab];
      },
      locationLabel: function () {
         return [this.localProfile.city, this.localProfile.province].filter(Boolean).join(", ") || "Location not set";
      },
      summaryCards: function () {
         const cashBalance = Number(this.localProfile.cash_ledger_summary?.balance || 0);
         const photoCount = Array.isArray(this.localProfile.photos) ? this.localProfile.photos.length : 0;
         const systemEntries = Number(this.localProfile.cash_ledger_summary?.system_entries_count || 0);
         const manualEntries = Number(this.localProfile.cash_ledger_summary?.manual_entries_count || 0);

         return [
            { label: "Cash Balance", value: `₱${this.$filters.formatMoney(cashBalance)}`, icon: "bi-wallet2", iconBg: "bg-info-soft", iconColor: "text-info" },
            { label: "Gallery Photos", value: photoCount, icon: "bi-images", iconBg: "bg-primary-soft", iconColor: "text-primary" },
            { label: "System Entries", value: systemEntries, icon: "bi-postcard", iconBg: "bg-success-soft", iconColor: "text-success" },
            { label: "Manual Entries", value: manualEntries, icon: "bi-stopwatch", iconBg: "bg-warning-soft", iconColor: "text-warning" },
         ];
      },
   },

   methods: {
      onProfileUpdated: function (updatedProfile) {
         this.localProfile = { ...this.localProfile, ...updatedProfile };
      },
      formatTime: function (timeStr) {
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
