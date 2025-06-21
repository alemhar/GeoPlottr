<template>
  <div id="map" style="height: 100vh; width: 100vw;"></div>
  <Toaster position="top-right" richColors />
  <button 
    @click="saveMeasurements" 
    :disabled="saving" 
    style="position: absolute; top: 16px; right: 16px; z-index: 1000; background: #fff; border: 1px solid #ccc; padding: 8px 16px; border-radius: 4px;"
  >
    {{ saving ? 'Saving...' : 'Save Measurements' }}
  </button>
</template>

<script>
import LeafletMapAdapter from '../MapAdapters/LeafletMapAdapter';
import { Inertia } from '@inertiajs/inertia';
import { toast, Toaster } from 'vue-sonner';

export default {
  name: 'MapComponent',
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
    };
  },
  mounted() {
    // Use the Leaflet adapter for now
    this.mapAdapter = new LeafletMapAdapter('map');
    this.mapAdapter.initMap([14.5995, 120.9842], 13);
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
    saveMeasurements() {
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
        // Confirm before save using standard confirm dialog
        let confirmMessage = 'Save changes to the backend?';
        if (!geojson || geojson.features.length === 0) {
          confirmMessage = 'No measurements found. This will erase all saved measurements. Continue?';
        }
        
        if (confirm(confirmMessage)) {
          this.saving = true;
          
          // Use Inertia to post to backend
          Inertia.post('/measurements', {
            new: newFeatures,
            modified: modifiedFeatures,
            deleted: this.deletedFeatureIds,
          }, {
            onSuccess: () => {
              toast.success('Measurements saved successfully!');
              this.deletedFeatureIds = [];
              this.saving = false;
            },
            onError: (errors) => {
              toast.error('Failed to save measurements');
              console.error(errors);
              this.saving = false;
            },
          });
        }
      }
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
