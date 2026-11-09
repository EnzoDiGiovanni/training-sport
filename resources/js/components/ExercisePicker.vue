<script setup lang="ts">
import { computed, ref } from 'vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Icon from '@/components/Icon.vue';
import Sheet from '@/components/Sheet.vue';
import type { Exercise } from '@/types';

const props = defineProps<{ exercises: Exercise[]; selected: number[] }>();
const emit = defineEmits<{ close: []; pick: [Exercise]; create: [] }>();
const q = ref('');
const list = computed(() => props.exercises.filter((e) => !q.value || (e.name + ' ' + e.primary_muscle).toLowerCase().includes(q.value.toLowerCase())));
</script>

<template>
    <Sheet wide label="Ajouter un exercice" @close="emit('close')">
        <div class="sheet-head">
            <h3 class="grow title-m">Ajouter un exercice</h3>
            <button class="btn btn-ghost btn-icon" aria-label="Fermer" @click="emit('close')"><Icon name="x" /></button>
        </div>
        <div class="sheet-body">
            <div class="row gap-8">
                <input v-model="q" class="input grow" placeholder="Rechercher" aria-label="Rechercher" />
                <button class="btn" @click="emit('create')"><Icon name="plus" />Créer</button>
            </div>
            <div class="lib-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr))">
                <button v-for="e in list" :key="e.id" class="card lib-card" @click="emit('pick', e)">
                    <div class="illo"><ExerciseImage :exercise="e" :animated="false" pose="end" /></div>
                    <div class="body">
                        <span style="font-weight: 600; font-size: 14px">{{ e.name }}</span>
                        <span class="faint" style="font-size: 12px">{{ e.primary_muscle }}</span>
                        <span v-if="selected.includes(e.id)" class="chip" style="align-self: flex-start">Déjà dans la séance</span>
                    </div>
                </button>
            </div>
        </div>
    </Sheet>
</template>
