import { getToken } from '../helpers';
import jsVectorMap from 'jsvectormap';
import 'jsvectormap/dist/jsvectormap.min.css';
import 'jsvectormap/dist/maps/world';
import { readConfig } from '../helpers';

export default (el) => {
    const { markers = [], mapColor = getToken('--line') } = readConfig(el);

    new jsVectorMap({
        selector: el,
        map: 'world',
        backgroundColor: 'transparent',
        markerStyle: { initial: { fill: getToken('--chart-1'), r: 4 } },
        markersSelectable: true,
        markers: markers.map((marker) => ({
            name: marker.name,
            coords: marker.latLng,
            style: {
                initial: {
                    fill: marker.style?.fill,
                    stroke: marker.style?.stroke ?? marker.style?.borderColor,
                    strokeWidth: marker.style?.borderWidth,
                    strokeOpacity: marker.style?.strokeOpacity,
                },
            },
        })),
        zoomButtons: false,
        zoomOnScroll: false,
        zoomMax: 12,
        zoomMin: 1,
        zoomAnimate: true,
        zoomStep: 1.5,
        regionStyle: {
            initial: { fill: mapColor, fillOpacity: 1, fontFamily: 'Outfit', stroke: 'none', strokeWidth: 0, strokeOpacity: 0 },
            hover: { fillOpacity: 0.7, cursor: 'pointer', fill: getToken('--chart-1'), stroke: 'none' },
            selected: { fill: getToken('--chart-1') },
            selectedHover: {},
        },
    });
};
