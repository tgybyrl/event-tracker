// The dashboard's "Events over time" chart, drawn with Chart.js: this period
// as a filled line, the period before as a thin grey line, and a switch
// between 24 hours and 14 days.
//
// The data is not fetched: the server writes it into the page as JSON
// (<script id="volume-data">), already bucketed and labelled by the
// DashboardController. This file only draws it.
//
// Only the parts of Chart.js a line chart needs are imported and
// registered, so the bundle carries nothing else.
import {
    CategoryScale,
    Chart,
    Filler,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(CategoryScale, Filler, LinearScale, LineController, LineElement, PointElement, Tooltip);

const root = document.querySelector('[data-volume-chart]');
const series = JSON.parse(document.getElementById('volume-data').textContent);

// The panel's own design tokens (resources/css/app.css), read from CSS so
// the chart and the rest of the page cannot drift apart.
const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const brand = token('--color-brand');
const ink = token('--color-ink');
const muted = token('--color-muted');
const hairline = token('--color-hairline');

// '#2e6be6' + 0.3 -> 'rgba(46, 107, 230, 0.3)'
function withAlpha(hex, alpha) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}

Chart.defaults.font.family = token('--font-sans');
Chart.defaults.font.size = 12;
Chart.defaults.color = muted;

// People who ask their system for less motion get none.
const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;

let range = 'hours';

// The area under this period's line fades from a light brand tint to
// nothing, so the line reads as volume without the fill shouting.
function areaFill({ chart }) {
    const { ctx, chartArea } = chart;
    if (!chartArea) return null; // not laid out yet on the very first pass
    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, withAlpha(brand, 0.28));
    gradient.addColorStop(1, withAlpha(brand, 0));
    return gradient;
}

// A thin vertical line under the pointer, so the two values in the tooltip
// visibly belong to one moment.
const crosshair = {
    id: 'crosshair',
    afterDatasetsDraw(chart) {
        const active = chart.tooltip?.getActiveElements();
        if (!active?.length) return;
        const { ctx, chartArea } = chart;
        const x = active[0].element.x;
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = withAlpha(ink, 0.18);
        ctx.stroke();
        ctx.restore();
    },
};

function datasets(data) {
    const last = data.current.length - 1;
    return [
        {
            label: data.currentLabel,
            data: data.current,
            borderColor: brand,
            borderWidth: 2.5,
            backgroundColor: areaFill,
            fill: 'origin',
            // Smooth, but never overshooting: a curve through 0, 5, 0 must
            // not dip below zero, which a plain spline would.
            cubicInterpolationMode: 'monotone',
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: brand,
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 2,
            // The last stretch leads into the running hour or day, which is
            // still filling up: dashed, so it does not read as a drop.
            segment: {
                borderDash: (ctx) => (ctx.p1DataIndex === last ? [5, 4] : undefined),
            },
            order: 1,
        },
        {
            label: data.previousLabel,
            data: data.previous,
            borderColor: withAlpha(muted, 0.75),
            borderWidth: 1.5,
            fill: false,
            cubicInterpolationMode: 'monotone',
            pointRadius: 0,
            pointHoverRadius: 4,
            pointHoverBackgroundColor: muted,
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 2,
            order: 2,
        },
    ];
}

const chart = new Chart(root.querySelector('canvas'), {
    type: 'line',
    data: { labels: series[range].labels, datasets: datasets(series[range]) },
    plugins: [crosshair],
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: calm ? false : { duration: 900, easing: 'easeOutQuart' },
        // Hovering anywhere in a column of the chart picks that moment, for
        // both lines at once; no need to hit the line itself.
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 8 } },
        scales: {
            x: {
                grid: { display: false },
                border: { color: hairline },
                ticks: { maxRotation: 0, autoSkipPadding: 18 },
            },
            y: {
                beginAtZero: true,
                grid: { color: hairline },
                border: { display: false },
                ticks: { precision: 0, maxTicksLimit: 5, padding: 8 },
            },
        },
        plugins: {
            tooltip: {
                backgroundColor: ink,
                padding: 10,
                cornerRadius: 8,
                titleFont: { weight: '700' },
                bodyFont: { weight: '500' },
                boxWidth: 8,
                boxHeight: 8,
                boxPadding: 4,
                usePointStyle: true,
                callbacks: {
                    title: (items) => series[range].titles[items[0].dataIndex],
                    label: (item) => {
                        const n = item.parsed.y;
                        const running = item.datasetIndex === 0 && item.dataIndex === item.dataset.data.length - 1;
                        return ` ${item.dataset.label}: ${n.toLocaleString('en')} ${n === 1 ? 'event' : 'events'}${running ? ' so far' : ''}`;
                    },
                    labelPointStyle: () => ({ pointStyle: 'circle', rotation: 0 }),
                },
            },
        },
    },
});

// The legend is HTML (in the Blade view), so it matches the panel's type
// and wraps on a phone; only its two labels change with the range.
function updateLegend() {
    root.querySelector('[data-legend="current"]').textContent = series[range].currentLabel;
    root.querySelector('[data-legend="previous"]').textContent = series[range].previousLabel;
}

// The range switch swaps the data in place; Chart.js animates the change.
root.querySelectorAll('[data-range]').forEach((button) => {
    button.addEventListener('click', () => {
        range = button.dataset.range;
        root.querySelectorAll('[data-range]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
        chart.data.labels = series[range].labels;
        // Updated in place rather than replaced, so Chart.js morphs the
        // existing lines into the new ones instead of redrawing from zero.
        datasets(series[range]).forEach((fresh, i) => Object.assign(chart.data.datasets[i], fresh));
        updateLegend();
        chart.update();
    });
});
