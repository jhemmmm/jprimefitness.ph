<template>
   <div class="panel-notifications" @click.stop>
      <button type="button" class="topbar-icon-btn" title="Notifications" :aria-expanded="open ? 'true' : 'false'" @click="toggleDropdown">
         <i class="bi bi-bell-fill"></i>
         <span v-if="unreadCount > 0" class="topbar-badge">{{ unreadCountLabel }}</span>
      </button>

      <div v-if="open" class="panel-card notifications-dropdown shadow-sm">
         <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
            <div>
               <div class="small text-uppercase text-muted fw-semibold">Notifications</div>
               <div class="small fw-semibold">{{ unreadSummary }}</div>
            </div>
            <a href="/panel/notifications" class="btn btn-link btn-sm p-0 text-decoration-none">View all</a>
         </div>

         <div v-if="loading && notifications.length === 0" class="p-3 small text-muted">Loading notifications...</div>
         <div v-else-if="notifications.length === 0" class="p-3 small text-muted">No notifications yet.</div>

         <div v-else class="notifications-dropdown-list">
            <button
               v-for="notification in notifications"
               :key="notification.id"
               type="button"
               class="notifications-dropdown-item"
               :class="{ 'is-unread': !notification.is_read }"
               @click="openNotification(notification)"
            >
               <div class="d-flex align-items-start gap-2">
                  <span class="notification-state-indicator" :class="`is-${notification.severity}`"></span>

                  <div class="flex-grow-1 min-w-0 text-start">
                     <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="fw-semibold small text-truncate">{{ notification.title }}</div>
                        <span class="badge rounded-pill" :class="severityBadgeClass(notification.severity)">
                           {{ severityLabel(notification.severity) }}
                        </span>
                     </div>

                     <div class="small text-muted mt-1">{{ notification.message }}</div>

                     <div class="small text-muted mt-2 d-flex align-items-center gap-2 flex-wrap">
                        <span>{{ formatOccurredAt(notification.occurred_at) }}</span>
                     </div>
                  </div>

                  <span v-if="!notification.is_read" class="notification-unread-dot"></span>
               </div>
            </button>
         </div>
      </div>
   </div>
</template>

<script>
import { formatDateTime, nowTimestamp } from "../../../dates";

export default {
   data: function () {
      return {
         open: false,
         loading: false,
         notifications: [],
         unreadCount: 0,
         pollHandle: null,
      };
   },

   computed: {
      unreadCountLabel: function () {
         return this.unreadCount > 99 ? "99+" : String(this.unreadCount);
      },

      unreadSummary: function () {
         if (this.unreadCount === 0) {
            return "You're all caught up";
         }

         if (this.unreadCount === 1) {
            return "1 unread alert";
         }

         return `${this.unreadCount} unread alerts`;
      },
   },

   mounted: function () {
      this.fetchNotifications();
      document.addEventListener("click", this.handleDocumentClick);
      document.addEventListener("visibilitychange", this.handleVisibilityChange);
      window.addEventListener("panel-notifications:refresh", this.handleExternalRefresh);
      this.pollHandle = window.setInterval(() => {
         if (!document.hidden) {
            this.fetchNotifications();
         }
      }, 60000);
   },

   beforeUnmount: function () {
      document.removeEventListener("click", this.handleDocumentClick);
      document.removeEventListener("visibilitychange", this.handleVisibilityChange);
      window.removeEventListener("panel-notifications:refresh", this.handleExternalRefresh);

      if (this.pollHandle) {
         window.clearInterval(this.pollHandle);
      }
   },

   methods: {
      toggleDropdown: function () {
         this.open = !this.open;

         if (this.open) {
            this.fetchNotifications();
         }
      },

      fetchNotifications: function () {
         this.loading = true;

         axios
            .get("/panel/notifications/list", {
               params: {
                  filter: "all",
                  per_page: 8,
               },
            })
            .then((response) => {
               this.notifications = response.data.notifications?.data || [];
               this.unreadCount = Number(response.data.unread_count || 0);
            })
            .finally(() => {
               this.loading = false;
            });
      },

      markAsRead: function (notification) {
         if (notification.is_read) {
            return Promise.resolve();
         }

         return axios.post(`/panel/notifications/${notification.id}/read`).then((response) => {
            notification.is_read = true;
            notification.read_at = response.data.notification?.read_at || nowTimestamp();
            this.unreadCount = Number(response.data.unread_count || 0);
            window.dispatchEvent(new Event("panel-notifications:refresh"));
         });
      },

      openNotification: function (notification) {
         const destination = notification.action_url || "/panel/notifications";

         this.markAsRead(notification).finally(() => {
            window.location.href = destination;
         });
      },

      handleDocumentClick: function (event) {
         if (!this.$el.contains(event.target)) {
            this.open = false;
         }
      },

      handleVisibilityChange: function () {
         if (!document.hidden) {
            this.fetchNotifications();
         }
      },

      handleExternalRefresh: function () {
         this.fetchNotifications();
      },

      formatOccurredAt: function (value) {
         return formatDateTime(value);
      },

      severityLabel: function (severity) {
         return {
            success: "Success",
            info: "Info",
            warning: "Warning",
            danger: "Urgent",
            muted: "Update",
         }[severity] || "Info";
      },

      severityBadgeClass: function (severity) {
         return {
            success: "text-bg-success",
            info: "text-bg-primary",
            warning: "text-bg-warning",
            danger: "text-bg-danger",
            muted: "text-bg-secondary",
         }[severity] || "text-bg-primary";
      },
   },
};
</script>
