<script setup lang="ts">
import { icon } from '@/lib/icons';

defineProps<{ name: string; size?: number }>();
</script>

<template>
    <UIcon
        :name="icon(name)"
        :style="{ width: (size ?? 16) + 'px', height: (size ?? 16) + 'px' }"
        class="shrink-0"
        aria-hidden="true"
    />
</template>
