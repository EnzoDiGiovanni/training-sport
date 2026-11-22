<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExerciseFormSheet from '@/components/ExerciseFormSheet.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import type { Exercise } from '@/types';

const props = defineProps<{ exercises: Exercise[] }>();
const GROUPS: Record<string, string> = {
    all: 'Tous',
    chest: 'Pectoraux',
    back: 'Dos',
    legs: 'Jambes',
    shoulders: 'Épaules',
    arms: 'Bras',
    core: 'Abdominaux',
};
const q = ref('');
const group = ref('all');
const list = computed(() => {
    const s = q.value.toLowerCase().trim();
    return props.exercises.filter(
        (e) =>
            (!s ||
                (e.name + ' ' + e.primary_muscle).toLowerCase().includes(s)) &&
            (group.value === 'all' || e.muscle_group === group.value),
    );
});
const shown = ref<number | null>(null);
const shownEx = computed(
    () => props.exercises.find((e) => e.id === shown.value) ?? null,
);
const editing = ref<Exercise | null | undefined>(undefined); // undefined = fermé, null = création
</script>

<template>
    <Head title="Bibliothèque" />
    <UPageHeader headline="Configuration" title="Bibliothèque">
        <template #links>
            <UFieldGroup aria-label="Section">
                <UButton to="/programme" color="neutral" variant="outline"
                    >Programme</UButton
                >
                <UButton
                    to="/exercices"
                    color="neutral"
                    variant="subtle"
                    aria-current="page"
                    >Bibliothèque d’exercices</UButton
                >
            </UFieldGroup>
        </template>
    </UPageHeader>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <UInput
            v-model="q"
            icon="i-lucide-search"
            placeholder="Rechercher un exercice"
            aria-label="Rechercher un exercice"
            class="min-w-60 flex-1 sm:max-w-sm"
        />
        <UButton icon="i-lucide-plus" @click="editing = null"
            >Exercice personnalisé</UButton
        >
    </div>
    <UTabs
        v-model="group"
        :items="
            Object.entries(GROUPS).map(([value, label]) => ({ value, label }))
        "
        :content="false"
        :ui="{ list: 'w-auto', trigger: 'grow-0' }"
        variant="pill"
        size="sm"
        class="max-w-full overflow-x-auto"
    />
    <div class="grid grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-4">
        <UCard
            v-for="e in list"
            :key="e.id"
            as="button"
            class="overflow-hidden text-left transition hover:shadow-md hover:ring-primary/40"
            :ui="{ body: 'p-0 sm:p-0' }"
            @click="shown = e.id"
        >
            <div class="illo rounded-none"><ExerciseImage :exercise="e" /></div>
            <div class="flex flex-col gap-0.5 p-4">
                <span class="font-semibold">{{ e.name }}</span>
                <span class="text-sm text-muted">{{ e.primary_muscle }}</span>
                <span class="text-xs text-dimmed">{{ e.equipment }}</span>
                <UBadge
                    v-if="e.is_custom"
                    color="neutral"
                    variant="subtle"
                    size="sm"
                    class="mt-1 self-start"
                    >Personnalisé</UBadge
                >
            </div>
        </UCard>
    </div>
    <UEmpty
        v-if="!list.length"
        icon="i-lucide-search-x"
        title="Aucun exercice trouvé."
    />
    <ExerciseSheet
        v-if="shownEx"
        :exercise="shownEx"
        @close="shown = null"
        @edit="
            (e) => {
                shown = null;
                editing = e;
            }
        "
    />
    <ExerciseFormSheet
        v-if="editing !== undefined"
        :exercise="editing"
        @close="editing = undefined"
        @saved="editing = undefined"
    />
</template>
