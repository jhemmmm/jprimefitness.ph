<template>
   <div class="panel-card h-100">
      <div class="panel-card-header">
         <div>
            <div class="panel-card-title">Payroll Trend</div>
            <div class="panel-card-sub">Net payroll by period end for the current report filter</div>
         </div>
      </div>
      <div class="panel-card-body chart-wrapper">
         <canvas ref="canvasRef"></canvas>
      </div>
   </div>
</template>

<script>
import { Chart, CategoryScale, Filler, Legend, LineController, LineElement, LinearScale, PointElement, Tooltip } from "chart.js";
import ChartDataLabels from "chartjs-plugin-datalabels";
import { formatShortMonthDay } from "../../../dates";

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler, ChartDataLabels);

export default {
   props: {
      trend: {
         type: Array,
         default: function () {
            return [];
         },
      },
   },
   data: function () {
      return {
         chart: null,
      };
   },
   mounted: function () {
      this.buildChart();
      document.querySelectorAll("#darkModeToggle").forEach((el) => {
         el.addEventListener("change", () => setTimeout(this.refreshTheme, 50));
      });
   },
   beforeUnmount: function () {
      if (this.chart) {
         this.chart.destroy();
      }
   },
   watch: {
      trend: function () {
         this.updateChart();
      },
   },
   methods: {
      isDark: function () {
         return document.documentElement.getAttribute("data-bs-theme") === "dark";
      },
      gridColor: function () {
         return this.isDark() ? "rgba(255,255,255,0.07)" : "rgba(0,0,0,0.06)";
      },
      labelColor: function () {
         return this.isDark() ? "#adb5bd" : "#6c757d";
      },
      lineColor: function () {
         return "#0d6efd";
      },
      fillColor: function () {
         return this.isDark() ? "rgba(13,110,253,0.18)" : "rgba(13,110,253,0.08)";
      },
      formatDateLabel: function (value) {
         return formatShortMonthDay(value);
      },
      chartLabels: function () {
         return this.trend.map((row) => this.formatDateLabel(row.period_end));
      },
      chartValues: function () {
         return this.trend.map((row) => row.net_payroll);
      },
      buildChart: function () {
         this.chart = new Chart(this.$refs.canvasRef, {
            type: "line",
            data: {
               labels: this.chartLabels(),
               datasets: [
                  {
                     label: "Net Payroll",
                     data: this.chartValues(),
                     borderColor: this.lineColor(),
                     backgroundColor: this.fillColor(),
                     fill: true,
                     tension: 0.35,
                     pointRadius: 4,
                     pointHoverRadius: 5,
                     pointBorderWidth: 2,
                     pointBackgroundColor: this.lineColor(),
                     pointBorderColor: this.isDark() ? "#0f1113" : "#ffffff",
                  },
               ],
            },
            options: {
               responsive: true,
               maintainAspectRatio: true,
               plugins: {
                  legend: {
                     position: "bottom",
                     labels: {
                        color: this.labelColor(),
                        usePointStyle: true,
                        padding: 16,
                     },
                  },
                  datalabels: {
                     align: "top",
                     anchor: "end",
                     color: this.labelColor(),
                     font: { size: 10, weight: "600", family: "Inter, sans-serif" },
                     formatter: function (value) {
                        return value > 0 ? `₱${Number(value).toLocaleString("en-PH")}` : "";
                     },
                  },
                  tooltip: {
                     callbacks: {
                        label: function (ctx) {
                           return ` ${ctx.dataset.label}: ₱${Number(ctx.parsed.y).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                        },
                     },
                  },
               },
               scales: {
                  x: {
                     grid: { color: this.gridColor(), drawBorder: false },
                     border: { display: false },
                     ticks: { color: this.labelColor() },
                  },
                  y: {
                     beginAtZero: true,
                     grid: { color: this.gridColor() },
                     border: { display: false },
                     ticks: {
                        color: this.labelColor(),
                        callback: function (value) {
                           return `₱${Number(value).toLocaleString("en-PH")}`;
                        },
                     },
                  },
               },
            },
         });
      },
      updateChart: function () {
         if (!this.chart) {
            return;
         }

         this.chart.data.labels = this.chartLabels();
         this.chart.data.datasets[0].data = this.chartValues();
         this.chart.update();
      },
      refreshTheme: function () {
         if (!this.chart) {
            return;
         }

         this.chart.data.datasets[0].backgroundColor = this.fillColor();
         this.chart.data.datasets[0].pointBorderColor = this.isDark() ? "#0f1113" : "#ffffff";
         this.chart.options.plugins.legend.labels.color = this.labelColor();
         this.chart.options.plugins.datalabels.color = this.labelColor();
         this.chart.options.scales.x.grid.color = this.gridColor();
         this.chart.options.scales.x.ticks.color = this.labelColor();
         this.chart.options.scales.y.grid.color = this.gridColor();
         this.chart.options.scales.y.ticks.color = this.labelColor();
         this.chart.update();
      },
   },
};
</script>
