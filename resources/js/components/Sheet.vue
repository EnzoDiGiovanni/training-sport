<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Panneau modal (bottom sheet sur mobile), fermable avec Échap ou clic sur le fond.
defineProps<{ wide?: boolean; label?: string }>();
const emit = defineEmits<{ close: [] }>();
const el = ref<HTMLElement>();
let prevFocus: Element | null = null;
const onKey = (e: KeyboardEvent) => e.key === 'Escape' && emit('close');

onMounted(() => {
    prevFocus = document.activeElement;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);
    el.value?.focus();
});
onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
    (prevFocus as HTMLElement | null)?.focus?.();
});
</script>

<template>
    <Teleport to="body">
        <div class="scrim" @click.self="emit('close')">
            <div ref="el" class="sheet" :class="{ wide }" role="dialog" aria-modal="true" :aria-label="label" tabindex="-1">
                <slot />
            </div>
        </div>
    </Teleport>
</template>
