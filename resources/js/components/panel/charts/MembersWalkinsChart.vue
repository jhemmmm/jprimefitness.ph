<template>
    <div class="panel-card h-100">
        <div class="panel-card-header">
            <div>
                <div class="panel-card-title">Members vs Walk-ins</div>
                <div class="panel-card-sub">Composition of today's check-ins</div>
            </div>
        </div>
        <div class="panel-card-body chart-wrapper chart-wrapper-pie">
            <canvas ref="canvasRef"></canvas>
        </div>
    </div>
</template>

<script>
import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from "chart.js";
import ChartDataLabels from "chartjs-plugin-datalabels";

Chart.register(DoughnutController, ArcElement, Tooltip, Legend, ChartDataLabels);

// Module-level helpers (no Vue instance needed — only read the DOM)
const isDark = () => document.documentElement.getAttribute("data-bs-theme") === "dark";
const labelColor = () => (isDark() ? "#adb5bd" : "#6c757d");

// Center-text plugin — shows total at rest, individual count on hover
const centerTextPlugin = {
    id: "centerText",
    afterDraw: function (c) {
        if (c.config.type !== "doughnut") return;
        const {
            ctx,
            chartArea: { left, top, width, height },
        } = c;
        const total = c.data.datasets[0].data.reduce((a, b) => a + b, 0);

        let displayVal = total;
        let displayLabel = "Total";
        const active = c.getActiveElements();
        if (active.length) {
            const idx = active[0].index;
            displayVal = c.data.datasets[0].data[idx];
            displayLabel = c.data.labels[idx];
        }

        const cx = left + width / 2;
        const cy = top + height / 2;

        ctx.save();
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";

        ctx.font = "bold 30px Inter, sans-serif";
        ctx.fillStyle = isDark() ? "#ffffff" : "#0d0f12";
        ctx.fillText(displayVal, cx, cy - 10);

        ctx.font = "500 11px Inter, sans-serif";
        ctx.fillStyle = labelColor();
        ctx.fillText(displayLabel, cx, cy + 14);
        ctx.restore();
    },
};

export default {
    props: {
        members: { type: Number, default: 256 },
        walkIns: { type: Number, default: 150 },
    },
    data: function () {
        return {
            chart: null,
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
        members: function () {
            this.updateData();
        },
        walkIns: function () {
            this.updateData();
        },
    },
    methods: {
        buildChart: function () {
            this.chart = new Chart(this.$refs.canvasRef, {
                type: "doughnut",
                plugins: [centerTextPlugin],
                data: {
                    labels: ["Memberships", "Walk-ins"],
                    datasets: [
                        {
                            data: [this.members, this.walkIns],
                            backgroundColor: ["#0d0f12", "#c8102e"],
                            borderColor: isDark() ? "#1a1d21" : "#ffffff",
                            borderWidth: 4,
                            hoverOffset: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1,
                    cutout: "55%",
                    plugins: {
                        datalabels: {
                            color: "#ffffff",
                            font: { size: 11, weight: "bold", family: "Inter, sans-serif" },
                            textAlign: "center",
                            display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                            formatter: (value, ctx) => `${ctx.chart.data.labels[ctx.dataIndex]}\n${value}`,
                        },
                        legend: {
                            position: "bottom",
                            labels: {
                                color: labelColor(),
                                padding: 16,
                                usePointStyle: true,
                                pointStyleWidth: 16,
                                generateLabels: function (c) {
                                    return c.data.labels.map((label, i) => ({
                                        text: `${label}: ${c.data.datasets[0].data[i]}`,
                                        fillStyle: c.data.datasets[0].backgroundColor[i],
                                        strokeStyle: c.data.datasets[0].backgroundColor[i],
                                        pointStyle: "square",
                                        hidden: false,
                                        index: i,
                                    }));
                                },
                            },
                        },
                        tooltip: {
                            callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed}` },
                        },
                    },
                },
            });
        },
        updateData: function () {
            if (!this.chart) return;
            this.chart.data.datasets[0].data = [this.members, this.walkIns];
            this.chart.update();
        },
        refreshTheme: function () {
            if (!this.chart) return;
            this.chart.data.datasets[0].borderColor = isDark() ? "#1a1d21" : "#ffffff";
            this.chart.options.plugins.legend.labels.color = labelColor();
            this.chart.update();
        },
    },
};
</script>
