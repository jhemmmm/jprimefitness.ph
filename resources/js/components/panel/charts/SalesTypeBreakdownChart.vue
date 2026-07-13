<template>
    <div class="panel-card h-100">
        <div class="panel-card-header">
            <div>
                <div class="panel-card-title">Sales Mix</div>
                <div class="panel-card-sub">Share of sales by transaction type</div>
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
import { chartTheme, isDark, labelColor, pesoAmount, pesoLabel } from "./chartTheme";

Chart.register(DoughnutController, ArcElement, Tooltip, Legend, ChartDataLabels);

const centerTextPlugin = {
    id: "salesTypeCenterText",
    afterDraw: function (chart) {
        const {
            ctx,
            chartArea: { left, top, width, height },
        } = chart;
        const total = chart.data.datasets[0].data.reduce((sum, value) => sum + value, 0);

        const centerX = left + width / 2;
        const centerY = top + height / 2;

        ctx.save();
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.font = "bold 26px Inter, sans-serif";
        ctx.fillStyle = isDark() ? "#ffffff" : "#0d0f12";
        ctx.fillText(pesoLabel(total), centerX, centerY - 8);
        ctx.font = "500 11px Inter, sans-serif";
        ctx.fillStyle = labelColor();
        ctx.fillText("Total Sales", centerX, centerY + 16);
        ctx.restore();
    },
};

export default {
    mixins: [chartTheme],
    props: {
        breakdown: {
            type: Array,
            default: function () {
                return [];
            },
        },
    },
    data: function () {
        return {
            colors: ["#c8102e", "#0d6efd", "#f59f00", "#198754"],
        };
    },
    watch: {
        breakdown: function () {
            this.updateChart();
        },
    },
    methods: {
        labels: function () {
            return this.breakdown.map((row) => row.label);
        },
        values: function () {
            return this.breakdown.map((row) => row.total_sales);
        },
        buildChart: function () {
            this.chart = new Chart(this.$refs.canvasRef, {
                type: "doughnut",
                plugins: [centerTextPlugin],
                data: {
                    labels: this.labels(),
                    datasets: [
                        {
                            data: this.values(),
                            backgroundColor: this.colors,
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
                    cutout: "58%",
                    plugins: {
                        datalabels: {
                            color: "#ffffff",
                            font: { size: 11, weight: "bold", family: "Inter, sans-serif" },
                            textAlign: "center",
                            display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                            formatter: (value, ctx) => `${ctx.chart.data.labels[ctx.dataIndex]}\n${pesoLabel(value)}`,
                        },
                        legend: {
                            position: "bottom",
                            labels: {
                                color: labelColor(),
                                padding: 16,
                                usePointStyle: true,
                                pointStyleWidth: 16,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ` ${ctx.label}: ${pesoAmount(ctx.parsed)}`;
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

            this.chart.data.labels = this.labels();
            this.chart.data.datasets[0].data = this.values();
            this.chart.update();
        },
        refreshTheme: function () {
            if (!this.chart) {
                return;
            }

            this.chart.data.datasets[0].borderColor = isDark() ? "#1a1d21" : "#ffffff";
            this.chart.options.plugins.legend.labels.color = labelColor();
            this.chart.update();
        },
    },
};
</script>
