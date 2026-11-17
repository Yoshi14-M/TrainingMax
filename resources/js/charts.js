import Chart from 'chart.js/auto';
import { longDate, shortDate, tooltipLine } from './chart-core';

/**
 * ひとつのグラフに各指標を折れ線で重ねる（S-13・S-14）。
 * - 数値は画面に並べず、カーソルを合わせた（タップした）ときの吹き出しに、その日の各指標の値を表示する
 * - 凡例を押すと、指標ごとに表示・非表示を切り替えられる
 * - 単位の違う指標は、左右2つの縦軸に分ける（使う指標が全て非表示の縦軸は自動で隠れる）
 * - 横軸は日付（1セッション／1測定日で1点）。記録のない日の指標は線をつなぐ（spanGaps）
 */
export default (config) => ({
    chart: null,

    init() {
        this.$nextTick(() => this.render());
    },

    render() {
        const canvas = this.$refs.canvas;

        if (!canvas || config.labels.length === 0) {
            return;
        }

        this.chart?.destroy();

        this.chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: config.labels,
                datasets: config.datasets.map((dataset) => ({
                    label: dataset.label,
                    unit: dataset.unit,
                    data: dataset.data,
                    hidden: dataset.hidden,
                    borderColor: dataset.color,
                    backgroundColor: dataset.color,
                    yAxisID: dataset.axis === 'left' ? 'yLeft' : 'yRight',
                    spanGaps: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    tension: 0.15,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            callback(value) {
                                return shortDate(this.getLabelForValue(value));
                            },
                        },
                    },
                    yLeft: {
                        type: 'linear',
                        position: 'left',
                        display: 'auto',
                        title: { display: true, text: config.axes.left },
                    },
                    yRight: {
                        type: 'linear',
                        position: 'right',
                        display: 'auto',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: config.axes.right },
                    },
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        // その日に記録のある指標だけを表示する
                        filter: (item) => item.parsed.y !== null,
                        callbacks: {
                            title: (items) => longDate(config.labels[items[0].dataIndex]),
                            label: (item) => tooltipLine(item.dataset.label, item.parsed.y, item.dataset.unit),
                        },
                    },
                },
            },
        });
    },
});
