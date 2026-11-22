<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import ExerciseIllustration from '@/components/ExerciseIllustration.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import StartConflictSheet from '@/components/StartConflictSheet.vue';
import TypeChip from '@/components/TypeChip.vue';
import { cap, dateLong, daysAgo, fmt, fmtClock } from '@/composables/useFormat';
import {
    durationLabel,
    estRunKm,
    exerciseById,
    kgLabel,
    repsLabel,
    setsSummary,
    speedLabel,
} from '@/lib/domain';
import { useStartWorkout } from '@/lib/start';
import type { Exercise, Profile, Session, SessionItem, Workout } from '@/types';

const props = defineProps<{
    workout: Workout;
    exercises: Exercise[];
    profile: Profile;
    last: Session | null;
    lastPerf: Record<number, (SessionItem & { date: string }) | null>;
}>();
const w = props.workout;
const ex = (id: number) => exerciseById(props.exercises, id)!;
const { start, conflict, abandonAndStart } = useStartWorkout();
const shown = ref<Exercise | null>(null);
</script>

<template>
    <Head :title="w.name" />
    <div class="space-y-3">
        <UButton
            to="/"
            icon="i-lucide-chevron-left"
            color="neutral"
            variant="ghost"
            size="sm"
            class="-ml-2"
            >Accueil</UButton
        >
        <UPageHeader
            :title="w.name"
            :description="w.objective ?? undefined"
            :ui="{ root: 'pt-0' }"
        >
            <template #headline>
                <TypeChip :type="w.type" /><span
                    class="text-sm text-muted tabular-nums"
                    >{{ durationLabel(w) }}</span
                >
            </template>
            <template #links>
                <UButton
                    :to="`/programme/seances/${w.id}`"
                    icon="i-lucide-pencil"
                    color="neutral"
                    variant="outline"
                    >Modifier</UButton
                >
                <UButton
                    v-if="w.type === 'run' || w.type === 'strength'"
                    icon="i-lucide-play"
                    @click="start(w)"
                    >Commencer</UButton
                >
            </template>
        </UPageHeader>
    </div>

    <div
        v-if="w.type === 'run'"
        class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]"
    >
        <UCard>
            <template #header>
                <div class="flex items-baseline justify-between">
                    <h2 class="font-semibold">Déroulé de la séance</h2>
                    <span class="text-sm text-muted tabular-nums"
                        >≈ {{ fmt(estRunKm(w.segments)) }} km</span
                    >
                </div>
            </template>
            <RunTimeline :segments="w.segments" />
            <table class="sets mt-4">
                <thead>
                    <tr>
                        <th>Phase</th>
                        <th>Durée</th>
                        <th>Vitesse</th>
                        <th>Incl.</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(s, i) in w.segments" :key="i">
                        <template v-if="s.kind === 'repeat'">
                            <tr>
                                <td
                                    colspan="4"
                                    class="text-sm font-medium text-primary"
                                >
                                    Répéter {{ s.repeat_count }} fois
                                </td>
                            </tr>
                            <tr v-for="(x, j) in s.children" :key="j">
                                <td class="pl-6">{{ x.label }}</td>
                                <td class="tabular-nums">
                                    {{ x.duration_min }} min
                                </td>
                                <td class="tabular-nums">
                                    {{ speedLabel(x) }}
                                </td>
                                <td class="tabular-nums">
                                    {{ fmt(x.incline_pct || 0, 0) }} %
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td>{{ s.label }}</td>
                            <td class="tabular-nums">
                                {{ s.duration_min }} min
                            </td>
                            <td class="tabular-nums">{{ speedLabel(s) }}</td>
                            <td class="tabular-nums">
                                {{ fmt(s.incline_pct || 0, 0) }} %
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </UCard>
        <div class="space-y-4">
            <div class="illo">
                <ExerciseIllustration
                    illustration-key="run"
                    label="Course sur tapis"
                />
            </div>
            <UCard>
                <template #header>
                    <h2 class="font-semibold">Dernière fois</h2>
                </template>
                <template v-if="last && last.run">
                    <div class="mb-4 flex justify-between text-sm">
                        <span class="text-muted">{{
                            cap(dateLong(last.date))
                        }}</span
                        ><span class="text-dimmed">{{
                            daysAgo(last.date)
                        }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            v-for="[k, v] in [
                                [
                                    'Distance',
                                    fmt(last.run.distance_km, 2) + ' km',
                                ],
                                ['Durée', fmtClock(last.duration_seconds)],
                                ['Vit. moy.', fmt(last.run.average_speed_kmh)],
                            ]"
                            :key="k"
                        >
                            <p class="text-xs text-muted">{{ k }}</p>
                            <p class="font-semibold tabular-nums">{{ v }}</p>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-muted">Pas encore réalisée.</p>
            </UCard>
            <UAlert
                variant="subtle"
                color="info"
                icon="i-lucide-info"
                :description="`Tapis limité à ${fmt(
                    profile.treadmill_max_kmh,
                    profile.treadmill_max_kmh % 1 ? 1 : 0,
                )} km/h. Inclinaison conseillée : 1 % pour compenser l’absence de résistance de l’air, si c’est confortable.`"
            />
        </div>
    </div>

    <div
        v-else-if="w.type === 'strength'"
        class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4"
    >
        <UCard
            v-for="(we, i) in w.exercises"
            :key="we.exercise_id"
            as="button"
            class="overflow-hidden text-left transition hover:shadow-md hover:ring-primary/40"
            :ui="{ body: 'p-0 sm:p-0' }"
            @click="shown = ex(we.exercise_id)"
        >
            <div class="illo rounded-none">
                <ExerciseImage :exercise="ex(we.exercise_id)" />
            </div>
            <div class="flex flex-col gap-0.5 p-4">
                <div class="flex justify-between text-xs text-muted">
                    <span class="tabular-nums">{{
                        String(i + 1).padStart(2, '0')
                    }}</span
                    ><span>Repos {{ fmtClock(we.rest_seconds) }}</span>
                </div>
                <span class="font-semibold">{{ ex(we.exercise_id).name }}</span>
                <span class="text-sm text-muted">{{
                    ex(we.exercise_id).primary_muscle
                }}</span>
                <span class="mt-2 text-sm tabular-nums"
                    >{{ we.sets }} ×
                    {{
                        repsLabel(we.reps_min, we.reps_max, ex(we.exercise_id))
                    }}
                    ·
                    {{ kgLabel(we.target_weight_kg, ex(we.exercise_id)) }}</span
                >
                <span class="text-xs text-dimmed tabular-nums">{{
                    lastPerf[we.exercise_id]
                        ? 'Dernière fois : ' +
                          setsSummary(lastPerf[we.exercise_id]!)
                        : 'Pas encore réalisé'
                }}</span>
            </div>
        </UCard>
    </div>

    <ExerciseSheet v-if="shown" :exercise="shown" @close="shown = null" />
    <StartConflictSheet
        v-if="conflict"
        :workout="conflict"
        @close="conflict = null"
        @abandon="abandonAndStart"
    />
</template>
