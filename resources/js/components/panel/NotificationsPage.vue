<template>
   <div>
      <div v-if="pageError" class="alert alert-danger py-2 small mb-3">{{ pageError }}</div>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
         <div>
            <h4 class="panel-page-title mb-0">Notifications</h4>
            <p class="text-muted small mb-0">Operational alerts for payroll and inventory.</p>
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
                        <span class="badge rounded-pill" :class="$filters.severityBadge(notification.severity)">
                           {{ $filters.severityLabel(notification.severity) }}
                        </span>
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
import { formatDateTime, nowTimestamp } from "../../dates";

export default {
   data: function () {
      return {
         loading: false,
         pageError: "",
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
         this.pageError = "";

         return axios
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
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to load notifications.";
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
         this.pageError = "";

         if (notification.is_read) {
            return Promise.resolve();
         }

         return axios
            .post(`/panel/notifications/${notification.id}/read`)
            .then((response) => {
               notification.is_read = true;
               notification.read_at = response.data.notification?.read_at || nowTimestamp();
               this.unreadCount = Number(response.data.unread_count || 0);
               window.dispatchEvent(new Event("panel-notifications:refresh"));

               if (this.filter === "unread") {
                  this.fetchNotifications(this.pagination.currentPage);
               }
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to update the notification.";
            });
      },

      markAllAsRead: function () {
         this.pageError = "";

         axios
            .post("/panel/notifications/read-all")
            .then(() => {
               window.dispatchEvent(new Event("panel-notifications:refresh"));
               this.fetchNotifications(1);
            })
            .catch((error) => {
               this.pageError = error.response?.data?.message || "Failed to mark notifications as read.";
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
         return formatDateTime(value);
      },

   },
};
</script>
