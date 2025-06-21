<template>
  <!-- Completely standalone dialog with inline styles for maximum compatibility -->
  <div v-if="modelValue" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; height: 100vh !important; width: 100vw !important; z-index: 9999 !important; display: flex !important; align-items: center !important; justify-content: center !important; background-color: rgba(0, 0, 0, 0.5) !important;">
    <div style="background: white !important; border-radius: 8px !important; box-shadow: 0 5px 20px rgba(0,0,0,0.2) !important; width: 95% !important; max-width: 500px !important; margin: 0 auto !important; overflow: hidden !important;">
      <!-- Header section -->
      <div style="padding: 16px 24px !important;">
        <h3 style="font-size: 18px !important; font-weight: 600 !important; margin-bottom: 8px !important; color: #111827 !important;">{{ title }}</h3>
        <p style="color: #4B5563 !important; font-size: 14px !important;">{{ message }}</p>
      </div>
      
      <!-- Footer with buttons -->
      <div style="padding: 16px 24px !important; background-color: #F9FAFB !important; display: flex !important; justify-content: flex-end !important; gap: 12px !important; border-top: 1px solid #E5E7EB !important;">
        <button 
          @click="cancel" 
          style="padding: 8px 16px !important; border: 1px solid #D1D5DB !important; border-radius: 6px !important; font-size: 14px !important; font-weight: 500 !important; background-color: white !important; color: #374151 !important; cursor: pointer !important;"
        >
          {{ cancelText }}
        </button>
        <button 
          @click="confirm" 
          :disabled="loading"
          style="padding: 8px 16px !important; border-radius: 6px !important; font-size: 14px !important; font-weight: 500 !important; background-color: #2563EB !important; color: white !important; cursor: pointer !important; opacity: loading ? 0.5 : 1 !important;"
        >
          {{ loading ? loadingText : confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    modelValue: {
      type: Boolean,
      required: true
    },
    title: {
      type: String,
      default: 'Confirm Action'
    },
    message: {
      type: String,
      required: true
    },
    confirmText: {
      type: String,
      default: 'Continue'
    },
    cancelText: {
      type: String,
      default: 'Cancel'
    },
    loading: {
      type: Boolean,
      default: false
    },
    loadingText: {
      type: String,
      default: 'Processing...'
    }
  },
  watch: {
    modelValue(newValue) {
      console.log('ConfirmDialog modelValue changed:', newValue);
      if (newValue) {
        // Give Vue a moment to update the DOM
        setTimeout(() => {
          // Check if dialog is in DOM
          const dialogElement = document.querySelector('div[style*="z-index: 9999"]');
          console.log('Dialog element found in DOM:', !!dialogElement);
          if (dialogElement) {
            console.log('Dialog element styles:', dialogElement.style.cssText);
            console.log('Dialog element is visible:', dialogElement.offsetParent !== null);
          }
        }, 100);
      }
    }
  },
  mounted() {
    console.log('ConfirmDialog mounted, modelValue:', this.modelValue);
  },
  methods: {
    confirm() {
      this.$emit('confirm');
    },
    cancel() {
      this.$emit('cancel');
      this.$emit('update:modelValue', false);
    }
  }
}
</script>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
