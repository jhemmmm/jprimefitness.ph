<template>
   <div>
      <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-4">
         <div>
            <h4 class="fw-bold mb-0">Notifications</h4>
            <div class="text-muted small">Operational alerts for payroll, cash advances, and inventory.</div>
         </div>

         <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge rounded-pill text-bg-dark">{{ unreadCount }} unread</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="fetchNotifications(pagination.currentPage)">
               <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
         </div>
      </div>

      <div class="panel-card">
         <div class="panel-card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2 flex-wrap">
               <button
                  v-for="option in filterOptions"
                  :key="option.value"
                  type="button"
                  class="btn btn-sm"
                  :class="filter === option.value ? 'btn-danger' : 'btn-outline-secondary'"
                  @click="setFilter(option.value)"
               >
                  {{ option.label }}
               </button>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="unreadCount === 0 || loading" @click="markAllAsRead">
               <i class="bi bi-check2-all me-1"></i>Mark all as read
            </button>
         </div>

         <div v-if="loading && notifications.length === 0" class="p-4 text-muted small">Loading notifications...</div>
         <div v-else-if="notifications.length === 0" class="p-4 text-muted small">No notifications found for this filter.</div>

         <div v-else>
            <div
               v-for="notification in notifications"
               :key="notification.id"
               class="notifications-history-item border-bottom p-3"
               :class="{ 'is-unread': !notification.is_read }"
            >
               <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                  <div class="flex-grow-1 min-w-0">
                     <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge rounded-pill" :class="severityBadgeClass(notification.severity)">
                           {{ severityLabel(notification.severity) }}
                        </span>
                        <span v-if="notification.branch_name" class="text-muted small">{{ notification.branch_name }}</span>
                        <span v-if="!notification.is_read" class="badge rounded-pill text-bg-danger-subtle notification-unread-badge">Unread</span>
                     </div>

                     <div class="fw-semibold mt-2">{{ notification.title }}</div>
                     <div class="small text-muted mt-1">{{ notification.message }}</div>
                     <div class="small text-muted mt-2">{{ formatOccurredAt(notification.occurred_at) }}</div>
                  </div>

                  <div class="d-flex align-items-center gap-2 flex-wrap">
                     <button
                        v-if="!notification.is_read"
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        @click="markNotificationAsRead(notification)"
                     >
                        Mark read
                     </button>
                     <button type="button" class="btn btn-sm btn-danger" @click="openNotification(notification)">Open</button>
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
   </div>
</template>

<script>
export default {
   data: function () {
      return {
         loading: false,
         filter: "all",
         notifications: [],
         unreadCount: 0,
         pagination: {
            currentPage: 1,
            lastPage: 1,
            total: 0,
            links: [],
         },
      };
   },

   computed: {
      filterOptions: function () {
         return [
            { value: "all", label: "All" },
            { value: "unread", label: "Unread" },
            { value: "read", label: "Read" },
         ];
      },
   },

   mounted: function () {
      this.fetchNotifications();
      window.addEventListener("panel-notifications:refresh", this.handleExternalRefresh);
   },

   beforeUnmount: function () {
      window.removeEventListener("panel-notifications:refresh", this.handleExternalRefresh);
   },

   methods: {
      fetchNotifications: function (page = 1) {
         this.loading = true;

         axios
            .get("/panel/notifications/list", {
               params: {
                  filter: this.filter,
                  per_page: 20,
                  page,
               },
            })
            .then((response) => {
               const notifications = response.data.notifications || {};

               this.notifications = notifications.data || [];
               this.unreadCount = Number(response.data.unread_count || 0);
               this.pagination = {
                  currentPage: notifications.current_page || 1,
                  lastPage: notifications.last_page || 1,
                  total: notifications.total || 0,
                  links: notifications.links || [],
               };
            })
            .finally(() => {
               this.loading = false;
            });
      },

      setFilter: function (value) {
         if (this.filter === value) {
            return;
         }

         this.filter = value;
         this.fetchNotifications(1);
      },

      goToPage: function (link) {
         if (!link.url) {
            return;
         }

         const url = new URL(link.url);
         const page = Number(url.searchParams.get("page") || 1);
         this.fetchNotifications(page);
      },

      markNotificationAsRead: function (notification) {
         if (notification.is_read) {
            return Promise.resolve();
         }

         return axios.post(`/panel/notifications/${notification.id}/read`).then((response) => {
            notification.is_read = true;
            notification.read_at = response.data.notification?.read_at || new Date().toISOString();
            this.unreadCount = Number(response.data.unread_count || 0);
            window.dispatchEvent(new Event("panel-notifications:refresh"));

            if (this.filter === "unread") {
               this.fetchNotifications(this.pagination.currentPage);
            }
         });
      },

      markAllAsRead: function () {
         axios.post("/panel/notifications/read-all").then(() => {
            window.dispatchEvent(new Event("panel-notifications:refresh"));
            this.fetchNotifications(1);
         });
      },

      openNotification: function (notification) {
         const destination = notification.action_url || "/panel/notifications";

         this.markNotificationAsRead(notification).finally(() => {
            window.location.href = destination;
         });
      },

      handleExternalRefresh: function () {
         this.fetchNotifications(this.pagination.currentPage);
      },

      formatOccurredAt: function (value) {
         return this.$filters.formatDateTime(value);
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
