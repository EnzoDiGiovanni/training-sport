<script setup lang="ts">
import { fmtClock } from '@/composables/useFormat';

defineProps<{ label: string; left: number; total: number }>();
defineEmits<{ adjust: [number]; skip: [] }>();
</script>

<template>
    <UCard
        role="timer"
        aria-live="off"
        class="fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] left-1/2 z-40 w-[min(520px,calc(100%-24px))] -translate-x-1/2 shadow-lg lg:bottom-6"
        :ui="{
            body: 'flex flex-wrap items-center gap-3 p-3 sm:flex-nowrap sm:p-4',
        }"
        :class="left <= 0 ? 'ring-primary' : ''"
    >
        <div class="flex min-w-16 flex-col">
            <span class="text-xs text-muted">{{
                left <= 0 ? 'Repos terminé' : 'Récupération'
            }}</span>
            <span
                class="text-2xl font-semibold tabular-nums"
                :class="left <= 0 ? 'text-primary' : ''"
                >{{ left <= 0 ? 'Go' : fmtClock(left) }}</span
            >
        </div>
        <div
            class="flex min-w-0 flex-[1_1_calc(100%-100px)] flex-col gap-2 sm:flex-1"
        >
            <span class="truncate text-sm text-muted">{{ label }}</span>
            <UProgress
                :model-value="Math.max(0, Math.min(100, (left / total) * 100))"
                size="sm"
            />
        </div>
        <UButton
            color="neutral"
            variant="ghost"
            size="sm"
            aria-label="Retirer 15 secondes"
            @click="$emit('adjust', -15)"
            >−15</UButton
        >
        <UButton
            color="neutral"
            variant="ghost"
            size="sm"
            aria-label="Ajouter 15 secondes"
            @click="$emit('adjust', 15)"
            >+15</UButton
        >
        <UButton
            color="neutral"
            variant="outline"
            size="sm"
            @click="$emit('skip')"
        >
            {{ left <= 0 ? 'Fermer' : 'Passer' }}
        </UButton>
    </UCard>
</template>
