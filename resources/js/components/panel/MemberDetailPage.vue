<template>
   <div>
      <div class="d-flex align-items-center justify-content-between mb-4">
         <div>
            <h4 class="fw-bold mb-0">Member Details</h4>
            <div class="text-muted small">Information, attendance, memberships, PT sessions, and settings</div>
         </div>
         <a href="/panel/members" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
      </div>

      <div class="panel-card p-4 mb-4">
         <div class="d-flex align-items-start gap-3 flex-wrap">
            <div class="member-avatar employee-avatar-xl flex-shrink-0">{{ $filters.getNameInitials(localMember.name) }}</div>
            <div class="flex-grow-1 min-w-0">
               <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <h4 class="fw-bold mb-0">{{ localMember.name }}</h4>
                  <span :class="['m-badge', $filters.statusBadge(localMember.status)]">{{ $filters.capitalize(localMember.status) }}</span>
                  <span v-if="activeMembership" :class="['m-badge', membershipStatusClass(activeMembership.status)]">
                     {{ activeMembership.rate_plan.name }}
                  </span>
               </div>
               <div class="d-flex gap-3 flex-wrap small text-muted">
                  <span v-if="localMember.email"><i class="bi bi-envelope me-1"></i>{{ localMember.email }}</span>
                  <span v-if="localMember.phone"><i class="bi bi-telephone me-1"></i>{{ localMember.phone }}</span>
                  <span><i class="bi bi-calendar3 me-1"></i>Member since {{ formatDate(localMember.created_at) }}</span>
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
         <component :is="activeComponent" :member="localMember" :rate-plans-data="ratePlansData" @updated="onMemberUpdated" />
      </div>
   </div>
</template>

<script>
import MemberAttendancePage from "./vendor/MemberAttendancePage.vue";
import MemberInformationPage from "./vendor/MemberInformationPage.vue";
import MemberMembershipPage from "./vendor/MemberMembershipPage.vue";
import MemberPtSessionsPage from "./vendor/MemberPtSessionsPage.vue";
import MemberSettingsPage from "./vendor/MemberSettingsPage.vue";
import { formatDate } from "../../dates";

export default {
   components: {
      MemberAttendancePage,
      MemberInformationPage,
      MemberMembershipPage,
      MemberPtSessionsPage,
      MemberSettingsPage,
   },

   props: {
      member: { type: Object, required: true },
      ratePlansData: { type: Array, default: () => [] },
   },

   data: function () {
      return {
         localMember: { ...this.member },
         activeTab: "information",
         tabs: [
            { key: "information", label: "Information", icon: "bi-person-vcard" },
            { key: "attendance", label: "Attendance", icon: "bi-calendar-check" },
            { key: "membership", label: "Memberships", icon: "bi-postcard" },
            { key: "ptSessions", label: "PT Sessions", icon: "bi-stopwatch" },
            { key: "settings", label: "Settings", icon: "bi-gear" },
         ],
      };
   },

   computed: {
      activeComponent: function () {
         return {
            information: "MemberInformationPage",
            attendance: "MemberAttendancePage",
            membership: "MemberMembershipPage",
            ptSessions: "MemberPtSessionsPage",
            settings: "MemberSettingsPage",
         }[this.activeTab];
      },

      activeMembership: function () {
         if (!this.localMember.member_subscriptions || !this.localMember.member_subscriptions.length) {
            return null;
         }

         return (
            this.localMember.member_subscriptions.find((membership) => membership.status === "active" || membership.status === "paused") ||
            this.localMember.member_subscriptions[0]
         );
      },
   },

   methods: {
      formatDate,
      onMemberUpdated: function (updatedMember) {
         this.localMember = { ...updatedMember };
      },

      membershipStatusClass: function (status) {
         return {
            active: "m-badge--plan-active",
            expired: "m-badge--plan-expired",
            cancelled: "m-badge--plan-cancelled",
            paused: "m-badge--plan-paused",
         }[status] || "m-badge--plan-expired";
      },
   },
};
</script>
