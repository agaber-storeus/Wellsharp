(function () {
  function init() {
    var mapEl = document.getElementById("providerLocationMap");
    var dataEl = document.getElementById("providerLocationData");
    if (!mapEl || !dataEl || !window.L || !window.wellsharpMapTiles) return;

    var locations = JSON.parse(dataEl.textContent || "[]");
    var map = L.map(mapEl, { center: [20, 0], zoom: 2, minZoom: 2, maxZoom: 18, scrollWheelZoom: false });
    var markers = new Map();

    window.wellsharpMapTiles(map, function () {
      mapEl.insertAdjacentHTML("beforeend", '<div class="map-empty-message">Map tiles are unavailable. Coordinates are saved for these provider locations.</div>');
    });

    function selectLocation(key, focusMarker) {
      document.querySelectorAll(".provider-location-row[data-location-key]").forEach(function (row) {
        row.classList.toggle("is-map-selected", row.dataset.locationKey === key);
      });
      markers.forEach(function (marker, markerKey) {
        var selected = markerKey === key;
        marker.setOpacity(selected ? 1 : 0.72);
        marker.setZIndexOffset(selected ? 1000 : 0);
        if (marker._icon) marker._icon.classList.toggle("is-map-selected", selected);
      });
      var marker = markers.get(key);
      if (focusMarker && marker) map.setView(marker.getLatLng(), Math.max(map.getZoom(), 12));
    }

    locations.forEach(function (location) {
      var marker = L.marker([location.latitude, location.longitude])
        .addTo(map)
        .bindPopup(location.location || "Provider location");
      marker.on("click", function () { selectLocation(location.key, false); });
      markers.set(location.key, marker);
    });

    document.addEventListener("click", function (event) {
      var row = event.target.closest(".provider-location-row[data-location-key]");
      if (row) selectLocation(row.dataset.locationKey, true);
    });
    document.addEventListener("keydown", function (event) {
      var row = event.target.closest(".provider-location-row[data-location-key]");
      if (row && (event.key === "Enter" || event.key === " ")) {
        event.preventDefault();
        selectLocation(row.dataset.locationKey, true);
      }
    });

    var markerList = Array.from(markers.values());
    if (markerList.length > 1) map.fitBounds(L.latLngBounds(markerList.map(function (marker) { return marker.getLatLng(); })), { padding: [28, 28] });
    else if (markerList.length === 1) map.setView(markerList[0].getLatLng(), 15);
    if (locations[0]) selectLocation(locations[0].key, false);
    setTimeout(function () { map.invalidateSize(); }, 120);
  }

  if (window.L && window.wellsharpMapTiles) init();
  else window.addEventListener("wellsharp:maps-ready", init, { once: true });
})();
