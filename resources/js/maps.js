import L from 'leaflet';
import { maplibreGL } from '@maplibre/maplibre-gl-leaflet';
import { setWorkerUrl } from 'maplibre-gl';
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import 'leaflet/dist/leaflet.css';
import 'maplibre-gl/dist/maplibre-gl.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

const BASEMAP_CONFIG = Object.freeze({
    style: 'https://tiles.openfreemap.org/styles/liberty',
});

setWorkerUrl(maplibreWorkerUrl);

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIconRetina,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

window.L = L;
window.wellsharpMapTiles = function (map, onUnavailable) {
    let unavailableReported = false;
    const basemap = maplibreGL(BASEMAP_CONFIG).addTo(map);

    basemap.getMaplibreMap().on('error', function () {
        if (!unavailableReported && onUnavailable) {
            unavailableReported = true;
            onUnavailable();
        }
    });

    return basemap;
};

window.wellsharpClassDurationLabel = function (point) {
    const days = Number(point?.durationDays);

    return Number.isInteger(days) && days > 0
        ? `${days} ${days === 1 ? 'day' : 'days'}`
        : 'Duration not configured';
};

window.wellsharpClassMarkerIcon = function (point) {
    const group = ['ongoing', 'upcoming', 'past'].includes(point?.group) ? point.group : 'unknown';
    const days = Number(point?.durationDays);
    const badge = Number.isInteger(days) && days > 0 ? `${days}d` : '—';

    return L.divIcon({
        className: 'wellsharp-class-marker',
        html: `<span class="wellsharp-marker-badge">${badge}</span><span class="wellsharp-marker-pin ${group}"></span>`,
        iconSize: [58, 62],
        iconAnchor: [29, 62],
        popupAnchor: [0, -56],
    });
};

window.dispatchEvent(new Event('wellsharp:maps-ready'));
