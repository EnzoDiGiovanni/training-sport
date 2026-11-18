<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { TYPE_ICON, TYPE_LABEL } from '@/lib/domain';
import type { WorkoutType } from '@/types';

defineProps<{ type: WorkoutType }>();
</script>

<template>
    <span class="chip" :class="'chip-' + type"
        ><Icon :name="TYPE_ICON[type]" :size="13" />{{ TYPE_LABEL[type] }}</span
    >
</template>
