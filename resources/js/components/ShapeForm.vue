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
          <div class="measurement-data">
            <div>
              <span class="measurement-label">Area:</span>
              <span class="measurement-value">{{ formatArea(form.area) }}</span>
            </div>
            <div>
              <span class="measurement-label">Perimeter:</span>
              <span class="measurement-value">{{ formatDistance(form.perimeter) }}</span>
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

.form-section h4 {
  margin-top: 0;
  margin-bottom: 1rem;
  font-size: 1rem;
  font-weight: 600;
  color: #333;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.3rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #444;
}

.form-control {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.875rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.checkbox-label input {
  margin-right: 0.5rem;
}

.measurement-data {
  background-color: #f5f5f5;
  border-radius: 4px;
  padding: 0.5rem;
}

.measurement-data > div {
  display: flex;
  justify-content: space-between;
  padding: 0.25rem 0;
}

.measurement-label {
  font-weight: 500;
}

.form-actions {
  margin-top: 1.5rem;
  display: flex;
  justify-content: flex-end;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.btn-primary {
  background-color: #3490dc;
  color: white;
}

.btn-primary:hover {
  background-color: #2779bd;
}

.btn-primary:disabled {
  background-color: #a0cbef;
  cursor: not-allowed;
}

.loading-state,
.error-state {
  padding: 1rem;
  text-align: center;
  color: #666;
}

.error-state {
  color: #e3342f;
}
</style>
