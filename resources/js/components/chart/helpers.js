import ApexCharts from 'apexcharts';

window.ApexCharts = ApexCharts;

export const isDark = () => document.documentElement.classList.contains('dark');
export const isReducedMotion = () => typeof window !== 'undefined' && matchMedia('(prefers-reduced-motion: reduce)').matches;

export const getToken = (token, fallback = '') => {
    if (typeof window === 'undefined') return fallback;
    return getComputedStyle(document.documentElement).getPropertyValue(token).trim() || fallback;
};

export const getChartColors = () => [
    getToken('--chart-1'),
    getToken('--chart-2'),
    getToken('--chart-3'),
    getToken('--chart-4'),
];

/** Reads the JSON config rendered on the chart element (`data-config`). */
export const readConfig = (el) => JSON.parse(el.dataset.config || '{}');

/**
 * Renders an ApexCharts instance and rebuilds its options when the light / dark theme or color theme is toggled.
 * `build(dark)` must return the full options object.
 * Delays initial render until the parent card is revealed (`.in`), and disables animation on reduced motion.
 */
export function mountChart(el, build) {
    let chart = null;
    let rendered = false;

    const renderChart = () => {
        if (rendered) return;
        rendered = true;
        const dark = isDark();
        const opts = build(dark);
        if (isReducedMotion() && opts.chart) {
            opts.chart.animations = { enabled: false };
        }
        chart = new ApexCharts(el, opts);
        chart.render();
    };

    const host = el.closest('.rv');
    if (!host || host.classList.contains('in') || isReducedMotion()) {
        renderChart();
    } else {
        const obs = new MutationObserver(() => {
            if (host.classList.contains('in')) {
                obs.disconnect();
                renderChart();
            }
        });
        obs.observe(host, { attributes: true, attributeFilter: ['class'] });
    }

    const update = () => {
        if (chart) {
            const dark = isDark();
            const opts = build(dark);
            if (isReducedMotion() && opts.chart) {
                opts.chart.animations = { enabled: false };
            }
            chart.updateOptions(opts);
        }
    };

    new MutationObserver(update).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class', 'data-theme'],
    });

    window.addEventListener('theme-changed', update);

    return {
        updateOptions: (opts, redraw, animate) => {
            if (chart) chart.updateOptions(opts, redraw, animate);
        },
        highlightSeries: (name) => {
            if (chart && typeof chart.highlightSeries === 'function') chart.highlightSeries(name);
        },
    };
}

/** Shared "area chart" tooltip used by the statistics / employee growth style charts. */
export const areaTooltip = ({ series: chartSeries, dataPointIndex, w }) => {
    const category = w.globals?.categoryLabels?.[dataPointIndex] ?? w.config.xaxis?.categories?.[dataPointIndex] ?? '';
    const colors = w.config.colors ?? [getToken('--chart-1')];
    const seriesList = w.config.series ?? [];

    const rows = seriesList
        .map((s, idx) => {
            const val = chartSeries[idx]?.[dataPointIndex];
            const color = colors[idx] ?? getToken('--chart-1');
            const formattedVal = typeof val === 'number' ? val.toLocaleString() : (val ?? '');
            return `
                <div class="flex items-center gap-2">
                  <div class="flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full shrink-0" style="background-color: ${color}"></span>
                    <span class="w-13 text-xs text-ink">${s.name ?? ''}</span>
                    <span class="text-xs font-medium text-muted">:</span>
                  </div>
                  <span class="text-xs font-medium text-ink">${formattedVal}</span>
                </div>
              `;
        })
        .join('');

    return `
            <div class="min-w-32.5">
              <p class="mb-1 text-[10px] leading-4 text-muted">${category}</p>
              <div class="flex flex-col gap-1">
                ${rows}
              </div>
            </div>
          `;
};
