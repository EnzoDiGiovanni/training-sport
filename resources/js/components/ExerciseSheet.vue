<script setup lang="ts">
// Fiche exercice : image animée, positions, muscles, consignes, erreurs, mini graphique des performances.
import { onMounted, ref } from 'vue';
import Chart from '@/components/Chart.vue';
import ExerciseIllustration from '@/components/ExerciseIllustration.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Icon from '@/components/Icon.vue';
import Sheet from '@/components/Sheet.vue';
import { dateShort } from '@/composables/useFormat';
import { setsSummary } from '@/lib/domain';
import { json } from '@/lib/http';
import type { Exercise, ExerciseHistoryPoint } from '@/types';

const props = defineProps<{ exercise: Exercise }>();
const emit = defineEmits<{ close: []; edit: [Exercise] }>();
const history = ref<ExerciseHistoryPoint[]>([]);
onMounted(
    async () =>
        (history.value = await json(
            `/api/exercises/${props.exercise.id}/history`,
        )),
);
const bw = props.exercise.load_type === 'bodyweight';
const summary = (h: ExerciseHistoryPoint) =>
    setsSummary({
        sets: h.sets.map((s) => ({ ...s, is_completed: true })),
        load_type: props.exercise.load_type,
        measurement: props.exercise.measurement,
    });
</script>

<template>
    <Sheet :label="exercise.name" @close="emit('close')">
        <div class="sheet-head">
            <div class="stack grow">
                <span class="faint" style="font-size: 12px">{{
                    exercise.primary_muscle
                }}</span>
                <h3 class="title-m">{{ exercise.name }}</h3>
            </div>
            <button
                v-if="exercise.is_custom"
                class="btn btn-sm btn-ghost"
                @click="emit('edit', exercise)"
            >
                <Icon name="edit" />Modifier
            </button>
            <button
                class="btn btn-ghost btn-icon"
                aria-label="Fermer"
                @click="emit('close')"
            >
                <Icon name="x" />
            </button>
        </div>
        <div class="sheet-body">
            <div class="illo"><ExerciseImage :exercise="exercise" /></div>
            <div
                v-if="!exercise.image_url && exercise.illustration_key"
                class="frames"
            >
                <figure>
                    <div class="illo">
                        <ExerciseIllustration
                            :illustration-key="exercise.illustration_key"
                            pose="start"
                            label="Position de départ"
                        />
                    </div>
                    <figcaption>Position de départ</figcaption>
                </figure>
                <figure>
                    <div class="illo">
                        <ExerciseIllustration
                            :illustration-key="exercise.illustration_key"
                            pose="end"
                            label="Position finale"
                        />
                    </div>
                    <figcaption>Position finale</figcaption>
                </figure>
            </div>
            <div class="row wrapflex gap-8">
                <span class="chip chip-strength">{{
                    exercise.primary_muscle
                }}</span>
                <span v-if="exercise.secondary_muscles" class="chip">{{
                    exercise.secondary_muscles
                }}</span>
                <span v-if="exercise.equipment" class="chip">{{
                    exercise.equipment
                }}</span>
            </div>
            <p v-if="exercise.description" class="muted">
                {{ exercise.description }}
            </p>
            <div v-if="exercise.instructions?.length" class="stack gap-8">
                <span class="label">Consignes d’exécution</span>
                <ul class="cues">
                    <li v-for="c in exercise.instructions" :key="c">{{ c }}</li>
                </ul>
            </div>
            <div v-if="exercise.common_mistakes?.length" class="stack gap-8">
                <span class="label">Erreurs fréquentes</span>
                <ul class="cues bad">
                    <li v-for="c in exercise.common_mistakes" :key="c">
                        {{ c }}
                    </li>
                </ul>
            </div>
            <div v-if="history.length" class="stack gap-8">
                <span class="label">Tes performances</span>
                <Chart
                    kind="line"
                    :data="
                        history.map((h) => ({
                            label: dateShort(h.date),
                            value: bw ? h.best_reps : h.max_kg,
                        }))
                    "
                    :unit="
                        bw
                            ? exercise.measurement === 'time'
                                ? 's'
                                : 'reps'
                            : 'kg'
                    "
                    :d="1"
                    :h="150"
                    :step="!bw"
                />
                <div class="card" style="background: var(--bg)">
                    <div
                        v-for="h in history.slice(-3).reverse()"
                        :key="h.session_id"
                        class="row"
                        style="
                            padding: 9px 14px;
                            border-bottom: 1px solid var(--border);
                        "
                    >
                        <span
                            class="muted num"
                            style="width: 70px; font-size: 13px"
                            >{{ dateShort(h.date) }}</span
                        ><span class="num grow" style="font-size: 13.5px">{{
                            summary(h)
                        }}</span>
                    </div>
                </div>
            </div>
        </div>
    </Sheet>
</template>
