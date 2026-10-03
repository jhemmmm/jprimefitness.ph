<template>
   <div class="panel-card h-100">
      <div class="panel-card-header">
         <div>
            <div class="panel-card-title">Attendance Trend</div>
            <div class="panel-card-sub">Daily check-ins and unique attendees for the current report filter</div>
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
import { chartTheme } from "./chartTheme";

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler, ChartDataLabels);

export default {
   mixins: [chartTheme],
   props: {
      trend: {
         type: Array,
         default: function () {
            return [];
         },
      },
   },
   watch: {
      trend: function () {
         this.updateChart();
      },
   },
   methods: {
      checkInColor: function () {
         return "#c8102e";
      },
      uniqueColor: function () {
         return "#0d6efd";
      },
      formatDateLabel: function (value) {
         return formatShortMonthDay(value);
      },
      chartLabels: function () {
         return this.trend.map((row) => this.formatDateLabel(row.attendance_date));
      },
      checkInValues: function () {
         return this.trend.map((row) => row.check_in_count);
      },
      uniqueValues: function () {
         return this.trend.map((row) => row.unique_attendees);
      },
      buildChart: function () {
         this.chart = new Chart(this.$refs.canvasRef, {
            type: "line",
            data: {
               labels: this.chartLabels(),
               datasets: [
                  {
                     label: "Check-ins",
                     data: this.checkInValues(),
                     borderColor: this.checkInColor(),
                     backgroundColor: this.isDark() ? "rgba(200,16,46,0.18)" : "rgba(200,16,46,0.08)",
                     fill: true,
                     tension: 0.35,
                     pointRadius: 4,
                     pointHoverRadius: 5,
                     pointBorderWidth: 2,
                     pointBackgroundColor: this.checkInColor(),
                     pointBorderColor: this.isDark() ? "#0f1113" : "#ffffff",
                     datalabels: {
                        align: "top",
                        anchor: "end",
                     },
                  },
                  {
                     label: "Unique Attendees",
                     data: this.uniqueValues(),
                     borderColor: this.uniqueColor(),
                     backgroundColor: "transparent",
                     fill: false,
                     tension: 0.35,
                     pointRadius: 4,
                     pointHoverRadius: 5,
                     pointBorderWidth: 2,
                     pointBackgroundColor: this.uniqueColor(),
                     pointBorderColor: this.isDark() ? "#0f1113" : "#ffffff",
                     datalabels: {
                        align: "bottom",
                        anchor: "end",
                     },
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
                     color: this.labelColor(),
                     font: { size: 10, weight: "600", family: "Inter, sans-serif" },
                     formatter: function (value) {
                        return value > 0 ? value : "";
                     },
                  },
                  tooltip: {
                     callbacks: {
                        label: function (ctx) {
                           return ` ${ctx.dataset.label}: ${Number(ctx.parsed.y).toLocaleString("en-PH")}`;
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
                        precision: 0,
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
         this.chart.data.datasets[0].data = this.checkInValues();
         this.chart.data.datasets[1].data = this.uniqueValues();
         this.chart.update();
      },
      refreshTheme: function () {
         if (!this.chart) {
            return;
         }

         this.chart.data.datasets[0].backgroundColor = this.isDark() ? "rgba(200,16,46,0.18)" : "rgba(200,16,46,0.08)";
         this.chart.data.datasets[0].pointBorderColor = this.isDark() ? "#0f1113" : "#ffffff";
         this.chart.data.datasets[1].pointBorderColor = this.isDark() ? "#0f1113" : "#ffffff";
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
