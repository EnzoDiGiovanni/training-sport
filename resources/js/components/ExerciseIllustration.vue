<script setup lang="ts">
import { computed } from 'vue';
import { illoSvg } from '@/lib/illustrations';

// pose : 'start' | 'end' fige la silhouette ; null = animation (sauf prefers-reduced-motion).
const props = withDefaults(defineProps<{ illustrationKey: string | null; animated?: boolean; pose?: 'start' | 'end' | null; label?: string }>(), {
    animated: true,
    pose: null,
});
const svg = computed(() =>
    illoSvg(props.illustrationKey, {
        animate: props.animated,
        pose: props.pose === 'start' ? 'A' : props.pose === 'end' ? 'B' : undefined,
        label: props.label,
    }),
);
</script>

<template>
    <span style="display: contents" v-html="svg" />
</template>
