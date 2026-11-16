import { getToken } from '../helpers';
import { mountChart, readConfig } from '../helpers';

export default (el) => {
    if (el.__chart_mounted) return;
    el.__chart_mounted = true;

    const config = readConfig(el);
    const categories = config.categories || [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    const brands = config.brands || [];

    const state = {
        mode: config.defaultMode || 'share',
    };

    const defaultColors = [
        '#465fff',
        '#f59e0b',
        '#10b981',
        '#8b5cf6',
    ];

    const getColors = () => {
        return brands.map((b, i) => b.colorHex || defaultColors[i % defaultColors.length]);
    };

    const getSeries = () => {
        return brands.map((b) => ({
            name: b.name,
            data: state.mode === 'volume' ? b.volumeData : b.shareData,
        }));
    };

    const build = (dark) => {
        const isShare = state.mode === 'share';
        const colors = getColors();

        return {
            series: getSeries(),
            legend: {
                show: false,
            },
            colors: colors,
            chart: {
                fontFamily: 'Outfit, sans-serif',
                height: 270,
                type: 'area',
                toolbar: {
                    show: false,
                },
                zoom: {
                    enabled: false,
                },
            },
            stroke: {
                curve: 'smooth',
                width: 2.5,
            },
            fill: {
                type: 'gradient',
                gradient: {
                    opacityFrom: 0.38,
                    opacityTo: 0.02,
                },
            },
            markers: {
                size: 0,
                colors: [dark ? getToken('--surface', '#1f2937') : '#ffffff'],
                strokeColors: colors,
                strokeWidth: 2,
                hover: {
                    size: 5,
                },
            },
            grid: {
                position: 'back',
                borderColor: dark ? getToken('--line', '#374151') : getToken('--line', '#e5e7eb'),
                strokeDashArray: 0,
                xaxis: {
                    lines: {
                        show: false,
                    },
                },
                yaxis: {
                    lines: {
                        show: true,
                    },
                },
            },
            dataLabels: {
                enabled: false,
            },
            tooltip: {
                enabled: true,
                shared: true,
                intersect: false,
                custom: function ({ series: chartSeries, dataPointIndex, w }) {
                    const category = w.globals?.categoryLabels?.[dataPointIndex] ??
                        w.config.xaxis?.categories?.[dataPointIndex] ?? '';
                    const seriesColors = w.config.colors ?? colors;
                    const seriesList = w.config.series ?? [];
                    const rows = seriesList
                        .map((s, idx) => {
                            const val = chartSeries[idx]?.[dataPointIndex];
                            const color = seriesColors[idx] ?? '#465fff';
                            const formattedVal = isShare
                                ? `${val}%`
                                : `৳ ${typeof val === 'number' ? val.toLocaleString() + 'L' : val}`;
                            return `
                                <div class="flex items-center justify-between gap-4 py-1">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2 rounded-full shrink-0" style="background-color: ${color}"></span>
                                        <span class="text-xs font-medium text-ink">${s.name ?? ''}</span>
                                    </div>
                                    <span class="text-xs font-bold text-ink">${formattedVal}</span>
                                </div>
                            `;
                        })
                        .join('');

                    return `
                        <div class="rounded-xl border border-line bg-surface p-3 shadow-lg min-w-44">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted border-b border-line pb-1">${category}</p>
                            <div class="flex flex-col divide-y divide-line/40">
                                ${rows}
                            </div>
                        </div>
                    `;
                },
            },
            xaxis: {
                type: 'category',
                categories: categories,
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
                crosshairs: {
                    show: true,
                    width: 2,
                    position: 'back',
                    opacity: 1,
                    stroke: {
                        width: 0,
                        color: 'none',
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            colorFrom: dark ? getToken('--soft', '#374151') : getToken('--surface', '#ffffff'),
                            colorTo: getToken('--brand-400', '#6366f1'),
                            stops: [0, 100],
                            opacityFrom: 1,
                            opacityTo: 1,
                        },
                    },
                },
                labels: {
                    style: {
                        fontSize: '11px',
                        fontFamily: 'Outfit, sans-serif',
                        colors: dark ? getToken('--muted', '#9ca3af') : getToken('--muted', '#6b7280'),
                    },
                },
                tooltip: {
                    enabled: false,
                },
            },
            yaxis: {
                min: 0,
                max: isShare ? 50 : undefined,
                tickAmount: 5,
                labels: {
                    formatter: (val) => {
                        if (val === 0) return '0';
                        return isShare ? `${val.toFixed(0)}%` : `৳${val.toFixed(0)}L`;
                    },
                    style: {
                        fontSize: '11px',
                        fontFamily: 'Outfit, sans-serif',
                        colors: dark ? getToken('--muted', '#9ca3af') : getToken('--muted', '#6b7280'),
                    },
                },
                title: {
                    text: undefined,
                },
            },
        };
    };

    const chart = mountChart(el, build);

    el.addEventListener('chart-update', (event) => {
        if (event.detail?.mode) {
            state.mode = event.detail.mode;
        }
        chart.updateOptions(build(document.documentElement.classList.contains('dark')));
    });

    el.addEventListener('toggle-brand', (event) => {
        if (event.detail?.brandName) {
            chart.toggleSeries(event.detail.brandName);
        }
    });
};
