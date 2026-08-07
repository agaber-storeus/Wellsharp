(function () {
  var shell = document.querySelector(".proctor-home-workspace");
  var reportType = document.getElementById("homeReportType");
  var timeFrame = document.getElementById("homeTimeFrame");
  var mapEl = document.getElementById("homeLeafletMap");
  var map;
  var markersLayer;

  var classPoints = [
    {
      title: "supervisor 212",
      location: "LIVE - Egypt",
      lat: 30.0444,
      lng: 31.2357,
      group: "scheduled",
      weeks: 1
    },
    {
      title: "Kuwait WellSharp",
      location: "Kuwait",
      lat: 29.3759,
      lng: 47.9774,
      group: "scheduled",
      weeks: 12
    },
    {
      title: "Nigeria Well Control",
      location: "Nigeria",
      lat: 9.082,
      lng: 8.6753,
      group: "history",
      weeks: -52
    },
    {
      title: "Gulf of Guinea",
      location: "Offshore",
      lat: 1.6508,
      lng: 7.4128,
      group: "history",
      weeks: -8
    }
  ];

  function initMap() {
    if (!mapEl || !window.L) {
      return;
    }

    map = L.map(mapEl, {
      center: [12, 24],
      zoom: 2,
      minZoom: 2,
      maxZoom: 10
    });

    L.tileLayer("https://iadc.wellsharp.org/MapTiles/{z}-{x}-{y}.png", {
      attribution: ""
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);
  }

  function relabelTimeFrame() {
    var selected = reportType.options[reportType.selectedIndex];
    var mode = selected ? selected.getAttribute("data-label") : "Next";
    var prefix = "Next";

    if (mode === "Previous" || mode === "HeatmapHistory") {
      prefix = "Previous";
    } else if (mode === "Timeline") {
      prefix = "+/-";
    }

    Array.prototype.forEach.call(timeFrame.options, function (option) {
      if (option.value === "all") {
        option.textContent = "All Time";
        return;
      }

      var unit = {
        "1": "1 Week",
        "2": "2 Weeks",
        "3": "3 Weeks",
        "4": "1 Month",
        "8": "2 Months",
        "12": "3 Months",
        "26": "6 Months",
        "52": "Year"
      }[option.value];

      option.textContent = prefix + " " + unit;
    });
  }

  function pointIsVisible(point) {
    var type = reportType.value;
    var frame = timeFrame.value;

    if (type === "scheduled" || type === "heatmapFuture") {
      if (point.group !== "scheduled") return false;
      return frame === "all" || point.weeks <= Number(frame);
    }

    if (type === "history" || type === "heatmapHist") {
      if (point.group !== "history") return false;
      return frame === "all" || Math.abs(point.weeks) <= Number(frame);
    }

    return true;
  }

  function renderMarkers() {
    if (!map || !markersLayer) {
      return;
    }

    markersLayer.clearLayers();
    var visible = classPoints.filter(pointIsVisible);

    visible.forEach(function (point) {
      L.marker([point.lat, point.lng])
        .bindPopup("<strong>" + point.title + "</strong><br>" + point.location)
        .addTo(markersLayer);
    });

    if (visible.length) {
      map.setView([12, 24], 2);
    }
  }

  function updateMapState() {
    shell.dataset.timeFrame = timeFrame.value;
    shell.dataset.reportType = reportType.value;
    relabelTimeFrame();
    renderMarkers();
  }

  if (!shell || !reportType || !timeFrame) {
    return;
  }

  initMap();
  reportType.addEventListener("change", updateMapState);
  timeFrame.addEventListener("change", updateMapState);
  updateMapState();
})();
