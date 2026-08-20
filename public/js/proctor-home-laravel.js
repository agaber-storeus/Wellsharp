(function () {
  function init() {
  var shell = document.querySelector(".proctor-home-workspace");
  var reportType = document.getElementById("homeReportType");
  var timeFrame = document.getElementById("homeTimeFrame");
  var mapEl = document.getElementById("homeLeafletMap");
  var map;
  var markersLayer;
  var classPoints = window.wellsharpClassPoints || [];

  function validPoint(point) {
    if (point.lat === null || point.lat === undefined || point.lng === null || point.lng === undefined) return false;
    var lat = Number(point.lat);
    var lng = Number(point.lng);

    return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
  }

  function initMap() {
    if (!mapEl || !window.L) {
      if (mapEl) {
        mapEl.innerHTML = '<div class="map-empty-message">The map service is unavailable.</div>';
      }
      return;
    }

    map = L.map(mapEl, { center: [12, 24], zoom: 2, minZoom: 2, maxZoom: 10 });
    window.wellsharpMapTiles(map, function () {
      mapEl.innerHTML = '<div class="map-empty-message">The map tiles are unavailable. Class data is still available in the tables below.</div>';
    });
    markersLayer = L.layerGroup().addTo(map);
  }

  function relabelTimeFrame() {
    if (!reportType || !timeFrame) return;
    var selected = reportType.options[reportType.selectedIndex];
    var mode = selected ? selected.getAttribute("data-label") : "Next";
    var prefix = mode === "Past" ? "Previous" : "Next";
    Array.prototype.forEach.call(timeFrame.options, function (option) {
      if (option.value === "all") { option.textContent = "All Time"; return; }
      var unit = { "1": "1 Week", "2": "2 Weeks", "3": "3 Weeks", "4": "1 Month", "8": "2 Months", "12": "3 Months", "26": "6 Months", "52": "Year" }[option.value];
      option.textContent = prefix + " " + unit;
    });
  }

  function timeFrameDays() {
    if (!timeFrame || timeFrame.value === "all") return null;
    return { "1": 7, "2": 14, "3": 21, "4": 30, "8": 60, "12": 90, "26": 182, "52": 365 }[timeFrame.value] || 7;
  }

  function matchesFilter(point) {
    var selectedGroup = reportType ? reportType.value : "all";
    var days = timeFrameDays();
    var now = Date.now();
    var start = point.startsAt ? Date.parse(point.startsAt) : NaN;
    var end = point.endsAt ? Date.parse(point.endsAt) : start;
    var range = days === null ? null : days * 24 * 60 * 60 * 1000;

    if (selectedGroup !== "all" && point.group !== selectedGroup) return false;
    if (days === null || point.group === "ongoing") return true;
    if (point.group === "past") return Number.isFinite(end) && end >= now - range;

    return Number.isFinite(start) && start <= now + range;
  }

  function popupContent(point) {
    var popup = document.createElement("div");
    var title = document.createElement("strong");
    title.textContent = point.title;
    popup.appendChild(title);

    [
      ["Class", point.classNumber],
      ["Status", point.statusLabel],
      ["Provider", point.provider],
      ["Location", point.location],
      ["Class duration", window.wellsharpClassDurationLabel ? window.wellsharpClassDurationLabel(point) : "Duration not configured"],
      ["Schedule", [point.startsAt, point.endsAt].filter(Boolean).join(" to ")]
    ].forEach(function (row) {
      if (!row[1]) return;
      var line = document.createElement("div");
      line.textContent = row[0] + ": " + row[1];
      popup.appendChild(line);
    });

    return popup;
  }

  function renderMarkers() {
    if (!map || !markersLayer) return;
    markersLayer.clearLayers();
    var visiblePoints = classPoints.filter(function (point) { return validPoint(point) && matchesFilter(point); });
    visiblePoints.forEach(function (point) {
      L.marker([point.lat, point.lng], { icon: window.wellsharpClassMarkerIcon ? window.wellsharpClassMarkerIcon(point) : undefined }).bindPopup(popupContent(point)).addTo(markersLayer);
    });
    if (visiblePoints.length === 1) map.setView([Number(visiblePoints[0].lat), Number(visiblePoints[0].lng)], Math.max(map.getZoom(), 8));
    if (visiblePoints.length > 1) map.fitBounds(visiblePoints.map(function (point) { return [Number(point.lat), Number(point.lng)]; }), { padding: [30, 30], maxZoom: 8 });
  }

  if (!shell || !mapEl) return;
  initMap();
  if (!map) return;
  if (reportType) reportType.addEventListener("change", function () { relabelTimeFrame(); renderMarkers(); });
  if (timeFrame) timeFrame.addEventListener("change", function () { relabelTimeFrame(); renderMarkers(); });
  document.addEventListener("operational-map-filter", function (event) {
    if (event.detail?.reportType && reportType) reportType.value = event.detail.reportType;
    if (event.detail?.timeFrame && timeFrame) timeFrame.value = event.detail.timeFrame;
    relabelTimeFrame();
    renderMarkers();
  });
  relabelTimeFrame();
  renderMarkers();
  setTimeout(function () { map.invalidateSize(); }, 120);
  }

  if (window.L && window.wellsharpMapTiles) init();
  else window.addEventListener("wellsharp:maps-ready", init, { once: true });
})();
