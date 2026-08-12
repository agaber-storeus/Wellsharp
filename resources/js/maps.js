import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIconRetina,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

window.L = L;
window.wellsharpMapTiles = function (map, onUnavailable) {
    let fallbackStarted = false;
    const primary = L.tileLayer('https://iadc.wellsharp.org/MapTiles/{z}-{x}-{y}.png', { attribution: '' }).addTo(map);

    primary.on('tileerror', function () {
        if (fallbackStarted) {
            return;
        }

        fallbackStarted = true;
        map.removeLayer(primary);
        const fallback = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        fallback.on('tileerror', function () {
            if (onUnavailable) {
                onUnavailable();
            }
        });
    });

    return primary;
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
