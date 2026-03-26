<template>
    <div class="panel-card h-100">
        <div class="panel-card-header">
            <div>
                <div class="panel-card-title">This Week's Earnings</div>
                <div class="panel-card-sub">Revenue by day — current week</div>
            </div>
        </div>
        <div class="panel-card-body chart-wrapper">
            <canvas ref="canvasRef"></canvas>
        </div>
    </div>
</template>

<script>
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } from "chart.js";
import ChartDataLabels from "chartjs-plugin-datalabels";

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, ChartDataLabels);

export default {
    props: {
        earnings: {
            type: Array,
            default: function () {
                return [3200, 4750, 2900, 5100, 6300, 8200, 4400];
            },
        },
    },
    data: function () {
        return {
            chart: null,
            weekDays: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
            todayIdx: (new Date().getDay() + 6) % 7,
        };
    },
    mounted() {
        this.buildChart();
        document.querySelectorAll("#appearanceToggle").forEach((el) => {
            el.addEventListener("change", () => setTimeout(this.refreshTheme, 50));
        });
    },
    beforeUnmount() {
        if (this.chart) this.chart.destroy();
    },
    watch: {
        earnings: function () {
            if (!this.chart) return;
            const data = this.buildData();
            this.chart.data.datasets[0].data = data;
            this.chart.data.datasets[0].backgroundColor = this.buildColors(data);
            this.chart.update();
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
        buildData: function () {
            return this.earnings.map((v, i) => (i <= this.todayIdx ? v : null));
        },
        buildColors: function (data) {
            return data.map((v, i) => {
                if (v === null) return "rgba(0,0,0,0)";
                if (i === this.todayIdx) return "#c8102e";
                return this.isDark() ? "rgba(255,255,255,0.18)" : "rgba(13,15,18,0.15)";
            });
        },
        buildChart: function () {
            const data = this.buildData();
            const colors = this.buildColors(data);
            this.chart = new Chart(this.$refs.canvasRef, {
                type: "bar",
                data: {
                    labels: this.weekDays,
                    datasets: [
                        {
                            label: "Earnings",
                            data: data,
                            backgroundColor: colors,
                            borderRadius: 6,
                            borderSkipped: false,
                            barPercentage: 0.55,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            display: (ctx) => ctx.dataset.data[ctx.dataIndex] !== null,
                            anchor: "end",
                            align: "end",
                            offset: 2,
                            color: this.labelColor(),
                            font: { size: 10, weight: "600", family: "Inter, sans-serif" },
                            formatter: (v) => (v !== null ? `₱${v.toLocaleString()}` : ""),
                        },
                        tooltip: {
                            callbacks: { label: (ctx) => ` ₱${ctx.parsed.y.toLocaleString()}` },
                        },
                    },
                    scales: {
                        x: {
                            grid: { color: this.gridColor() },
                            border: { display: false },
                            ticks: { color: this.labelColor() },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: this.gridColor() },
                            border: { display: false },
                            ticks: { color: this.labelColor(), callback: (v) => `₱${v.toLocaleString()}` },
                        },
                    },
                },
            });
        },
        refreshTheme: function () {
            if (!this.chart) return;
            const data = this.buildData();
            this.chart.data.datasets[0].backgroundColor = this.buildColors(data);
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
