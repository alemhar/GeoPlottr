<template>
  <div class="shape-form">
    <div v-if="loading" class="loading-state">
      Loading shape details...
    </div>
    
    <div v-else-if="error" class="error-state">
      Error loading shape: {{ error }}
    </div>
    
    <form v-else @submit.prevent="saveChanges" class="shape-details-form">
      <!-- Basic Information -->
      <div class="form-section">
        <h4>Basic Information</h4>
        <div class="form-group">
          <label for="name">Name</label>
          <input type="text" id="name" v-model="form.name" class="form-control" />
        </div>
        
        <div class="form-group">
          <label for="description">Description</label>
          <input type="text" id="description" v-model="form.description" class="form-control" placeholder="Describe this shape's purpose or details" />
        </div>
        
        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" v-model="form.category" class="form-control">
            <option value="">Select a category</option>
            <option value="Residential">Residential</option>
            <option value="Commercial">Commercial</option>
            <option value="Agricultural">Agricultural</option>
            <option value="Industrial">Industrial</option>
            <option value="Recreational">Recreational</option>
            <option value="Other">Other</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="tags">Tags</label>
          <input type="text" id="tags" v-model="form.tags" class="form-control" 
                placeholder="Comma-separated tags" />
        </div>
      </div>
      
      <!-- Property Information -->
      <div class="form-section">
        <h4>Property Information</h4>
        <div class="form-group">
          <label for="parcel_number">Parcel Number</label>
          <input type="text" id="parcel_number" v-model="form.parcel_number" class="form-control" />
        </div>
        
        <div class="form-group">
          <label>Measurements</label>
          <div class="measurement-input-fields">
            <div class="form-group">
              <label for="area">Area (m²)</label>
              <div class="input-with-display">
                <input type="number" id="area" v-model.number="form.area" class="form-control" step="0.01" />
                <span class="input-display">{{ formatArea(form.area) }}</span>
              </div>
            </div>
            <div class="form-group">
              <label for="perimeter">Perimeter (m)</label>
              <div class="input-with-display">
                <input type="number" id="perimeter" v-model.number="form.perimeter" class="form-control" step="0.01" />
                <span class="input-display">{{ formatLength(form.perimeter) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Additional Information -->
      <div class="form-section">
        <h4>Additional Information</h4>
        <div class="form-group">
          <label for="zoning">Zoning</label>
          <input type="text" id="zoning" v-model="form.zoning" class="form-control" />
        </div>
        
        <div class="form-group">
          <label for="estimated_value">Estimated Value</label>
          <input type="number" id="estimated_value" v-model.number="form.estimated_value" 
                class="form-control" step="0.01" />
        </div>
        
        <div class="form-group">
          <label for="slope">Slope (%)</label>
          <input type="number" id="slope" v-model.number="form.slope" 
                class="form-control" step="0.01" />
        </div>
      </div>
      
      <!-- Access Control -->
      <div class="form-section">
        <h4>Sharing</h4>
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" v-model="form.is_shared" />
            Share this shape with others
          </label>
        </div>
        
        <div class="form-group" v-if="form.is_shared">
          <label for="access_level">Access Level</label>
          <select id="access_level" v-model="form.access_level" class="form-control">
            <option value="0">Private</option>
            <option value="1">Team</option>
            <option value="2">Public</option>
          </select>
        </div>
      </div>
      
      <!-- Form Actions -->
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import axios from 'axios';
import { toast } from 'vue-sonner';

export default {
  name: 'ShapeForm',
  props: {
    shapeId: {
      type: [Number, String],
      required: true
    }
  },
  data() {
    return {
      form: {
        name: '',
        description: '',
        category: '',
        tags: '',
        parcel_number: '',
        area: null,
        perimeter: null,
        zoning: '',
        estimated_value: null,
        slope: null,
        is_shared: false,
        access_level: 0,
        // We don't modify geojson here as it's handled by the map
      },
      originalForm: {},
      loading: true,
      saving: false,
      error: null
    };
  },
  watch: {
    shapeId: {
      immediate: true,
      handler(newVal) {
        if (newVal) {
          this.fetchShapeDetails();
        }
      }
    }
  },
  methods: {
    fetchShapeDetails() {
      this.loading = true;
      this.error = null;
      
      // Use Axios to fetch the measurement details
      axios.get(`/api/measurements/${this.shapeId}`)
        .then(response => {
          if (response.data.measurement) {
            this.form = { ...response.data.measurement };
            this.originalForm = { ...response.data.measurement };
          } else {
            this.error = 'Shape not found';
          }
          this.loading = false;
        })
        .catch(error => {
          console.error('Error fetching shape details:', error);
          this.error = 'Failed to load shape details';
          this.loading = false;
        });
    },
    saveChanges() {
      this.saving = true;
      
      axios.put(`/api/measurements/${this.shapeId}`, this.form)
        .then(response => {
          toast.success('Shape details saved successfully');
          this.saving = false;
          
          // Update the form with any values returned from the server
          if (response.data.measurement) {
            this.form = { ...response.data.measurement };
          }
          
          // Update the original form data
          this.originalForm = { ...this.form };
        })
        .catch(error => {
          toast.error('Failed to save shape details');
          this.saving = false;
          
          // Display validation errors if available
          if (error.response && error.response.data && error.response.data.errors) {
            console.error('Validation errors:', error.response.data.errors);
          } else {
            console.error('Error saving shape:', error);
          }
        });
    },
    formatArea(area) {
      if (!area || isNaN(parseFloat(area))) return 'Not available';
      const value = parseFloat(area);
      return value < 10000 ? 
        `${Math.round(value)} m²` : 
        `${(Math.round(value / 100) / 100).toFixed(2)} hectares`;
    },
    
    formatLength(distance) {
      if (!distance || isNaN(parseFloat(distance))) return 'Not available';
      const value = parseFloat(distance);
      return value < 1000 ? 
        `${Math.round(value)} m` : 
        `${(Math.round(value / 10) / 100).toFixed(2)} km`;
    }
  }
};
</script>

<style scoped>
.shape-form {
  padding: 0 0.5rem;
}

.form-section {
  margin-bottom: 1.5rem;
  border-bottom: 1px solid #eee;
  padding-bottom: 1rem;
}

.form-section:last-child {
  border-bottom: none;
}

.form-group {
  margin-bottom: 1rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-control {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 0.25rem;
}

.form-section {
  margin-bottom: 1.5rem;
}

.form-section h4 {
  border-bottom: 1px solid #eee;
  padding-bottom: 0.5rem;
  margin-bottom: 1rem;
}

.measurement-data {
  background-color: #f8f9fa;
  border-radius: 0.25rem;
  padding: 0.75rem;
}

.measurement-input-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.input-with-display {
  position: relative;
}

.input-display {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  font-size: 0.85rem;
  font-family: monospace;
  pointer-events: none;
}

.measurement-label {
  font-weight: 500;
  margin-right: 0.5rem;
}

.measurement-value {
  font-family: monospace;
}

button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 0.25rem;
  background-color: #007bff;
  color: white;
  cursor: pointer;
  margin-right: 0.5rem;
}

button:hover {
  background-color: #0069d9;
}

button.secondary {
  background-color: #6c757d;
}

button.secondary:hover {
  background-color: #5a6268;
}

.button-group {
  margin-top: 1.5rem;
  display: flex;
  justify-content: flex-end;
}

.loading-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
}
</style>
