(function () {
  window.providerLocationForm = function (config) {
    return {
      locations: config.locations || [],
      autosaveUrl: config.autosaveUrl,
      selectedLocationKey: config.locations[0]?.clientKey || null,
      autosaveError: "",
      _dirtyKeys: [],
      _saveQueued: false,
      _saveRunning: false,
      _savePromise: Promise.resolve(true),
      _submitReleased: false,

      init: function () {
        var component = this;
        this.$nextTick(function () {
          var form = component.$root.closest("form");
          if (!form) return;
          form.addEventListener("submit", function (event) {
            if (component._submitReleased) return;
            event.preventDefault();
            component.flushAutosave().finally(function () {
              component._submitReleased = true;
              HTMLFormElement.prototype.submit.call(form);
            });
          });
        });
      },

      addLocation: function () {
        var clientKey = "new-" + crypto.randomUUID();
        this.locations.push({ id: null, clientKey: clientKey, location: "", latitude: null, longitude: null, autosaveStatus: "saving" });
        this.selectedLocationKey = clientKey;
        this.$nextTick(function () {
          window.dispatchEvent(new CustomEvent("provider-locations-changed", { detail: { selectedKey: clientKey } }));
        });
        this.autosaveLocation(clientKey, true);
      },

      removeLocation: function (index) {
        var removedKey = this.locations[index].clientKey;
        this.locations.splice(index, 1);
        this._dirtyKeys = this._dirtyKeys.filter(function (key) { return key !== removedKey; });
        if (this.selectedLocationKey === removedKey) this.selectedLocationKey = this.locations[0]?.clientKey || null;
        this.locations.forEach(function (location) { location.autosaveStatus = "saving"; });
        this.$nextTick(() => {
          window.dispatchEvent(new CustomEvent("provider-locations-changed", { detail: { selectedKey: this.selectedLocationKey } }));
        });
        this.queueAutosave(true);
      },

      selectLocation: function (key) {
        var previousKey = this.selectedLocationKey;
        this.selectedLocationKey = key;
        if (previousKey) this.markDirty(previousKey);
        if (key) this.markDirty(key);
        this.queueAutosave(true);
      },

      autosaveLocation: function (key, immediate) {
        this.markDirty(key);
        this.queueAutosave(immediate === true);
      },

      markDirty: function (key) {
        var location = this.locations.find(function (item) { return item.clientKey === key; });
        if (!location) return;
        if (!this._dirtyKeys.includes(key)) this._dirtyKeys.push(key);
        location.autosaveStatus = "saving";
      },

      queueAutosave: function () {
        this._saveQueued = true;
        return this.runAutosaveQueue();
      },

      flushAutosave: function () {
        this.locations.forEach((location) => this.markDirty(location.clientKey));
        this._saveQueued = true;
        return this.runAutosaveQueue();
      },

      runAutosaveQueue: function () {
        if (this._saveRunning) return this._savePromise;

        var component = this;
        this._saveRunning = true;
        this._savePromise = (async function () {
          var succeeded = true;
          while (component._saveQueued) {
            component._saveQueued = false;
            var savingKeys = component._dirtyKeys.slice();
            component._dirtyKeys = [];

            try {
              var response = await fetch(component.autosaveUrl, {
                method: "PUT",
                credentials: "same-origin",
                headers: {
                  Accept: "application/json",
                  "Content-Type": "application/json",
                  "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ locations: component.autosavePayload() }),
              });
              var result = await response.json().catch(function () { return {}; });
              if (!response.ok) throw new Error(result.message || "Locations could not be autosaved.");

              (result.locations || []).forEach(function (saved) {
                var location = component.locations.find(function (item) { return item.clientKey === saved.client_key; });
                if (location && saved.id) location.id = saved.id;
              });
              savingKeys.forEach(function (key) {
                var location = component.locations.find(function (item) { return item.clientKey === key; });
                if (location && !component._dirtyKeys.includes(key)) location.autosaveStatus = "saved";
              });
              if (savingKeys.length === 0) component.locations.forEach(function (location) { location.autosaveStatus = "saved"; });
              component.autosaveError = "";
            } catch (error) {
              succeeded = false;
              savingKeys.forEach(function (key) {
                var location = component.locations.find(function (item) { return item.clientKey === key; });
                if (location) location.autosaveStatus = "error";
              });
              if (savingKeys.length === 0) component.locations.forEach(function (location) { location.autosaveStatus = "error"; });
              component.autosaveError = error.message;
            }

            if (component._dirtyKeys.length > 0) component._saveQueued = true;
          }
          return succeeded;
        })().finally(function () {
          component._saveRunning = false;
          if (component._saveQueued) component.runAutosaveQueue();
        });

        return this._savePromise;
      },

      autosavePayload: function () {
        return this.locations.map(function (location) {
          return {
            client_key: location.clientKey,
            id: location.id || null,
            location: location.location || "",
            latitude: location.latitude === "" ? null : location.latitude,
            longitude: location.longitude === "" ? null : location.longitude,
          };
        });
      },
    };
  };

  function init() {
    var mapEl = document.getElementById("providerLocationMap");
    var message = document.getElementById("providerLocationMessage");

    if (!mapEl || !window.L || !window.wellsharpMapTiles) {
      if (message) message.textContent = "Map picker is unavailable. Locations can still be saved without map pins.";
      return;
    }

    var defaultCenter = [20, 0];
    var map = L.map(mapEl, { center: defaultCenter, zoom: 2, minZoom: 2, maxZoom: 18 });
    var markers = new Map();
    var selectedKey = null;

    window.wellsharpMapTiles(map, function () {
      if (message) message.textContent = "Map tiles are unavailable. Provider locations can still be saved.";
    });

    function rows() {
      return Array.from(document.querySelectorAll(".provider-location-row[data-location-key]"));
    }

    function field(key, name) {
      return document.querySelector('[data-location-key="' + CSS.escape(key) + '"][data-location-field="' + name + '"]');
    }

    function coordinates(key) {
      var latitude = parseFloat(field(key, "latitude")?.value);
      var longitude = parseFloat(field(key, "longitude")?.value);
      return Number.isFinite(latitude) && Number.isFinite(longitude) ? [latitude, longitude] : null;
    }

    function setFieldValue(input, value) {
      input.value = value;
      input.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function setCoordinates(key, latitude, longitude) {
      var latitudeInput = field(key, "latitude");
      var longitudeInput = field(key, "longitude");
      if (!latitudeInput || !longitudeInput) return;
      setFieldValue(latitudeInput, Number(latitude).toFixed(7));
      setFieldValue(longitudeInput, Number(longitude).toFixed(7));
      window.dispatchEvent(new CustomEvent("provider-location-coordinate-changed", { detail: { key: key } }));
      syncMarkers();
      selectLocation(key, true);
      if (message) message.textContent = "Map location selected. Drag the pin to adjust it.";
    }

    function clearCoordinates(key) {
      var latitudeInput = field(key, "latitude");
      var longitudeInput = field(key, "longitude");
      if (!latitudeInput || !longitudeInput) return;
      setFieldValue(latitudeInput, "");
      setFieldValue(longitudeInput, "");
      window.dispatchEvent(new CustomEvent("provider-location-coordinate-changed", { detail: { key: key } }));
      syncMarkers();
      selectLocation(key, false);
      if (message) message.textContent = "Map location cleared for the selected location.";
    }

    function updateSelection() {
      rows().forEach(function (row) {
        row.classList.toggle("is-map-selected", row.dataset.locationKey === selectedKey);
      });
      markers.forEach(function (marker, key) {
        var selected = key === selectedKey;
        marker.setOpacity(selected ? 1 : 0.72);
        marker.setZIndexOffset(selected ? 1000 : 0);
        if (marker._icon) marker._icon.classList.toggle("is-map-selected", selected);
      });
    }

    function selectLocation(key, focusMarker) {
      if (!key || !rows().some(function (row) { return row.dataset.locationKey === key; })) return;
      selectedKey = key;
      window.dispatchEvent(new CustomEvent("provider-location-selected", { detail: { key: key } }));
      updateSelection();
      var marker = markers.get(key);
      if (focusMarker && marker) map.setView(marker.getLatLng(), Math.max(map.getZoom(), 12));
      if (message) {
        var name = field(key, "name")?.value.trim();
        message.textContent = (name || "Selected location") + (marker ? " is selected on the map." : " is selected. Click the map to place its pin.");
      }
    }

    function createMarker(key, latLng) {
      var marker = L.marker(latLng, { draggable: true }).addTo(map);
      marker.on("click", function () { selectLocation(key, false); });
      marker.on("dragend", function (event) {
        var position = event.target.getLatLng();
        setCoordinates(key, position.lat, position.lng);
      });
      markers.set(key, marker);
      return marker;
    }

    function syncMarkers() {
      var currentKeys = new Set();
      rows().forEach(function (row) {
        var key = row.dataset.locationKey;
        var latLng = coordinates(key);
        currentKeys.add(key);
        if (latLng) {
          var marker = markers.get(key) || createMarker(key, latLng);
          marker.setLatLng(latLng);
        } else if (markers.has(key)) {
          markers.get(key).remove();
          markers.delete(key);
        }
      });
      markers.forEach(function (marker, key) {
        if (!currentKeys.has(key)) {
          marker.remove();
          markers.delete(key);
        }
      });
      updateSelection();
    }

    function fitMarkers() {
      var markerList = Array.from(markers.values());
      if (markerList.length > 1) map.fitBounds(L.latLngBounds(markerList.map(function (marker) { return marker.getLatLng(); })), { padding: [28, 28] });
      else if (markerList.length === 1) map.setView(markerList[0].getLatLng(), 12);
      else map.setView(defaultCenter, 2);
    }

    map.on("click", function (event) {
      if (selectedKey) setCoordinates(selectedKey, event.latlng.lat, event.latlng.lng);
      else if (message) message.textContent = "Select a location before placing a map pin.";
    });

    document.addEventListener("click", function (event) {
      var row = event.target.closest(".provider-location-row[data-location-key]");
      if (row) selectLocation(row.dataset.locationKey, true);
    });
    window.addEventListener("provider-location-selected", function (event) {
      if (selectedKey !== event.detail.key) selectLocation(event.detail.key, true);
    });
    window.addEventListener("provider-location-clear", function (event) { clearCoordinates(event.detail.key); });
    window.addEventListener("provider-locations-changed", function (event) {
      syncMarkers();
      selectedKey = event.detail.selectedKey || rows()[0]?.dataset.locationKey || null;
      selectLocation(selectedKey, false);
      fitMarkers();
    });

    setTimeout(function () {
      syncMarkers();
      selectedKey = rows()[0]?.dataset.locationKey || null;
      selectLocation(selectedKey, false);
      fitMarkers();
      map.invalidateSize();
    }, 120);
  }

  if (window.L && window.wellsharpMapTiles) init();
  else window.addEventListener("wellsharp:maps-ready", init, { once: true });
})();
