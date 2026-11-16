import { getToken } from '../helpers';
import { areaTooltip, mountChart, readConfig } from '../helpers';

export default (el) => {
    const { categories, labels, sales, revenue } = readConfig(el);
    const state = { tab: 'overview', factor: 1 };

    const seriesFor = () => {
        const scale = (list) => list.map((v) => Math.round(v * state.factor));
        if (state.tab === 'sales') return [{ name: labels.sales, data: scale(sales) }];
        if (state.tab === 'revenue') return [{ name: labels.revenue, data: scale(revenue) }];

        return [
            { name: labels.sales, data: scale(sales) },
            { name: labels.revenue, data: scale(revenue) },
        ];
    };
    const colorsFor = () => (state.tab === 'sales' ? [getToken('--chart-1')] : state.tab === 'revenue' ? [getToken('--brand-400')] : [getToken('--chart-1'), getToken('--brand-400')]);

    const build = (dark) => ({
        series: seriesFor(),
        legend: { show: false },
        colors: colorsFor(),
        chart: { fontFamily: 'Outfit, sans-serif', height: 310, type: 'area', toolbar: { show: false } },
        stroke: { curve: 'straight', width: state.tab === 'overview' ? [2, 2] : [2] },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.55, opacityTo: 0 } },
        markers: { size: 0, strokeColors: getToken('--surface'), strokeWidth: 2, hover: { size: 6 } },
        grid: { borderColor: dark ? getToken('--line') : getToken('--line'), strokeDashArray: 0, xaxis: { lines: { show: false } }, yaxis: { lines: { show: true } } },
        dataLabels: { enabled: false },
        tooltip: { enabled: true, shared: true, intersect: false, custom: areaTooltip },
        xaxis: {
            type: 'category',
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            crosshairs: {
                show: true,
                width: 2,
                position: 'back',
                opacity: 1,
                stroke: { width: 0, color: 'none' },
                fill: { type: 'gradient', gradient: { colorFrom: dark ? getToken('--soft') : getToken('--surface'), colorTo: getToken('--brand-400'), stops: [0, 100], opacityFrom: 1, opacityTo: 1 } },
            },
            tooltip: { enabled: false },
            labels: { style: { fontSize: '12px', fontFamily: 'Outfit, sans-serif', colors: dark ? getToken('--muted') : getToken('--muted') } },
        },
        yaxis: {
            labels: { style: { fontSize: '12px', fontFamily: 'Outfit, sans-serif', colors: dark ? getToken('--muted') : getToken('--muted') } },
            title: { text: '', style: { fontSize: '0px' } },
        },
    });

    const chart = mountChart(el, build);

    // The header (tabs / date range) lives in the card's Alpine scope and dispatches `chart-update`.
    el.addEventListener('chart-update', (event) => {
        state.tab = event.detail.tab;
        state.factor = event.detail.factor;
        chart.updateOptions(build(document.documentElement.classList.contains('dark')));
    });
};
