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

export default {
  name: 'MapComponent',
  components: {
    ConfirmDialog,
    Toaster
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
      }
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
              // Remove any id property if present
              const clean = { geojson: { ...f } };
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
