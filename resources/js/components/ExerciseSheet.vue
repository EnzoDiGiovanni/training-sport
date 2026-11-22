<script setup lang="ts">
// Fiche exercice : image animée, positions, muscles, consignes, erreurs, mini graphique des performances.
import { onMounted, ref } from 'vue';
import Chart from '@/components/Chart.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
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
    <Sheet
        :title="exercise.name"
        :description="exercise.primary_muscle"
        @close="emit('close')"
    >
        <template v-if="exercise.is_custom" #actions>
            <UButton
                icon="i-lucide-pencil"
                color="neutral"
                variant="ghost"
                size="sm"
                @click="emit('edit', exercise)"
                >Modifier</UButton
            >
        </template>
        <div class="illo"><ExerciseImage :exercise="exercise" /></div>
        <div class="flex flex-wrap gap-2">
            <UBadge variant="subtle">{{ exercise.primary_muscle }}</UBadge>
            <UBadge
                v-if="exercise.secondary_muscles"
                color="neutral"
                variant="subtle"
                >{{ exercise.secondary_muscles }}</UBadge
            >
            <UBadge
                v-if="exercise.equipment"
                color="neutral"
                variant="subtle"
                >{{ exercise.equipment }}</UBadge
            >
        </div>
        <p v-if="exercise.description" class="text-muted">
            {{ exercise.description }}
        </p>
        <div v-if="exercise.instructions?.length" class="space-y-2">
            <h4 class="text-sm font-semibold">Consignes d’exécution</h4>
            <ul class="space-y-1.5 text-sm">
                <li
                    v-for="c in exercise.instructions"
                    :key="c"
                    class="flex gap-2"
                >
                    <UIcon
                        name="i-lucide-check"
                        class="mt-0.5 size-4 flex-none text-primary"
                    />{{ c }}
                </li>
            </ul>
        </div>
        <div v-if="exercise.common_mistakes?.length" class="space-y-2">
            <h4 class="text-sm font-semibold">Erreurs fréquentes</h4>
            <ul class="space-y-1.5 text-sm">
                <li
                    v-for="c in exercise.common_mistakes"
                    :key="c"
                    class="flex gap-2"
                >
                    <UIcon
                        name="i-lucide-x"
                        class="mt-0.5 size-4 flex-none text-error"
                    />{{ c }}
                </li>
            </ul>
        </div>
        <div v-if="history.length" class="space-y-2">
            <h4 class="text-sm font-semibold">Tes performances</h4>
            <Chart
                kind="line"
                :data="
                    history.map((h) => ({
                        label: dateShort(h.date),
                        value: bw ? h.best_reps : h.max_kg,
                    }))
                "
                :unit="
                    bw ? (exercise.measurement === 'time' ? 's' : 'reps') : 'kg'
                "
                :d="1"
                :h="150"
                :step="!bw"
            />
            <div
                class="divide-y divide-default rounded-lg border border-default"
            >
                <div
                    v-for="h in history.slice(-3).reverse()"
                    :key="h.session_id"
                    class="flex gap-3 px-3.5 py-2 text-sm"
                >
                    <span class="w-16 text-muted tabular-nums">{{
                        dateShort(h.date)
                    }}</span
                    ><span class="flex-1 tabular-nums">{{ summary(h) }}</span>
                </div>
            </div>
        </div>
    </Sheet>
</template>
