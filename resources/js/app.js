import "./bootstrap";
import { createApp } from "vue";
const app = createApp({});

import LaravelPermissionToVueJS from "laravel-permission-to-vuejs";
app.use(LaravelPermissionToVueJS);

import BranchSelector from "./components/panel/_vendor/BranchSelector.vue";
app.component("branch-selector", BranchSelector);

import BranchSelectorCollapsed from "./components/panel/_vendor/BranchSelectorCollapsed.vue";
app.component("branch-selector-collapsed", BranchSelectorCollapsed);

import AsyncSearchSelect from "./components/panel/_vendor/AsyncSearchSelect.vue";
app.component("async-search-select", AsyncSearchSelect);

import HomeComponent from "./components/home/HomeComponent.vue";
app.component("home-component", HomeComponent);

import BranchComponent from "./components/home/BranchComponent.vue";
app.component("branch-component", BranchComponent);

import DashboardPage from "./components/panel/DashboardPage.vue";
app.component("dashboard-page", DashboardPage);

import MembersPage from "./components/panel/MembersPage.vue";
app.component("members-page", MembersPage);

import MemberDetailPage from "./components/panel/MemberDetailPage.vue";
app.component("member-detail-page", MemberDetailPage);

import WalkInsPage from "./components/panel/WalkInsPage.vue";
app.component("walk-ins-page", WalkInsPage);

import BranchesPage from "./components/panel/BranchesPage.vue";
app.component("branches-page", BranchesPage);

import BranchDetailPage from "./components/panel/BranchDetailPage.vue";
app.component("branch-detail-page", BranchDetailPage);

import InventoryPage from "./components/panel/InventoryPage.vue";
app.component("inventory-page", InventoryPage);

import PricingPage from "./components/panel/PricingPage.vue";
app.component("pricing-page", PricingPage);

import SalesPage from "./components/panel/SalesPage.vue";
app.component("sales-page", SalesPage);

import AttendancePage from "./components/panel/AttendancePage.vue";
app.component("attendance-page", AttendancePage);

import EmployeesPage from "./components/panel/EmployeesPage.vue";
app.component("employees-page", EmployeesPage);

import EmployeeDetailPage from "./components/panel/EmployeeDetailPage.vue";
app.component("employee-detail-page", EmployeeDetailPage);

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
   formatDateTime: function (dt, format = "display") {
      if (!dt) {
         return format === "input" ? "" : "—";
      }

      const date = new Date(dt);

      if (format === "input") {
         const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
         return localDate.toISOString().slice(0, 16);
      }

      return date.toLocaleString("en-PH", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
   },
   formatDate: function (dateStr) {
      if (!dateStr) return "—";
      return new Date(dateStr).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
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
};

app.mount("#app");

(function () {
   const sidebar = document.getElementById("panelSidebar");
   const main = document.getElementById("panelMain");
   const toggle = document.getElementById("sidebarToggle");
   const overlay = document.getElementById("sidebarOverlay");
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
})();
