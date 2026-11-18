<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Sheet from '@/components/Sheet.vue';
import type { Workout } from '@/types';

defineProps<{ workout: Workout }>();
defineEmits<{ close: []; abandon: [] }>();
const page = usePage();
</script>

<template>
    <Sheet label="Une séance est déjà en cours" @close="$emit('close')">
        <div class="sheet-head">
            <h3 class="title-m grow">Une séance est déjà en cours</h3>
        </div>
        <div class="sheet-body">
            <p class="muted">
                « {{ page.props.live?.name }} » n’est pas terminée. Tu peux la
                reprendre ou l’abandonner pour commencer « {{ workout.name }} ».
            </p>
        </div>
        <div class="sheet-foot">
            <button class="btn btn-danger" @click="$emit('abandon')">
                Abandonner et commencer
            </button>
            <Link href="/seance-en-cours" class="btn btn-primary"
                >Reprendre</Link
            >
        </div>
    </Sheet>
</template>
