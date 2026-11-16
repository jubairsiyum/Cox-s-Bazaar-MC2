import { getToken } from '../helpers';
import { mountChart, readConfig } from '../helpers';

export default (el) => {
    const { series } = readConfig(el);

    mountChart(el, (dark) => ({
        series,
        colors: [getToken('--brand-400')],
        chart: { fontFamily: 'Outfit, sans-serif', type: 'radialBar', height: 330, sparkline: { enabled: true } },
        plotOptions: {
            radialBar: {
                startAngle: -90,
                endAngle: 90,
                hollow: { size: '80%' },
                track: { background: dark ? getToken('--line') : getToken('--line'), strokeWidth: '100%', margin: 5 },
                dataLabels: {
                    name: { show: false },
                    value: { fontSize: '36px', fontWeight: '600', offsetY: -35, color: dark ? getToken('--surface') : getToken('--ink'), formatter: (val) => val + '%' },
                },
            },
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'horizontal',
                shadeIntensity: 0.5,
                gradientToColors: [getToken('--chart-1')],
                inverseColors: false,
                opacityFrom: 1,
                opacityTo: 1,
                stops: [0, 100],
                colorStops: [
                    { offset: 0, color: getToken('--brand-400'), opacity: 1 },
                    { offset: 100, color: getToken('--chart-1'), opacity: 1 },
                ],
            },
        },
        stroke: { lineCap: 'round' },
        labels: ['Progress'],
    }));
};
