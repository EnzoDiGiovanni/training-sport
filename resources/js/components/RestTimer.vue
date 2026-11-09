<script setup lang="ts">
import { fmtClock } from '@/composables/useFormat';

defineProps<{ label: string; left: number; total: number }>();
defineEmits<{ adjust: [number]; skip: [] }>();
</script>

<template>
    <div class="rest" :class="{ 'is-over': left <= 0 }" role="timer" aria-live="off">
        <div class="stack" style="min-width: 0">
            <span class="faint" style="font-size: 11.5px">{{ left <= 0 ? 'Repos terminé' : 'Récupération' }}</span>
            <span class="clock" :style="left <= 0 ? 'color:var(--accent)' : ''">{{ left <= 0 ? 'Go' : fmtClock(left) }}</span>
        </div>
        <div class="stack gap-8 grow" style="min-width: 0">
            <span class="muted" style="font-size: 12.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{{ label }}</span>
            <div class="bar"><i :style="{ width: Math.max(0, Math.min(100, (left / total) * 100)) + '%' }"></i></div>
        </div>
        <button class="btn btn-sm btn-ghost" aria-label="Retirer 15 secondes" @click="$emit('adjust', -15)">−15</button>
        <button class="btn btn-sm btn-ghost" aria-label="Ajouter 15 secondes" @click="$emit('adjust', 15)">+15</button>
        <button class="btn btn-sm" @click="$emit('skip')">{{ left <= 0 ? 'Fermer' : 'Passer' }}</button>
    </div>
</template>
