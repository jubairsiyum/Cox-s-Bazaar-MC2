import { getToken } from '../helpers';
import { mountChart, readConfig } from '../helpers';

export default (el) => {
    const { categories, data, seriesName } = readConfig(el);

    mountChart(el, (dark) => ({
        series: [{ name: seriesName, data }],
        colors: [getToken('--chart-1')],
        chart: { fontFamily: 'Outfit, sans-serif', type: 'bar', height: 200, toolbar: { show: false } },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '38%',
                borderRadius: 8,
                borderRadiusApplication: 'around',
                dataLabels: { position: 'top' },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => `${(val / 100).toFixed(1)}%`,
            offsetY: -20,
            style: { fontSize: '12px', fontFamily: 'Outfit, sans-serif', fontWeight: 400, colors: [dark ? getToken('--muted') : getToken('--ink')] },
            dropShadow: { enabled: false },
        },
        stroke: { show: false },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: dark ? getToken('--muted') : getToken('--ink'), fontFamily: 'Outfit, sans-serif' } },
        },
        legend: { show: false },
        yaxis: { max: 420, min: 0, tickAmount: 4, labels: { show: false } },
        grid: {
            borderColor: dark ? getToken('--line') : getToken('--line'),
            strokeDashArray: 0,
            padding: { left: -10, right: 0 },
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } },
        },
        fill: {
            type: 'gradient',
            gradient: {
                type: 'vertical',
                shadeIntensity: 1,
                opacityFrom: 1,
                opacityTo: 1,
                stops: [0, 100],
                colorStops: [
                    { offset: 0, color: getToken('--chart-1'), opacity: 1 },
                    { offset: 100, color: getToken('--chart-1'), opacity: 1 },
                ],
            },
        },
        tooltip: { x: { show: false }, y: { formatter: (val) => `${(val / 100).toFixed(1)}%` } },
    }));
};
