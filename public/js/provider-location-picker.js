(function () {
  function init() {
  var mapEl = document.getElementById("providerLocationMap");
  var latitude = document.getElementById("latitude");
  var longitude = document.getElementById("longitude");
  var message = document.getElementById("providerLocationMessage");
  var clearButton = document.getElementById("clearProviderLocation");

  if (!mapEl || !latitude || !longitude || !window.L) {
    if (message) message.textContent = "Map picker is unavailable. You can save the address without a map pin.";
    return;
  }

  var initialLat = parseFloat(mapEl.dataset.lat);
  var initialLng = parseFloat(mapEl.dataset.lng);
  var hasInitialLocation = Number.isFinite(initialLat) && Number.isFinite(initialLng);
  var map = L.map(mapEl, { center: hasInitialLocation ? [initialLat, initialLng] : [20, 0], zoom: hasInitialLocation ? 12 : 2, minZoom: 2, maxZoom: 18 });
  window.wellsharpMapTiles(map, function () {
    if (message) message.textContent = "Map tiles are unavailable. The provider address can still be saved.";
  });
  var marker = null;

  function setLocation(lat, lng) {
    latitude.value = Number(lat).toFixed(7);
    longitude.value = Number(lng).toFixed(7);
    if (!marker) {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on("dragend", function (event) {
        var position = event.target.getLatLng();
        setLocation(position.lat, position.lng);
      });
    }
    marker.setLatLng([lat, lng]);
    map.setView([lat, lng], Math.max(map.getZoom(), 12));
    if (message) message.textContent = "Map location selected. Drag the pin to adjust it.";
  }

  if (hasInitialLocation) setLocation(initialLat, initialLng);
  map.on("click", function (event) { setLocation(event.latlng.lat, event.latlng.lng); });
  if (clearButton) clearButton.addEventListener("click", function () { latitude.value = ""; longitude.value = ""; if (marker) { marker.remove(); marker = null; } if (message) message.textContent = "No map location selected."; });
  setTimeout(function () { map.invalidateSize(); }, 120);
  }

  if (window.L && window.wellsharpMapTiles) init();
  else window.addEventListener("wellsharp:maps-ready", init, { once: true });
})();
