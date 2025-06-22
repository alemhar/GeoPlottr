<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" :class="className" v-bind="$attrs">
        <!-- Globe/Map Circle -->  
        <circle cx="20" cy="20" r="15" fill="none" stroke="currentColor" stroke-width="2" />
        <!-- Latitude lines -->  
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="1"
            d="M5 20h30 M8 12h24 M8 28h24" 
        />
        <!-- Longitude lines --> 
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="1"
            d="M20 5v30 M12 8v24 M28 8v24" 
        />
        <!-- Location Marker --> 
        <path
            fill="currentColor"
            d="M20 10c-3.3 0-6 2.7-6 6 0 4.4 6 14 6 14s6-9.6 6-14c0-3.3-2.7-6-6-6zm0 8.5c-1.4 0-2.5-1.1-2.5-2.5s1.1-2.5 2.5-2.5 2.5 1.1 2.5 2.5-1.1 2.5-2.5 2.5z"
        />
    </svg>
</template>
