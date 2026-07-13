import "./bootstrap";
import { formatClockTime } from "./dates";
import { createApp } from "vue";
const app = createApp({});

import LaravelPermissionToVueJS from "laravel-permission-to-vuejs";
app.use(LaravelPermissionToVueJS);

import GlobalSearch from "./components/panel/vendor/GlobalSearch.vue";
app.component("global-search", GlobalSearch);

import PanelNotifications from "./components/panel/vendor/PanelNotifications.vue";
app.component("panel-notifications", PanelNotifications);

import AsyncSearchSelect from "./components/panel/vendor/AsyncSearchSelect.vue";
app.component("async-search-select", AsyncSearchSelect);

import DashboardPage from "./components/panel/DashboardPage.vue";
app.component("dashboard-page", DashboardPage);

import MembersPage from "./components/panel/MembersPage.vue";
app.component("members-page", MembersPage);

import MemberDetailPage from "./components/panel/MemberDetailPage.vue";
app.component("member-detail-page", MemberDetailPage);

import BusinessSettingsPage from "./components/panel/BusinessSettingsPage.vue";
app.component("business-settings-page", BusinessSettingsPage);

import SystemActivityPage from "./components/panel/SystemActivityPage.vue";
app.component("system-activity-page", SystemActivityPage);

import InventoryPage from "./components/panel/InventoryPage.vue";
app.component("inventory-page", InventoryPage);

import PricingPage from "./components/panel/PricingPage.vue";
app.component("pricing-page", PricingPage);

import SalesPage from "./components/panel/SalesPage.vue";
app.component("sales-page", SalesPage);

import SalesReportsPage from "./components/panel/SalesReportsPage.vue";
app.component("sales-reports-page", SalesReportsPage);

import AttendanceReportsPage from "./components/panel/AttendanceReportsPage.vue";
app.component("attendance-reports-page", AttendanceReportsPage);

import PayrollReportsPage from "./components/panel/PayrollReportsPage.vue";
app.component("payroll-reports-page", PayrollReportsPage);

import AttendancePage from "./components/panel/AttendancePage.vue";
app.component("attendance-page", AttendancePage);

import EmployeesPage from "./components/panel/EmployeesPage.vue";
app.component("employees-page", EmployeesPage);

import EmployeeDetailPage from "./components/panel/EmployeeDetailPage.vue";
app.component("employee-detail-page", EmployeeDetailPage);

import NotificationsPage from "./components/panel/NotificationsPage.vue";
app.component("notifications-page", NotificationsPage);

import HomeComponent from "./components/home/HomeComponent.vue";
app.component("home-component", HomeComponent);

const normalizeKey = (value) =>
   String(value ?? "")
      .trim()
      .toLowerCase()
      .replace(/[\s-]+/g, "_");

// Filters
app.config.globalProperties.$filters = {
   getNameInitials: function (name) {
      if (!name) return "";
      const parts = name.trim().split(/\s+/);
      if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
      return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
   },
   formatQuantity: function (value) {
      if (value === null || value === undefined || value === "") return "0";
      return parseFloat(value).toLocaleString("en-PH", {
         minimumFractionDigits: 0,
         maximumFractionDigits: 2,
      });
   },
   formatMoney(value) {
      if (!value && value !== 0) return "0.00";
      return parseFloat(value).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
   },
   formatPeso(value) {
      return `₱${this.formatMoney(value || 0)}`;
   },
   formatTime: formatClockTime,
   capitalize: function (str) {
      return String(str ?? "")
         .trim()
         .replace(/[_-]+/g, " ")
         .split(/\s+/)
         .filter(Boolean)
         .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
         .join(" ");
   },
   roleBadge(role) {
      return (
         {
            "super admin": "m-badge--super-admin",
            admin: "m-badge--admin",
            manager: "m-badge--manager",
            staff: "m-badge--staff",
            member: "m-badge--member",
            coach: "m-badge--coach",
            employee: "m-badge--employee",
            walk_in: "m-badge--plan",
         }[
            String(role ?? "")
               .trim()
               .toLowerCase()
         ] ?? ""
      );
   },
   statusBadge(status) {
      return (
         {
            active: "m-badge--active",
            inactive: "m-badge--inactive",
            suspended: "m-badge--suspended",
            open: "m-badge--open",
            closed: "m-badge--closed",
            coming_soon: "m-badge--coming_soon",
            pending: "m-badge--pending",
            approved: "m-badge--approved",
            rejected: "m-badge--suspended",
            requested: "m-badge--pending",
            released: "m-badge--open",
            draft: "m-badge--draft",
            partial: "m-badge--partial",
            partially_paid: "m-badge--partial",
            paid: "m-badge--active",
            expired: "m-badge--inactive",
            canceled: "m-badge--suspended",
            cancelled: "m-badge--suspended",
            paused: "m-badge--pending",
            cash: "m-badge--pending",
            gcash: "m-badge--open",
            card: "m-badge--approved",
            bank_transfer: "m-badge--approved",
            online_payment: "m-badge--open",
         }[normalizeKey(status)] ?? ""
      );
   },
   severityLabel(severity) {
      return (
         {
            success: "Success",
            info: "Info",
            warning: "Warning",
            danger: "Urgent",
            muted: "Update",
         }[severity] || "Info"
      );
   },
   severityBadge(severity) {
      return (
         {
            success: "text-bg-success",
            info: "text-bg-primary",
            warning: "text-bg-warning",
            danger: "text-bg-danger",
            muted: "text-bg-secondary",
         }[severity] || "text-bg-primary"
      );
   },
};

app.mount("#app");

(function () {
   const sidebar = document.getElementById("panelSidebar");
   const main = document.getElementById("panelMain");
   const toggle = document.getElementById("sidebarToggle");
   const overlay = document.getElementById("sidebarOverlay");
   if (!sidebar || !main || !toggle || !overlay) return;
   const isMobile = () => window.innerWidth < 992;

   if (!isMobile() && localStorage.getItem("sidebarCollapsed") === "1") {
      sidebar.classList.add("sidebar-collapsed");
      main.classList.add("sidebar-collapsed");
   }

   toggle.addEventListener("click", () => {
      if (isMobile()) {
         sidebar.classList.toggle("sidebar-open");
         overlay.classList.toggle("show");
      } else {
         const collapsed = sidebar.classList.toggle("sidebar-collapsed");
         main.classList.toggle("sidebar-collapsed", collapsed);
         localStorage.setItem("sidebarCollapsed", collapsed ? "1" : "0");
      }
   });

   overlay.addEventListener("click", () => {
      sidebar.classList.remove("sidebar-open");
      overlay.classList.remove("show");
   });

   // Dark Mode Toggle
   const darkModeToggle = document.getElementById("darkModeToggle");
   if (darkModeToggle) {
      const isDark = document.documentElement.getAttribute("data-bs-theme") === "dark";
      darkModeToggle.checked = isDark;

      darkModeToggle.addEventListener("change", (e) => {
         if (e.target.checked) {
            document.documentElement.setAttribute("data-bs-theme", "dark");
            localStorage.setItem("panel-theme", "dark");
         } else {
            document.documentElement.setAttribute("data-bs-theme", "light");
            localStorage.setItem("panel-theme", "light");
         }
      });
   }
})();
