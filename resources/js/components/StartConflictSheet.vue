<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import Sheet from '@/components/Sheet.vue';
import type { Workout } from '@/types';

defineProps<{ workout: Workout }>();
defineEmits<{ close: []; abandon: [] }>();
const page = usePage();
</script>

<template>
    <Sheet title="Une séance est déjà en cours" @close="$emit('close')">
        <p class="text-muted">
            « {{ page.props.live?.name }} » n’est pas terminée. Tu peux la
            reprendre ou l’abandonner pour commencer « {{ workout.name }} ».
        </p>
        <template #footer>
            <UButton color="error" variant="soft" @click="$emit('abandon')">
                Abandonner et commencer
            </UButton>
            <UButton to="/seance-en-cours">Reprendre</UButton>
        </template>
    </Sheet>
</template>
