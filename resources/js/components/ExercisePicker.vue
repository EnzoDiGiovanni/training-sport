<script setup lang="ts">
import { computed, ref } from 'vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Sheet from '@/components/Sheet.vue';
import type { Exercise } from '@/types';

const props = defineProps<{ exercises: Exercise[]; selected: number[] }>();
const emit = defineEmits<{ close: []; pick: [Exercise]; create: [] }>();
const q = ref('');
const list = computed(() =>
    props.exercises.filter(
        (e) =>
            !q.value ||
            (e.name + ' ' + e.primary_muscle)
                .toLowerCase()
                .includes(q.value.toLowerCase()),
    ),
);
</script>

<template>
    <Sheet wide title="Ajouter un exercice" @close="emit('close')">
        <div class="flex gap-2">
            <UInput
                v-model="q"
                icon="i-lucide-search"
                placeholder="Rechercher"
                aria-label="Rechercher"
                class="flex-1"
            />
            <UButton
                icon="i-lucide-plus"
                color="neutral"
                variant="outline"
                @click="emit('create')"
                >Créer</UButton
            >
        </div>
        <div class="grid grid-cols-[repeat(auto-fill,minmax(160px,1fr))] gap-3">
            <button
                v-for="e in list"
                :key="e.id"
                class="overflow-hidden rounded-lg border border-default text-left transition hover:border-primary/50 hover:shadow-sm"
                @click="emit('pick', e)"
            >
                <div class="illo rounded-none">
                    <ExerciseImage :exercise="e" :animated="false" pose="end" />
                </div>
                <div class="flex flex-col gap-0.5 p-3">
                    <span class="text-sm font-semibold">{{ e.name }}</span>
                    <span class="text-xs text-muted">{{
                        e.primary_muscle
                    }}</span>
                    <UBadge
                        v-if="selected.includes(e.id)"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        class="mt-1 self-start"
                        >Déjà dans la séance</UBadge
                    >
                </div>
            </button>
        </div>
    </Sheet>
</template>
