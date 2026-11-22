<script setup lang="ts">
import { TYPE_COLOR, TYPE_ICON, TYPE_LABEL } from '@/lib/domain';
import { icon } from '@/lib/icons';
import type { WorkoutType } from '@/types';

defineProps<{ type: WorkoutType }>();
</script>

<template>
    <UBadge
        :color="TYPE_COLOR[type]"
        variant="subtle"
        :icon="icon(TYPE_ICON[type])"
        >{{ TYPE_LABEL[type] }}</UBadge
    >
</template>
