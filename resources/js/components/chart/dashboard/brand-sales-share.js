import { getToken, mountChart, readConfig } from '../helpers';

const formatCompactBdt = (value) => {
    const num = Number(value) || 0;
    const abs = Math.abs(num);
    if (abs >= 10000000) {
        return `৳ ${(num / 10000000).toFixed(2).replace(/\.00$/, '')}Cr`;
    }
    if (abs >= 100000) {
        return `৳ ${(num / 100000).toFixed(1).replace(/\.0$/, '')}L`;
    }
    if (abs >= 1000) {
        return `৳ ${(num / 1000).toFixed(1).replace(/\.0$/, '')}K`;
    }
    return `৳ ${Math.round(num).toLocaleString('en-IN')}`;
};

const formatAxisBdt = (value) => {
    const num = Number(value) || 0;
    if (num === 0) return '0';
    const abs = Math.abs(num);
    if (abs >= 10000000) {
        return `৳${(num / 10000000).toFixed(1).replace(/\.0$/, '')}Cr`;
    }
    if (abs >= 100000) {
        return `৳${(num / 100000).toFixed(1).replace(/\.0$/, '')}L`;
    }
    if (abs >= 1000) {
        return `৳${(num / 1000).toFixed(0)}K`;
    }
    return `৳${Math.round(num)}`;
};

export default (el) => {
    if (el.__chart_mounted) return;
    el.__chart_mounted = true;

    const config = readConfig(el);
    const categories = config.categories || [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    const brands = Array.isArray(config.brands) ? config.brands : [];
    const maxVisible = Number(config.maxVisible) || 4;
    const volumeScale = config.volumeScale || 'raw'; // 'raw' (BDT) or 'lakh' (legacy demo)

    const state = {
        mode: config.defaultMode || 'share',
        selectedBrand: 'all',
    };

    const defaultColors = [
        '#465fff',
        '#f59e0b',
        '#10b981',
        '#8b5cf6',
        '#06b6d4',
        '#ec4899',
        '#f97316',
        '#14b8a6',
        '#6366f1',
        '#84cc16',
        '#e11d48',
        '#0ea5e9',
    ];

    const buildOthersSeries = () => {
        const rest = brands.slice(maxVisible);
        if (!rest.length) return null;

        const len = categories.length;
        const shareData = Array.from({ length: len }, (_, i) => {
            const sum = rest.reduce((acc, b) => acc + (Number(b.shareData?.[i]) || 0), 0);
            return Number(sum.toFixed(1));
        });
        const volumeData = Array.from({ length: len }, (_, i) => {
            const sum = rest.reduce((acc, b) => acc + (Number(b.volumeData?.[i]) || 0), 0);
            return Number(sum.toFixed(2));
        });

        return {
            name: `Others (${rest.length})`,
            colorHex: '#94a3b8',
            shareData,
            volumeData,
            isOthers: true,
        };
    };

    const getActiveBrands = () => {
        if (!brands.length) return [];

        if (state.selectedBrand === 'all') {
            if (brands.length <= maxVisible) {
                return brands;
            }
            const top = brands.slice(0, maxVisible);
            const others = buildOthersSeries();
            return others ? [...top, others] : top;
        }

        if (state.selectedBrand === '__others__') {
            const others = buildOthersSeries();
            return others ? [others] : brands.slice(0, maxVisible);
        }

        const matched = brands.find((b) => b.name === state.selectedBrand);
        return matched ? [matched] : brands.slice(0, maxVisible);
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
            data: state.mode === 'volume' ? (b.volumeData || []) : (b.shareData || []),
        }));
    };

    const formatVolumeValue = (val) => {
        const num = Number(val) || 0;
        if (volumeScale === 'lakh') {
            return `৳ ${num.toLocaleString('en-IN')}L`;
        }
        return formatCompactBdt(num);
    };

    const formatVolumeAxis = (val) => {
        const num = Number(val) || 0;
        if (num === 0) return '0';
        if (volumeScale === 'lakh') {
            return `৳${num.toFixed(0)}L`;
        }
        return formatAxisBdt(num);
    };

    const computeYAxisMax = (activeList, isShare) => {
        let maxVal = 0;
        activeList.forEach((b) => {
            const arr = isShare ? (b.shareData || []) : (b.volumeData || []);
            arr.forEach((v) => {
                const n = Number(v) || 0;
                if (n > maxVal) maxVal = n;
            });
        });

        if (isShare) {
            if (maxVal <= 0) return 50;
            const padded = Math.ceil((maxVal * 1.2) / 10) * 10;
            return Math.min(100, Math.max(20, padded));
        }

        if (maxVal <= 0) return volumeScale === 'lakh' ? 20 : 10000;
        if (volumeScale === 'lakh') {
            return Math.max(10, Math.ceil((maxVal * 1.15) / 5) * 5);
        }

        const magnitude = Math.pow(10, Math.floor(Math.log10(maxVal)));
        const step = magnitude / 2;
        return Math.ceil((maxVal * 1.15) / step) * step;
    };

    const build = (dark) => {
        const isShare = state.mode === 'share';
        const activeBrands = getActiveBrands();
        const colors = getColors(activeBrands);
        const series = getSeries(activeBrands);
        const yMax = computeYAxisMax(activeBrands, isShare);
        const dashArray = activeBrands.map((b) => (b.isOthers ? 5 : 0));
        const strokeWidths = activeBrands.map((b) => (b.isOthers ? 2 : 2.5));

        return {
            series,
            legend: {
                show: false,
            },
            colors,
            chart: {
                fontFamily: 'Outfit, sans-serif',
                height: 260,
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
                    speed: 850,
                    dynamicAnimation: {
                        enabled: true,
                        speed: 320,
                    },
                },
            },
            stroke: {
                curve: 'smooth',
                width: strokeWidths,
                dashArray,
            },
            fill: {
                type: 'gradient',
                gradient: {
                    opacityFrom: activeBrands.length === 1 ? 0.42 : 0.28,
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
                                ? `${Number(val).toFixed(1).replace(/\.0$/, '')}%`
                                : formatVolumeValue(val);

                            return `
                                <div class="flex items-center justify-between gap-4 py-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="size-2 rounded-full shrink-0" style="background-color: ${color}"></span>
                                        <span class="text-xs font-medium text-ink truncate">${s.name ?? ''}</span>
                                    </div>
                                    <span class="text-xs font-bold text-ink tabular-nums shrink-0">${formattedVal}</span>
                                </div>
                            `;
                        })
                        .filter(Boolean)
                        .join('');

                    if (!rows) return '';

                    return `
                        <div class="rounded-xl border border-line bg-surface p-3 shadow-lg min-w-48">
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
                categories,
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
                max: yMax,
                tickAmount: 5,
                labels: {
                    formatter: (val) => {
                        if (val === 0) return '0';
                        return isShare ? `${Math.round(val)}%` : formatVolumeAxis(val);
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
