const CHART_JS_CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js';

const dashboardChartInstances = new WeakMap();

let chartJsPromise = null;

/**
 * Load Chart.js once from CDN when needed.
 *
 * @returns {Promise<any>}
 */
const ensureChartJs = () => {
    if (window.Chart) {
        return Promise.resolve(window.Chart);
    }

    if (chartJsPromise !== null) {
        return chartJsPromise;
    }

    chartJsPromise = new Promise((resolve, reject) => {
        const existingScript = document.querySelector('script[data-chart-js-cdn="true"]');

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(window.Chart), { once: true });
            existingScript.addEventListener('error', reject, { once: true });

            return;
        }

        const script = document.createElement('script');
        script.src = CHART_JS_CDN;
        script.async = true;
        script.dataset.chartJsCdn = 'true';
        script.addEventListener('load', () => resolve(window.Chart), { once: true });
        script.addEventListener('error', reject, { once: true });
        document.head.appendChild(script);
    });

    return chartJsPromise;
};

/**
 * Render all dashboard charts on the page.
 */
const initializeDashboardCharts = async () => {
    const chartContainers = document.querySelectorAll('[data-dashboard-chart]');

    if (chartContainers.length === 0) {
        return;
    }

    let ChartConstructor = null;

    try {
        ChartConstructor = await ensureChartJs();
    } catch (error) {
        console.error('Unable to load Chart.js', error);

        return;
    }

    chartContainers.forEach((container) => {
        const canvas = container.querySelector('canvas[data-chart-canvas]');
        const payloadRaw = container.getAttribute('data-chart-payload') ?? '';

        if (!(canvas instanceof HTMLCanvasElement) || payloadRaw === '') {
            return;
        }

        let payload;

        try {
            payload = JSON.parse(payloadRaw);
        } catch (error) {
            console.error('Invalid dashboard chart payload', error);

            return;
        }

        if (!Array.isArray(payload.labels) || !Array.isArray(payload.datasets)) {
            return;
        }

        const datasets = payload.datasets.map((dataset) => ({
            label: dataset.label ?? '',
            data: Array.isArray(dataset.data) ? dataset.data : [],
            borderColor: dataset.color ?? '#2563eb',
            backgroundColor: dataset.color ?? '#2563eb',
            borderWidth: 2,
            fill: false,
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 4,
        }));

        const existingChart = dashboardChartInstances.get(canvas);

        if (existingChart) {
            existingChart.destroy();
        }

        const context = canvas.getContext('2d');

        if (context === null) {
            return;
        }

        const chart = new ChartConstructor(context, {
            type: 'line',
            data: {
                labels: payload.labels,
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            maxTicksLimit: 10,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                        },
                    },
                },
            },
        });

        dashboardChartInstances.set(canvas, chart);
    });
};

document.addEventListener('DOMContentLoaded', () => {
    void initializeDashboardCharts();
});

document.addEventListener('livewire:navigated', () => {
    void initializeDashboardCharts();
});
