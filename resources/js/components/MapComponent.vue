<template>
  <div id="map" style="height: 100vh; width: 100vw;"></div>
  <Toaster position="top-right" richColors />
  <button 
    @click="showConfirmDialog" 
    :disabled="saving" 
    style="position: absolute; top: 16px; right: 16px; z-index: 1000; background: #fff; border: 1px solid #ccc; padding: 8px 16px; border-radius: 4px;"
  >
    {{ saving ? 'Saving...' : 'Save Session' }}
  </button>
  
  <!-- Reusable side panel for feature details -->
  <SidePanel v-model="sidePanelOpen" :title="selectedFeatureTitle">
    <!-- For shapes with database ID, use ShapeForm component -->
    <ShapeForm v-if="selectedFeature && selectedFeature.properties && selectedFeature.properties._dbId" 
              :shapeId="selectedFeature.properties._dbId" />
    
    <!-- For newly created shapes without ID, show basic info -->
    <div v-else-if="selectedFeature" class="feature-details">
      <div class="info-message">
        <p>This shape has not been saved to the database yet.</p>
        <p>Save your session to create a permanent record.</p>
      </div>
      
      <div v-if="selectedFeature.geometry" class="geometry-info">
        <h4>Shape Information</h4>
        <p><strong>Type:</strong> {{ selectedFeature.geometry.type }}</p>
        
        <!-- Show calculated area for polygons -->
        <p v-if="selectedFeature.geometry.type === 'Polygon'">
          <strong>Estimated Area:</strong> 
          {{ formatShapeArea(selectedFeature) }}
        </p>
        
        <!-- Show calculated length for lines -->
        <p v-else-if="selectedFeature.geometry.type === 'LineString'">
          <strong>Estimated Length:</strong>
          {{ formatShapeLength(selectedFeature) }}
        </p>
      </div>
    </div>
  </SidePanel>
  
  <!-- Alert Dialog for confirmation -->
  <ConfirmDialog
    v-model="confirmDialogOpen"
    :title="'Save Changes'"
    :message="confirmMessage"
    :loading="saving"
    @confirm="saveConfirmed"
    @cancel="cancelSave"
  />
</template>

<script>
import LeafletMapAdapter from '../MapAdapters/LeafletMapAdapter';
import { Inertia } from '@inertiajs/inertia';
import { toast, Toaster } from 'vue-sonner';
import ConfirmDialog from './ConfirmDialog.vue';
import SidePanel from './SidePanel.vue';
import ShapeForm from './ShapeForm.vue';
import L from 'leaflet';
import 'leaflet-geometryutil';

