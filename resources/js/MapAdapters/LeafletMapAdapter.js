import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-draw/dist/leaflet.draw.css';
import 'leaflet-draw';
import IMapAdapter from './IMapAdapter';

export default class LeafletMapAdapter extends IMapAdapter {
  /**
   * Add a GeoJSON feature (polygon or polyline) to the map and drawnItems
   */
  addGeoJSONFeature(feature) {
    if (!this.map || !this.drawnItems) return;
    // Ensure _dbId is in properties for persistence
    if (feature._dbId && (!feature.properties || !feature.properties._dbId)) {
      if (!feature.properties) feature.properties = {};
      feature.properties._dbId = feature._dbId;
    }
    const layer = L.GeoJSON.geometryToLayer(feature);
    // Attach the properties to the layer's feature for persistence
    layer.feature = layer.feature || {};
    layer.feature.type = feature.type;
    layer.feature.properties = feature.properties;
    layer.feature.geometry = feature.geometry;
    this.drawnItems.addLayer(layer);
    
    // Add click handler to each layer
    layer.on('click', (e) => {
      L.DomEvent.stopPropagation(e); // Prevent map click
      // Emit custom feature click event
      this.emit('featureClick', { feature: layer.feature, layer: layer, event: e });
      // Still show popup if needed
      if (layer._popup) {
        layer.openPopup(e.latlng);
      }
    });
    
    // Optionally display measurement popup
    if (feature.geometry.type === 'Polygon') {
      const latlngs = layer.getLatLngs()[0];
      const area = L.GeometryUtil.geodesicArea(latlngs);
      const coordsList = latlngs.map(latlng => `[${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}]`).join('<br>');
      
      // Get shape name following the same logic as the side panel
      const props = feature.properties || {};
      let name = feature.name || `Shape #${props.id || props._dbId}` || 'Unnamed Shape';
      
      // For debugging, log the available properties
      console.log('Popup - Feature properties:', props);
      
      // Add a default name to newly drawn features
      if (name === 'Unnamed Shape' && !props._dbId) {
        // This is a new shape without a name or ID
        if (!feature.properties) feature.properties = {};
        feature.properties.description = 'New Shape';
        name = 'New Shape';
      }
      
      layer.bindPopup(
        `<b>Name:</b> ${name}<br><b>Area:</b> ${this.formatArea(area)}<br>`
      );
    } else if (feature.geometry.type === 'LineString') {
      const latlngs = layer.getLatLngs();
      let distance = 0;
      for (let i = 1; i < latlngs.length; i++) {
        distance += latlngs[i - 1].distanceTo(latlngs[i]);
      }
      layer.bindPopup('Distance: ' + this.formatDistance(distance));
    }
  }

  // ... existing code ...

  /**
   * Get all drawn shapes as GeoJSON FeatureCollection
   */
  getAllDrawnGeoJSON() {
    if (this.drawnItems) {
      return this.drawnItems.toGeoJSON();
    }
    return null;
  }
  constructor(containerId) {
    super(containerId);
    this.containerId = containerId;
    this.map = null;
    this.drawnItems = null;
    this.drawControl = null;
    this.eventHandlers = {}; // For custom events
  }

