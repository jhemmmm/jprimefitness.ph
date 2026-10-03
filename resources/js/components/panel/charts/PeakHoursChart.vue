<template>
   <div class="panel-card h-100">
      <div class="panel-card-header">
         <div>
            <div class="panel-card-title">Peak Hours</div>
            <div class="panel-card-sub">Month-to-date check-ins grouped by hour</div>
         </div>
      </div>
      <div class="panel-card-body chart-wrapper">
         <canvas ref="canvasRef"></canvas>
      </div>
   </div>
</template>

<script>
import { BarController, BarElement, CategoryScale, Chart, Legend, LinearScale, Tooltip } from "chart.js";
import ChartDataLabels from "chartjs-plugin-datalabels";
import { chartTheme } from "./chartTheme";

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend, ChartDataLabels);

export default {
   mixins: [chartTheme],
   props: {
      hours: {
         type: Array,
         default: function () {
            return [];
         },
      },
   },
   watch: {
      hours: function () {
         this.updateChart();
      },
   },
   methods: {
      barColor: function () {
         return this.isDark() ? "rgba(200,16,46,0.78)" : "rgba(200,16,46,0.9)";
      },
      barBorderColor: function () {
         return "#c8102e";
      },
      chartLabels: function () {
         return this.hours.map((row) => row.label);
      },
      chartValues: function () {
         return this.hours.map((row) => row.check_in_count);
      },
      buildChart: function () {
         this.chart = new Chart(this.$refs.canvasRef, {
            type: "bar",
            data: {
               labels: this.chartLabels(),
               datasets: [
                  {
                     label: "Check-ins",
                     data: this.chartValues(),
                     backgroundColor: this.barColor(),
                     borderColor: this.barBorderColor(),
                     borderRadius: 8,
                     borderWidth: 1,
                     maxBarThickness: 28,
                  },
               ],
            },
            options: {
               responsive: true,
               maintainAspectRatio: true,
               plugins: {
                  legend: {
                     display: false,
                  },
                  datalabels: {
                     color: this.labelColor(),
                     anchor: "end",
                     align: "end",
                     font: {
                        size: 10,
                        weight: "600",
                        family: "Inter, sans-serif",
                     },
                     formatter: function (value) {
                        return value > 0 ? value : "";
                     },
                  },
                  tooltip: {
                     callbacks: {
                        label: function (context) {
                           return ` Check-ins: ${Number(context.parsed.y).toLocaleString("en-PH")}`;
                        },
                     },
                  },
               },
               scales: {
                  x: {
                     grid: {
                        color: this.gridColor(),
                        drawBorder: false,
                     },
                     border: { display: false },
                     ticks: {
                        color: this.labelColor(),
                        maxRotation: 0,
                        autoSkip: true,
                     },
                  },
                  y: {
                     beginAtZero: true,
                     grid: {
                        color: this.gridColor(),
                     },
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
         this.chart.data.datasets[0].data = this.chartValues();
         this.chart.update();
      },
      refreshTheme: function () {
         if (!this.chart) {
            return;
         }

         this.chart.data.datasets[0].backgroundColor = this.barColor();
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
