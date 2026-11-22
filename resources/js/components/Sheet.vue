<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import UDrawer from '@nuxt/ui/components/Drawer.vue';
import UModal from '@nuxt/ui/components/Modal.vue';

// Panneau modal : tiroir du bas sur mobile, fenêtre centrée sur desktop. Échap / clic dehors ferment.
const props = defineProps<{
    wide?: boolean;
    title: string;
    description?: string;
}>();
const emit = defineEmits<{ close: [] }>();
const mobile = useMediaQuery('(max-width: 640px)');
const cmp = computed(() => (mobile.value ? UDrawer : UModal));
const ui = computed(() =>
    mobile.value
        ? {
              content: 'max-h-[92dvh]',
              // Seul le corps défile : les boutons du pied restent visibles.
              container:
                  'min-h-0 overflow-hidden pb-[calc(1rem+env(safe-area-inset-bottom))]',
              body: 'min-h-0 space-y-5 overflow-y-auto',
              footer: 'flex-row flex-wrap justify-end border-t border-default pt-3',
          }
        : {
              content: props.wide ? 'sm:max-w-3xl' : 'sm:max-w-lg',
              body: 'space-y-5',
              footer: 'justify-end',
          },
);
</script>

<template>
    <component
        :is="cmp"
        :open="true"
        :title="title"
        :description="description"
        :ui="ui"
        @update:open="!$event && emit('close')"
    >
        <template v-if="$slots.actions" #actions
            ><slot name="actions"
        /></template>
        <template #body><slot /></template>
        <template v-if="$slots.footer" #footer><slot name="footer" /></template>
    </component>
</template>
