<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MapComponent from '../Components/MapComponent.vue';

const props = defineProps({
  measurements: { type: Array, default: () => [] }
});
</script>

<template>
    <Head title="Welcome" />
    <MapComponent :measurements="props.measurements" />
</template>