export default {
  name: 'MapComponent',
  components: {
    ConfirmDialog,
    Toaster,
    SidePanel,
    ShapeForm
  },
  props: {
    measurements: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      mapAdapter: null,
      dbFeatures: [], // Track features loaded from DB (with id)
      deletedFeatureIds: [], // Track ids of deleted features,
      saving: false, // Track if save in progress
      confirmDialogOpen: false, // Control the confirm dialog visibility
      confirmMessage: 'Save changes to the backend?', // Message for the confirmation dialog
      preparedFeatures: {
        new: [],
        modified: [],
        deleted: []
      },
      mapState: { // Track map position to restore after save
        center: null,
        zoom: null
      },
      // Side panel state
      sidePanelOpen: false,
      selectedFeature: null,
      selectedFeatureTitle: 'Shape Details'
    };
  },
  mounted() {
    // Use the Leaflet adapter for now
    this.mapAdapter = new LeafletMapAdapter('map');
    
    // Try to restore map position from localStorage
    let center = [14.5995, 120.9842];
    let zoom = 13;
    
    try {
      const savedState = localStorage.getItem('mapState');
      if (savedState) {
        const parsedState = JSON.parse(savedState);
        if (parsedState.lat && parsedState.lng) {
          center = [parsedState.lat, parsedState.lng];
          zoom = parsedState.zoom || zoom;
        }
      }
    } catch (e) {
      console.warn('Failed to restore map state', e);
    }
    
    // Initialize map with the restored or default position
    this.mapAdapter.initMap(center, zoom);
    // Aggregate all features from all measurements
    let allFeatures = [];
    if (this.measurements && this.measurements.length > 0) {
      this.measurements.forEach(m => {
        console.log(m.name);
        if (m.geojson && m.geojson.type === 'FeatureCollection' && Array.isArray(m.geojson.features)) {
          m.geojson.features.forEach(f => {
            f._dbId = m.id;
            f._fromDb = true;
            f._modified = false;
            // Attach id to properties for persistence through toGeoJSON
            if (!f.properties) f.properties = {};
            f.properties._dbId = m.id;
            allFeatures.push(f);
          });
        } else if (m.geojson && m.geojson.type === 'Feature') {
          // Single feature
          const f = m.geojson;
          f._dbId = m.id;
          f._fromDb = true;
          f._modified = false;
          if (!f.properties) f.properties = {};
          f.properties._dbId = m.id;
          allFeatures.push(f);
        }
      });
    }
    allFeatures.forEach(f => {
      this.mapAdapter.addGeoJSONFeature(f);
      this.dbFeatures.push(f);
    });
    // Listen for edits/deletes
    this.mapAdapter.on('editstart', () => {
      console.log('Edit started');
    });

    this.mapAdapter.on('editstop', () => {
      console.log('Edit stopped');
    });
    
    // Add click handler for features to open side panel
    this.mapAdapter.on('featureClick', (e) => {
      this.openSidePanel(e.feature, e.layer);
    });

    this.mapAdapter.onFeatureEdited = (feature) => {
      feature._modified = true;
    };

    this.mapAdapter.onFeatureDeleted = (feature) => {
      // Check for ID in both feature directly and in properties (GeoJSON)
      let dbId = null;
      if (feature._fromDb && feature._dbId) {
        dbId = feature._dbId;
      } else if (feature.properties && feature.properties._dbId) {
        dbId = feature.properties._dbId;
      }
      
      if (dbId) {
        this.deletedFeatureIds.push(dbId);
      }
    };
  },
  beforeDestroy() {
    if (this.mapAdapter) {
      this.mapAdapter.destroyMap();
      this.mapAdapter = null;
    }
  },
  methods: {
    // Calculate perimeter for a polygon (array of LatLng objects)
    calculatePerimeter(coordinates) {
      let perimeter = 0;
      
      if (coordinates && coordinates.length > 1) {
        for (let i = 0; i < coordinates.length - 1; i++) {
          const p1 = L.latLng(coordinates[i].lat, coordinates[i].lng);
          const p2 = L.latLng(coordinates[i+1].lat, coordinates[i+1].lng);
          perimeter += p1.distanceTo(p2);
        }
        
        // Add distance from last point back to first point to close the polygon
        if (coordinates.length > 2) {
          const first = L.latLng(coordinates[0].lat, coordinates[0].lng);
          const last = L.latLng(coordinates[coordinates.length-1].lat, coordinates[coordinates.length-1].lng);
          perimeter += last.distanceTo(first);
        }
      }
      
      return perimeter;
    },
    
    // Format area to user-friendly string
    formatArea(area) {
      if (!area) return '0 m²';
      
      if (area < 10000) {
        return Math.round(area) + ' m²';
      } else {
        return Math.round(area / 10000) / 100 + ' ha';
      }
    },
    
    // Format perimeter/distance to user-friendly string
    formatLength(length) {
      if (!length) return '0 m';
      
      if (length < 1000) {
        return Math.round(length) + ' m';
      } else {
        return Math.round(length / 100) / 10 + ' km';
      }
    },
    
    // Fallback method to calculate polygon area when GeometryUtil is not available
    calculateFallbackArea(coordinates) {
      // Simple shoelace formula for planar polygon area
      // Note: This will be less accurate than geodesic calculations for large areas
      let area = 0;
      const n = coordinates.length;
      
      if (n < 3) return 0; // Not enough points for an area
      
      for (let i = 0; i < n; i++) {
        const j = (i + 1) % n;
        area += coordinates[i].lng * coordinates[j].lat;
        area -= coordinates[j].lng * coordinates[i].lat;
      }
      
      // Take absolute value and multiply by scaling factor
      // This is a rough conversion factor for mid-latitudes
      // For more accurate results, proper geodesic calculations should be used
      const scaleFactor = 85000; // Approximate factor for conversion at mid-latitudes
      return Math.abs(area) * scaleFactor / 2;
    },
    
    // Prepare data and show the confirmation dialog
    showConfirmDialog() {
      console.log('showConfirmDialog');
      // Debug the dialog state
      console.log('Before:', this.confirmDialogOpen);
      if (this.mapAdapter) {
        const geojson = this.mapAdapter.getAllDrawnGeoJSON();
        // Prepare new, modified, and deleted features
        const newFeatures = [];
        const modifiedFeatures = [];
        
        if (geojson && geojson.features) {
          geojson.features.forEach(f => {
            // Extract dbId ONLY from properties._dbId (toGeoJSON loses root-level _dbId)
            const dbId = f.properties && f.properties._dbId ? f.properties._dbId : undefined;
            // If not from DB, treat as new
            if (!dbId) {
              // Calculate area and perimeter for polygon features
              let area = null;
              let perimeter = null;
              
              if (f.geometry && f.geometry.type === 'Polygon') {
                // Convert GeoJSON coordinates to LatLng array for Leaflet calculation
                const coordinates = f.geometry.coordinates[0].map(coord => {
                  return { lat: coord[1], lng: coord[0] };
                });
                
                try {
                  // Try to calculate area using Leaflet's GeometryUtil
                  if (L.GeometryUtil && typeof L.GeometryUtil.geodesicArea === 'function') {
                    area = L.GeometryUtil.geodesicArea(coordinates);
                  } else {
                    // Fallback to simpler calculation
                    console.warn('GeometryUtil not available, using simpler area calculation');
                    area = this.calculateFallbackArea(coordinates);
                  }
                  
                  // Calculate perimeter by summing distances between consecutive points
                  perimeter = this.calculatePerimeter(coordinates);
                  
                  console.log('Calculated measurements:', { area, perimeter });
                } catch (error) {
                  console.error('Error calculating area/perimeter:', error);
                  // Fallback values
                  area = 1000; // default fallback
                  perimeter = 150; // default fallback
                }
              }
              
              // Remove any id property if present
              const clean = { 
                geojson: { ...f },
                area: area,
                perimeter: perimeter
              };
              
              if (clean.geojson.properties) delete clean.geojson.properties._dbId;
              delete clean.geojson._dbId;
              delete clean.geojson._fromDb;
              delete clean.geojson._modified;
              newFeatures.push(clean);
            } else {
              // For modified, ensure id is included and clean flags
              if (f._modified) {
                const clean = { id: dbId, geojson: { ...f } };
                if (clean.geojson.properties) delete clean.geojson.properties._dbId;
                delete clean.geojson._dbId;
                delete clean.geojson._fromDb;
                delete clean.geojson._modified;
                modifiedFeatures.push(clean);
              }
            }
          });
        }
        
        // Store prepared data for use in saveConfirmed
        this.preparedFeatures = {
          new: newFeatures,
          modified: modifiedFeatures,
          deleted: this.deletedFeatureIds
        };
        
        // Save current map position to memory and localStorage
        this.mapState = this.mapAdapter.getMapState();
        if (this.mapState) {
          localStorage.setItem('mapState', JSON.stringify({
            lat: this.mapState.center.lat,
            lng: this.mapState.center.lng,
            zoom: this.mapState.zoom
          }));
        }
        
        // Set confirmation message based on content
        this.confirmMessage = (!geojson || geojson.features.length === 0) 
          ? 'No measurements found. This will erase all saved measurements. Continue?' 
          : 'Save current session to the database?';
        
        // Open the confirmation dialog
        this.confirmDialogOpen = true;
        console.log('After setting:', this.confirmDialogOpen);
        
        // Force a re-render if needed by setting it again in the next tick
        setTimeout(() => {
          this.confirmDialogOpen = true;
          console.log('After timeout:', this.confirmDialogOpen);
        }, 0);
      }
    },
    
    // Cancel save operation
    cancelSave() {
      toast('Save operation cancelled');
      this.confirmDialogOpen = false;
    },
    
    // Confirmed, proceed with save
    selectLayerById(id) {
      // Implementation of selecting a specific layer by id
    },
    
    // Helper methods for displaying measurement info
    formatShapeArea(feature) {
      // Calculate area for polygons if not already calculated
      if (!feature.properties || !feature.properties.area) {
        // Basic calculation - this could be enhanced with proper geodesic area calculation
        const area = this.estimatePolygonArea(feature.geometry.coordinates);
        return this.formatArea(area);
      }
      return this.formatArea(feature.properties.area);
    },
    
    formatShapeLength(feature) {
      // Calculate length for lines if not already calculated
      if (!feature.properties || !feature.properties.length) {
        // Basic calculation - this could be enhanced with proper geodesic distance calculation
        const length = this.estimateLineLength(feature.geometry.coordinates);
        return this.formatDistance(length);
      }
      return this.formatDistance(feature.properties.length);
    },
    
    estimatePolygonArea(coordinates) {
      // Very basic area calculation for demo purposes
      // In a real app, use a proper geodesic library like turf.js
      // This is just a placeholder that returns a reasonable value
      return 10000; // 10000 square meters as placeholder
    },
    
    estimateLineLength(coordinates) {
      // Basic length calculation for demo purposes
      // In a real app, use a proper geodesic library
      return 500; // 500 meters as placeholder
    },
    
    formatArea(area) {
      if (!area) return 'N/A';
      if (area >= 1000000) {
        return `${(area / 1000000).toFixed(2)} km²`;
      }
      return `${area.toFixed(2)} m²`;
    },
    
    formatDistance(distance) {
      if (!distance) return 'N/A';
      if (distance >= 1000) {
        return `${(distance / 1000).toFixed(2)} km`;
      }
      return `${distance.toFixed(2)} m`;
    },
    
    // Side panel methods
    openSidePanel(feature, layer) {
      this.selectedFeature = feature;
      
      // Set the title based on feature properties
      let title = 'Shape Details';
      if (feature.properties) {
        if (feature.properties.name) {
          title = feature.properties.name;
        } else if (feature.properties.id) {
          title = `Shape #${feature.properties.id}`;
        }
      }
      this.selectedFeatureTitle = title;
      
      // Open the panel
      this.sidePanelOpen = true;
    },
    
    // Confirmed, proceed with save
    saveConfirmed() {
      this.saving = true;
      
      // Use Inertia to post to backend
      Inertia.post('/measurements', {
        new: this.preparedFeatures.new,
        modified: this.preparedFeatures.modified,
        deleted: this.preparedFeatures.deleted,
      }, {
        onSuccess: (page) => {
          // Check for success message in flash data
          if (page.props.flash && page.props.flash.success) {
            const stats = page.props.flash.stats || {};
            const total = (stats.updated || 0) + (stats.deleted || 0) + (stats.inserted || 0);
            toast.success(`Measurements saved successfully! (${total} changes)`);
            
            // Reset tracking arrays
            this.deletedFeatureIds = [];
          } else {
            toast.success('Measurements saved successfully!');
          }
          
          // Restore map position if we saved it
          if (this.mapState && this.mapAdapter) {
            this.mapAdapter.setMapState(this.mapState);
          }
          
          this.saving = false;
          this.confirmDialogOpen = false;
        },
        onError: (errors) => {
          if (errors.response && errors.response.status === 409) {
            toast.error('Conflict detected: Another user may have modified these measurements');
            console.error('Conflict error:', errors.response?.data?.message || 'Unknown conflict');
          } else {
            toast.error('Failed to save measurements');
            console.error('Save error:', errors);
          }
          this.saving = false;
          this.confirmDialogOpen = false;
        },
      });
    },
  },
};
</script>


<style scoped>
#map {
  min-height: 100vh;
  min-width: 100vw;
}
</style>
