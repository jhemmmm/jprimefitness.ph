<template>
   <div class="panel-card h-100">
      <div class="panel-card-header">
         <div>
            <div class="panel-card-title">Daily Sales Trend</div>
            <div class="panel-card-sub">Revenue by day for the current report filter</div>
         </div>
      </div>
      <div class="panel-card-body chart-wrapper">
         <canvas ref="canvasRef"></canvas>
      </div>
   </div>
</template>

<script>
import { Chart, CategoryScale, Filler, LineController, LineElement, LinearScale, PointElement, Tooltip } from "chart.js";
import ChartDataLabels from "chartjs-plugin-datalabels";
import { formatShortMonthDay } from "../../../dates";
import { chartTheme, pesoAmount, pesoLabel } from "./chartTheme";

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler, ChartDataLabels);

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
      lineColor: function () {
         return "#c8102e";
      },
      fillColor: function () {
         return this.isDark() ? "rgba(200,16,46,0.26)" : "rgba(200,16,46,0.14)";
      },
      formatDateLabel: function (value) {
         return formatShortMonthDay(value);
      },
      chartLabels: function () {
         return this.trend.map((row) => this.formatDateLabel(row.sale_date));
      },
      chartValues: function () {
         return this.trend.map((row) => row.total_sales);
      },
      buildChart: function () {
         this.chart = new Chart(this.$refs.canvasRef, {
            type: "line",
            data: {
               labels: this.chartLabels(),
               datasets: [
                  {
                     label: "Sales",
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
                  legend: { display: false },
                  datalabels: {
                     anchor: "end",
                     align: "top",
                     offset: 4,
                     color: this.labelColor(),
                     font: { size: 10, weight: "600", family: "Inter, sans-serif" },
                     formatter: function (value) {
                        return value > 0 ? pesoLabel(value) : "";
                     },
                  },
                  tooltip: {
                     callbacks: {
                        label: function (ctx) {
                           return ` ${pesoAmount(ctx.parsed.y)}`;
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
                           return pesoLabel(value);
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