  initMap(center = [14.5995, 120.9842], zoom = 13) {
    // Define base map layers
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    });
    
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
      maxZoom: 19
    });
    
    // Initialize map with satellite layer as default
    this.map = L.map(this.containerId, {
      layers: [satelliteLayer],
      center: center,
      zoom: zoom
    });

    // Add layer control to switch between satellite and street map
    const baseLayers = {
      "Satellite": satelliteLayer,
      "Street Map": osmLayer
    };
    L.control.layers(baseLayers, {}).addTo(this.map);
    
    // FeatureGroup to store editable layers
    this.drawnItems = new L.FeatureGroup();
    this.map.addLayer(this.drawnItems);

    // Add Leaflet.draw controls
    this.drawControl = new L.Control.Draw({
      edit: {
        featureGroup: this.drawnItems
      },
      draw: {
        polygon: true,
        polyline: true,
        rectangle: false,
        circle: false,
        marker: false,
        circlemarker: false
      }
    });
    this.map.addControl(this.drawControl);

    // Handle created layers
    this.map.on(L.Draw.Event.CREATED, (e) => {
      const layer = e.layer;
      this.drawnItems.addLayer(layer);
      // Optionally, you can display measurement here
      if (e.layerType === 'polygon') {
        const area = L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]);
        layer.bindPopup('Area: ' + this.formatArea(area)).openPopup();
      } else if (e.layerType === 'polyline') {
        const distance = this.getPolylineDistance(layer);
        layer.bindPopup('Distance: ' + this.formatDistance(distance)).openPopup();
      }
      
      // Add click handler to the newly created layer
      layer.on('click', (e) => {
        L.DomEvent.stopPropagation(e);
        this.emit('featureClick', { feature: layer.feature, layer: layer, event: e });
      });
    });

    // When editing, preserve _dbId and _fromDb, and set _modified
    this.map.on(L.Draw.Event.EDITED, (e) => {
      e.layers.eachLayer(layer => {
        if (layer.feature && layer.feature.properties && layer.feature.properties._dbId) {
          // preserve dbId and fromDb on the feature
          layer.feature._dbId = layer.feature.properties._dbId;
          layer.feature._fromDb = true;
          layer.feature._modified = true;
        }
      });
    });

    // When deleting, call onFeatureDeleted for each deleted feature
    this.map.on(L.Draw.Event.DELETED, (e) => {
      e.layers.eachLayer(layer => {
        if (this.onFeatureDeleted && layer.feature) {
          this.onFeatureDeleted(layer.feature);
        }
        // Remove the layer from drawnItems (should already be handled by Leaflet)
      });
    });
  }

  destroyMap() {
    if (this.map) {
      this.map.remove();
      this.map = null;
      this.drawnItems = null;
      this.drawControl = null;
    }
  }

  // Get current map center and zoom
  getMapState() {
    if (!this.map) return null;
    return {
      center: this.map.getCenter(),
      zoom: this.map.getZoom()
    };
  }
  
  // Set map center and zoom
  setMapState(state) {
    if (!this.map || !state) return;
    if (state.center) {
      this.map.setView(state.center, state.zoom || this.map.getZoom());
    }
  }

  // Helper to calculate polyline distance in meters
  getPolylineDistance(layer) {
    const latlngs = layer.getLatLngs();
    let distance = 0;
    for (let i = 1; i < latlngs.length; i++) {
      distance += latlngs[i - 1].distanceTo(latlngs[i]);
    }
    return distance;
  }

  // Helper to format area (m², km²)
  formatArea(area) {
    if (area >= 1000000) {
      return (area / 1000000).toFixed(2) + ' km²';
    }
    return area.toFixed(2) + ' m²';
  }

  // Helper to format distance (m, km)
  formatDistance(distance) {
    if (distance >= 1000) {
      return (distance / 1000).toFixed(2) + ' km';
    }
    return distance.toFixed(2) + ' m';
  }
  
  // Custom event handling methods
  on(event, callback) {
    if (!this.eventHandlers[event]) {
      this.eventHandlers[event] = [];
    }
    this.eventHandlers[event].push(callback);
    return this;
  }
  
  off(event, callback) {
    if (!this.eventHandlers[event]) return this;
    if (!callback) {
      delete this.eventHandlers[event];
    } else {
      this.eventHandlers[event] = this.eventHandlers[event].filter(cb => cb !== callback);
    }
    return this;
  }
  
  emit(event, data) {
    if (!this.eventHandlers[event]) return;
    this.eventHandlers[event].forEach(callback => {
      callback(data);
    });
    return this;
  }
}

