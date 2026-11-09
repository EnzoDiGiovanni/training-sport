<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExerciseFormSheet from '@/components/ExerciseFormSheet.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import Icon from '@/components/Icon.vue';
import type { Exercise } from '@/types';

const props = defineProps<{ exercises: Exercise[] }>();
const GROUPS: Record<string, string> = { all: 'Tous', chest: 'Pectoraux', back: 'Dos', legs: 'Jambes', shoulders: 'Épaules', arms: 'Bras', core: 'Abdominaux' };
const q = ref('');
const group = ref('all');
const list = computed(() => {
    const s = q.value.toLowerCase().trim();
    return props.exercises.filter((e) => (!s || (e.name + ' ' + e.primary_muscle).toLowerCase().includes(s)) && (group.value === 'all' || e.muscle_group === group.value));
});
const shown = ref<number | null>(null);
const shownEx = computed(() => props.exercises.find((e) => e.id === shown.value) ?? null);
const editing = ref<Exercise | null | undefined>(undefined); // undefined = fermé, null = création
</script>

<template>
    <Head title="Bibliothèque" />
    <div class="page-head">
        <div class="stack gap-4"><span class="eyebrow">Configuration</span><h1 class="title-xl">Bibliothèque</h1></div>
        <div class="seg" role="group" aria-label="Section">
            <Link href="/programme" as="button" :aria-pressed="false">Programme</Link>
            <Link href="/exercices" as="button" :aria-pressed="true">Bibliothèque d’exercices</Link>
        </div>
    </div>
    <div class="row wrapflex" style="justify-content: space-between">
        <div class="input-unit" style="min-width: 240px; flex: 1; max-width: 360px">
            <input v-model="q" class="input" placeholder="Rechercher un exercice" aria-label="Rechercher un exercice" /><span><Icon name="search" /></span>
        </div>
        <button class="btn btn-primary" @click="editing = null"><Icon name="plus" />Exercice personnalisé</button>
    </div>
    <div class="filters">
        <button v-for="(l, k) in GROUPS" :key="k" class="fchip" :aria-pressed="group === k" @click="group = k">{{ l }}</button>
    </div>
    <div class="lib-grid">
        <button v-for="e in list" :key="e.id" class="card lib-card" @click="shown = e.id">
            <div class="illo"><ExerciseImage :exercise="e" /></div>
            <div class="body">
                <span style="font-weight: 600">{{ e.name }}</span>
                <span class="faint" style="font-size: 12.5px">{{ e.primary_muscle }}</span>
                <span class="faint" style="font-size: 12px">{{ e.equipment }}</span>
                <span v-if="e.is_custom" class="chip" style="align-self: flex-start; margin-top: 4px">Personnalisé</span>
            </div>
        </button>
        <div v-if="!list.length" class="card empty">Aucun exercice trouvé.</div>
    </div>
    <ExerciseSheet v-if="shownEx" :exercise="shownEx" @close="shown = null" @edit="(e) => { shown = null; editing = e; }" />
    <ExerciseFormSheet v-if="editing !== undefined" :exercise="editing" @close="editing = undefined" @saved="editing = undefined" />
</template>
