export function isDark() {
   return document.documentElement.getAttribute("data-bs-theme") === "dark";
}

export function gridColor() {
   return isDark() ? "rgba(255,255,255,0.07)" : "rgba(0,0,0,0.06)";
}

export function labelColor() {
   return isDark() ? "#adb5bd" : "#6c757d";
}

export function pesoLabel(value) {
   return `₱${Number(value).toLocaleString("en-PH")}`;
}

export function pesoAmount(value) {
   return `₱${Number(value).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Shared chart lifecycle: build on mount, destroy on unmount, refresh colors when
// the panel dark-mode toggle changes. Components provide buildChart() and refreshTheme().
export const chartTheme = {
   data: function () {
      return {
         chart: null,
      };
   },
   mounted: function () {
      this.buildChart();
      this.themeListener = () => setTimeout(this.refreshTheme, 50);
      document.getElementById("darkModeToggle")?.addEventListener("change", this.themeListener);
   },
   beforeUnmount: function () {
      document.getElementById("darkModeToggle")?.removeEventListener("change", this.themeListener);
      if (this.chart) {
         this.chart.destroy();
      }
   },
   methods: {
      isDark: isDark,
      gridColor: gridColor,
      labelColor: labelColor,
   },
};
