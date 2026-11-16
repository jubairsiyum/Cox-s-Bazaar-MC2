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
        selectedBrand: 'all',
    };

    const defaultColors = [
        '#465fff',
        '#f59e0b',
        '#10b981',
        '#8b5cf6',
    ];

    const getActiveBrands = () => {
        if (state.selectedBrand === 'all') {
            return brands;
        }
        const filtered = brands.filter((b) => b.name === state.selectedBrand);
        return filtered.length > 0 ? filtered : brands;
    };

    const getColors = (activeList) => {
        return activeList.map((b) => {
            if (b.colorHex) return b.colorHex;
            const originalIndex = brands.findIndex((orig) => orig.name === b.name);
            return defaultColors[originalIndex >= 0 ? originalIndex % defaultColors.length : 0];
        });
    };

    const getSeries = (activeList) => {
        return activeList.map((b) => ({
            name: b.name,
            data: state.mode === 'volume' ? b.volumeData : b.shareData,
        }));
    };

    const build = (dark) => {
        const isShare = state.mode === 'share';
        const activeBrands = getActiveBrands();
        const colors = getColors(activeBrands);
        const series = getSeries(activeBrands);

        let yMax = 50;
        if (!isShare) {
            let maxVal = 0;
            activeBrands.forEach((b) => {
                const bMax = Math.max(...(b.volumeData || []));
                if (bMax > maxVal) maxVal = bMax;
            });
            yMax = Math.ceil((maxVal * 1.15) / 5) * 5;
            if (yMax < 10) yMax = 10;
        }

        return {
            series: series,
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
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 350,
                    dynamicAnimation: {
                        enabled: true,
                        speed: 300,
                    },
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
                custom: function ({ series: chartSeries, seriesIndex, dataPointIndex, w }) {
                    const category = w.globals?.categoryLabels?.[dataPointIndex] ??
                        w.config.xaxis?.categories?.[dataPointIndex] ?? '';
                    const seriesColors = w.config.colors ?? colors;
                    const seriesList = w.config.series ?? [];

                    if (!seriesList.length) return '';

                    const rows = seriesList
                        .map((s, idx) => {
                            let val = chartSeries[idx]?.[dataPointIndex];
                            if (val === undefined && chartSeries[dataPointIndex] !== undefined && seriesList.length === 1) {
                                val = chartSeries[dataPointIndex];
                            }
                            if (val === undefined || val === null) return '';

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
                        .filter(Boolean)
                        .join('');

                    if (!rows) return '';

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
                max: isShare ? 50 : yMax,
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
        if (event.detail?.selectedBrand !== undefined) {
            state.selectedBrand = event.detail.selectedBrand;
        }
        const dark = document.documentElement.classList.contains('dark');
        const nextOpts = build(dark);
        chart.updateOptions(nextOpts, false, true);
    });

    el.addEventListener('highlight-brand', (event) => {
        const name = event.detail?.brandName;
        if (name && typeof chart.highlightSeries === 'function') {
            chart.highlightSeries(name);
        }
    });

    el.addEventListener('unhighlight-brand', () => {
        if (typeof chart.highlightSeries === 'function') {
            chart.highlightSeries('');
        }
    });
};
