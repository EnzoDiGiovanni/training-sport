<script setup lang="ts">
import ExerciseIllustration from '@/components/ExerciseIllustration.vue';

// Image importée → illustration intégrée → silhouette neutre « Ajoute une image ».
defineProps<{
    exercise: { name: string; image_url?: string | null; illustration_key?: string | null } | null | undefined;
    animated?: boolean;
    pose?: 'start' | 'end' | null;
}>();
</script>

<template>
    <img v-if="exercise?.image_url" :src="exercise.image_url" :alt="exercise.name" loading="lazy" />
    <ExerciseIllustration v-else-if="exercise?.illustration_key" :illustration-key="exercise.illustration_key" :animated="animated !== false" :pose="pose ?? null" :label="'Illustration : ' + exercise.name" />
    <span v-else class="illo-empty">
        <ExerciseIllustration illustration-key="custom" :animated="false" label="Silhouette neutre" />
        <span>Ajoute une image</span>
    </span>
</template>
