import { getToken, mountChart, readConfig } from '../helpers';

export default (el) => {
    let dark = false;
    const isDarkMode = { get value() { return dark; } };

    const config = readConfig(el);
    const categories = config.categories || [];
    const series = config.series || [{ name: 'Sales', data: [] }];

    const formatBdt = (value) => {
        const num = Number(value) || 0;
        return `৳ ${Math.round(num).toLocaleString('en-US')}`;
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

    const options = () => ({
        legend: {
            show: false,
        },
        colors: [getToken('--chart-1')],
        chart: {
            fontFamily: 'Outfit, sans-serif',
            height: 180,
            type: 'area',
            toolbar: {
                show: false,
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 700,
            },
        },
        stroke: {
            curve: 'smooth',
            width: 2,
        },
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0.55,
                opacityTo: 0,
            },
        },
        markers: {
            size: 0,
            colors: [getToken('--surface')],
            strokeColors: [getToken('--chart-1')],
            strokeWidth: 2,
            hover: {
                size: 5,
            },
        },
        grid: {
            position: 'back',
            borderColor: isDarkMode.value ? getToken('--line') : getToken('--line'),
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
            padding: {
                top: -10,
                right: 8,
                bottom: 0,
                left: 10,
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
                    w.config.xaxis?.categories?.[dataPointIndex] ??
                    categories[dataPointIndex] ??
                    `Day ${dataPointIndex + 1}`;
                const colors = w.config.colors ?? [getToken('--chart-1')];
                const seriesList = w.config.series ?? [];
                const rows = seriesList
                    .map((s, idx) => {
                        const val = chartSeries[idx]?.[dataPointIndex] ?? 0;
                        const color = colors[idx] ?? getToken('--chart-1');
                        const formattedVal = formatBdt(val);
                        return `
                          <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1.5">
                              <span class="size-1.5 rounded-full shrink-0" style="background-color: ${color}"></span>
                              <span class="text-xs text-ink font-medium">${s.name ?? 'Daily Sales'}</span>
                              <span class="text-xs font-medium text-muted">:</span>
                            </div>
                            <span class="text-xs font-bold text-ink">${formattedVal}</span>
                          </div>
                        `;
                    })
                    .join('');

                return `
                  <div class="min-w-32.5 rounded-xl border border-line bg-surface p-2.5 shadow-card text-left">
                    <p class="mb-1 text-[10px] font-medium leading-4 text-muted">${category}</p>
                    <div class="flex flex-col gap-1">
                      ${rows}
                    </div>
                  </div>
                `;
            },
        },
        xaxis: {
            type: 'category',
            categories,
            tickAmount: 7,
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
                        colorFrom: isDarkMode.value ? getToken('--soft') : getToken('--surface'),
                        colorTo: getToken('--brand-400'),
                        stops: [0, 100],
                        opacityFrom: 1,
                        opacityTo: 1,
                    },
                },
            },
            labels: {
                hideOverlappingLabels: true,
                style: {
                    fontSize: '11px',
                    fontFamily: 'Outfit, sans-serif',
                    colors: isDarkMode.value ? getToken('--muted') : getToken('--muted'),
                },
            },
            tooltip: {
                enabled: false,
            },
        },
        yaxis: {
            min: 0,
            tickAmount: 3,
            labels: {
                formatter: (val) => formatAxisBdt(val),
                style: {
                    fontSize: '11px',
                    fontFamily: 'Outfit, sans-serif',
                    colors: isDarkMode.value ? getToken('--muted') : getToken('--muted'),
                },
            },
            title: {
                text: undefined,
            },
        },
    });

    mountChart(el, (isDark) => {
        dark = isDark;
        const opts = options();
        return { ...opts, series };
    });
};
