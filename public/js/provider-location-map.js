(function () {
  function init() {
    var mapEl = document.getElementById("providerLocationMap");

    if (!mapEl || !window.L || !window.wellsharpMapTiles) return;

    var latitude = parseFloat(mapEl.dataset.lat);
    var longitude = parseFloat(mapEl.dataset.lng);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
      mapEl.innerHTML = '<div class="map-empty-message">No valid map location is saved.</div>';
      return;
    }

    var map = L.map(mapEl, {
      center: [latitude, longitude],
      zoom: 15,
      minZoom: 2,
      maxZoom: 18,
      scrollWheelZoom: false,
    });

    window.wellsharpMapTiles(map, function () {
      mapEl.insertAdjacentHTML("beforeend", '<div class="map-empty-message">Map tiles are unavailable. Coordinates are saved for this provider.</div>');
    });

    L.marker([latitude, longitude])
      .addTo(map)
      .bindPopup(mapEl.dataset.address || "Provider location")
      .openPopup();

    setTimeout(function () { map.invalidateSize(); }, 120);
  }

  if (window.L && window.wellsharpMapTiles) init();
  else window.addEventListener("wellsharp:maps-ready", init, { once: true });
})();
