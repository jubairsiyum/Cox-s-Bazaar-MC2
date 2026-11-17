import { getToken } from '../helpers';
import { mountChart } from '../helpers';

export default (el) => {
    let dark = false;
    const isDarkMode = { get value() { return dark; } };
    const computed = (fn) => fn;

    const series = [
        {
            name: 'Sales',
            data: [310, 440, 430, 400, 700, 810, 700, 770, 625, 470, 470, 530],
        },
        {
            name: 'Revenue',
            data: [540, 630, 570, 700, 825, 500, 370, 480, 750, 810, 650, 760],
        },
    ];
    const categories = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
    ];
    const options = computed(() => ({
        legend: {
            show: false,
        },
        colors: [getToken('--chart-1'), getToken('--brand-400')],
        chart: {
            fontFamily: 'Outfit, sans-serif',
            height: 310,
            type: 'area',
            toolbar: {
                show: false,
            },
        },
        stroke: {
            curve: 'straight',
            width: [2, 2],
        },
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0.55,
                opacityTo: 0,
            },
        },
        markers: {
            size: 5,
            colors: [getToken('--surface')],
            strokeColors: [getToken('--chart-1'), getToken('--brand-400')],
            strokeWidth: 2,
            hover: {
                size: 7,
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
                    '';
                const colors = w.config.colors ?? [getToken('--chart-1'), getToken('--brand-400')];
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
                        colorFrom: isDarkMode.value ? getToken('--soft') : getToken('--surface'),
                        colorTo: getToken('--brand-400'),
                        stops: [0, 100],
                        opacityFrom: 1,
                        opacityTo: 1,
                    },
                },
            },
            labels: {
                style: {
                    fontSize: '12px',
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
            max: 1000,
            tickAmount: 5,
            labels: {
                formatter: (val) => {
                    return val.toLocaleString();
                },
                style: {
                    fontSize: '12px',
                    fontFamily: 'Outfit, sans-serif',
                    colors: isDarkMode.value ? getToken('--muted') : getToken('--muted'),
                },
            },
            title: {
                text: undefined,
            },
        },
    }));

    mountChart(el, (isDark) => {
        dark = isDark;
        const opts = options();
        return { ...opts, series: series, chart: { ...opts.chart, type: 'area', height: 310 } };
    });
};
