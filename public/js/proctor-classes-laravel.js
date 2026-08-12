(function () {
  function init() {
  var mapEl = document.getElementById("classesLeafletMap");
  var fallback = document.querySelector(".classes-map-fallback");
  var filterToggle = document.getElementById("calendarFilterToggle");
  var filterClose = document.getElementById("calendarFilterClose");
  var filterBody = document.querySelector(".calendar-filter-body");
  var reportType = document.getElementById("classesReportType");
  var timeFrame = document.getElementById("classesTimeFrame");
  var points = window.wellsharpClassPoints || [];
  var markersLayer;

  function validPoint(point) {
    var lat = Number(point.lat);
    var lng = Number(point.lng);

    return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
  }

  function timeFrameDays() {
    if (!timeFrame || timeFrame.value === "all") return null;
    return { "1": 30, "3": 90, "6": 180, "12": 365 }[timeFrame.value] || 30;
  }

  function matchesFilter(point) {
    var selectedGroup = reportType ? reportType.value : "all";
    var days = timeFrameDays();
    var now = Date.now();
    var start = point.startsAt ? Date.parse(point.startsAt) : NaN;
    var end = point.endsAt ? Date.parse(point.endsAt) : start;
    var range = days === null ? null : days * 24 * 60 * 60 * 1000;

    if (selectedGroup === "scheduled" && point.group !== "ongoing" && point.group !== "upcoming") return false;
    if (selectedGroup !== "all" && selectedGroup !== "scheduled" && point.group !== selectedGroup) return false;
    if (days === null || point.group === "ongoing") return true;
    if (point.group === "past") return Number.isFinite(end) && end >= now - range;

    return Number.isFinite(start) && start <= now + range;
  }

  if (filterBody && filterToggle && filterClose) {
    filterToggle.addEventListener("click", function () { filterBody.classList.add("is-expanded"); });
    filterClose.addEventListener("click", function () { filterBody.classList.remove("is-expanded"); });
  }

  if (!mapEl || !window.L) {
    if (fallback) fallback.classList.add("is-visible");
    return;
  }

  var map = L.map(mapEl, { center: [24, 29], zoom: 3, minZoom: 1, maxZoom: 10 });
  window.wellsharpMapTiles(map, function () { if (fallback) fallback.classList.add("is-visible"); });
  markersLayer = L.layerGroup().addTo(map);
  if (fallback) fallback.classList.remove("is-visible");

  function renderMarkers() {
    markersLayer.clearLayers();
    var visiblePoints = points.filter(function (point) { return validPoint(point) && matchesFilter(point); });
    visiblePoints.forEach(function (point) {
      var popup = document.createElement("div");
      var title = document.createElement("strong");
      title.textContent = point.title;
      popup.appendChild(title);
      var location = document.createElement("div");
      location.textContent = "Location: " + point.location;
      popup.appendChild(location);
      var duration = document.createElement("div");
      duration.textContent = "Class duration: " + (window.wellsharpClassDurationLabel ? window.wellsharpClassDurationLabel(point) : "Duration not configured");
      popup.appendChild(duration);
      L.marker([point.lat, point.lng], { icon: window.wellsharpClassMarkerIcon ? window.wellsharpClassMarkerIcon(point) : undefined }).bindPopup(popup).addTo(markersLayer);
    });
    if (visiblePoints.length === 1) map.setView([Number(visiblePoints[0].lat), Number(visiblePoints[0].lng)], Math.max(map.getZoom(), 8));
    if (visiblePoints.length > 1) map.fitBounds(visiblePoints.map(function (point) { return [Number(point.lat), Number(point.lng)]; }), { padding: [30, 30], maxZoom: 8 });
  }

  if (reportType) reportType.addEventListener("change", renderMarkers);
  if (timeFrame) timeFrame.addEventListener("change", renderMarkers);
  renderMarkers();
  setTimeout(function () { map.invalidateSize(); }, 120);
  }

  if (window.L && window.wellsharpMapTiles) init();
  else window.addEventListener("wellsharp:maps-ready", init, { once: true });
})();
