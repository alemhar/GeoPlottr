<template>
  <div v-show="modelValue" class="side-panel" :style="panelStyle">
    <div class="side-panel-header">
      <h3>{{ title }}</h3>
      <button @click="close" class="close-btn">&times;</button>
    </div>
    <div class="side-panel-content">
      <!-- Slot for custom content -->
      <slot></slot>
    </div>
    <div v-if="$slots.footer" class="side-panel-footer">
      <!-- Optional footer slot -->
      <slot name="footer"></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SidePanel',
  props: {
    // Using v-model pattern with modelValue
    modelValue: {
      type: Boolean,
      default: false
    },
    // Panel title
    title: {
      type: String,
      default: 'Details'
    },
    // Width of the panel (with CSS units)
    width: {
      type: String,
      default: '380px'
    }
  },
  emits: ['update:modelValue', 'close'],
  methods: {
    close() {
      this.$emit('update:modelValue', false);
      this.$emit('close');
    }
  },
  computed: {
    // Use computed style instead of direct DOM manipulation
    panelStyle() {
      return {
        width: this.width,
        transform: this.modelValue ? 'translateX(0)' : 'translateX(100%)'
      };
    }
  },
  watch: {
    // Watch for modelValue changes to add/remove body class
    modelValue(newVal) {
      if (newVal) {
        document.body.classList.add('side-panel-open');
      } else {
        document.body.classList.remove('side-panel-open');
      }
    }
  },
  beforeUnmount() {
    // Clean up body class
    document.body.classList.remove('side-panel-open');
  }
};
</script>

<style scoped>
.side-panel {
  position: fixed;
  top: 0;
  right: 0;
  height: 100vh;
  background-color: white;
  box-shadow: -2px 0 5px rgba(0,0,0,0.2);
  z-index: 1000;
  display: flex;
  flex-direction: column;
  width: 380px; /* Default width, can be overridden by props */
  transition: transform 0.3s ease;
}

.side-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid #eee;
}

.side-panel-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
}

.close-btn:hover {
  color: #000;
}

.side-panel-content {
  padding: 16px;
  flex: 1;
  overflow-y: auto;
}

.side-panel-footer {
  padding: 16px;
  border-top: 1px solid #eee;
  background-color: #f9f9f9;
}

/* Global style for body when panel is open */
:global(body.side-panel-open) {
  overflow: hidden;
}
</style>
