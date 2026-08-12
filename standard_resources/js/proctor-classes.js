(function () {
  var mapEl = document.getElementById("classesLeafletMap");
  var fallback = document.querySelector(".classes-map-fallback");
  var filterToggle = document.getElementById("calendarFilterToggle");
  var filterClose = document.getElementById("calendarFilterClose");
  var filterBody = document.querySelector(".calendar-filter-body");

  function initCalendarFilter() {
    if (!filterBody || !filterToggle || !filterClose) {
      return;
    }

    filterToggle.addEventListener("click", function () {
      filterBody.classList.add("is-expanded");
    });

    filterClose.addEventListener("click", function () {
      filterBody.classList.remove("is-expanded");
    });
  }

  initCalendarFilter();

  if (!mapEl || !window.L) {
    if (fallback) fallback.classList.add("is-visible");
    return;
  }

  var map = L.map(mapEl, {
    center: [24, 29],
    zoom: 3,
    minZoom: 1,
    maxZoom: 10
  });

  var tiles = L.tileLayer("https://iadc.wellsharp.org/MapTiles/{z}-{x}-{y}.png", {
    attribution: ""
  }).addTo(map);

  if (fallback) fallback.classList.remove("is-visible");

  tiles.on("tileerror", function () {
    if (fallback) fallback.classList.add("is-visible");
  });

  [
    ["Egypt", 30.0444, 31.2357],
    ["Kuwait", 29.3759, 47.9774],
    ["Nigeria", 9.082, 8.6753]
  ].forEach(function (point) {
    L.marker([point[1], point[2]]).bindPopup(point[0]).addTo(map);
  });

  setTimeout(function () {
    map.invalidateSize();
  }, 120);
})();
