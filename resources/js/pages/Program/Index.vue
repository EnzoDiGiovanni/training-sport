<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import Sheet from '@/components/Sheet.vue';
import TypeChip from '@/components/TypeChip.vue';
import { DAY_NAMES, DAY_SHORT, fmtKg } from '@/composables/useFormat';
import {
    durationLabel,
    exerciseById,
    flatSteps,
    maxLoadFor,
} from '@/lib/domain';
import type { Exercise, Profile, Workout, WorkoutType } from '@/types';

const props = defineProps<{
    workouts: Workout[];
    schedule: Record<number, number | null>;
    exercises: Exercise[];
    profile: Profile;
    sessionsCount: number;
}>();
const byId = (id: number | null) => props.workouts.find((w) => w.id === id);
const planned = computed(
    () =>
        Object.values(props.schedule).filter((id) =>
            ['run', 'strength'].includes(byId(id)?.type ?? ''),
        ).length,
);

// Avertissements matériel / tapis (règle 7 et 8, affichés en haut de page).
const warnings = computed(() => {
    const out: string[] = [];
    const p = props.profile;
    props.workouts.forEach((w) => {
        if (w.type === 'strength')
            w.exercises.forEach((we) => {
                const ex = exerciseById(props.exercises, we.exercise_id);
                if (!ex || ex.load_type === 'bodyweight') return;
                const mx = maxLoadFor(ex.load_type, p);
                if ((we.target_weight_kg ?? 0) > mx)
                    out.push(
                        `${w.name} · ${ex.name} : ${fmtKg(we.target_weight_kg!)} kg prévus, au-delà de ton matériel (${fmtKg(mx)} kg max${ex.load_type === 'two_dumbbells' ? ' par haltère' : ''}).`,
                    );
            });
        if (w.type === 'run')
            flatSteps(w.segments).forEach((s) => {
                if (s.speed_max_kmh > p.treadmill_max_kmh)
                    out.push(
                        `${w.name} · ${s.label} : ${s.speed_max_kmh} km/h prévus, ton tapis est limité à ${p.treadmill_max_kmh} km/h.`,
                    );
            });
    });
    return [...new Set(out)];
});

const setDay = (day: number, value: string) =>
    router.put(
        `/programme/jours/${day}`,
        { workout_id: value ? +value : null },
        { preserveScroll: true },
    );
const daysOf = (w: Workout) =>
    Object.entries(props.schedule)
        .filter(([, id]) => id === w.id)
        .map(([d]) => DAY_SHORT[+d - 1]);
// Le select n'accepte pas de valeur vide : « 0 » représente le repos.
const dayItems = computed(() => [
    { label: 'Repos', value: '0' },
    ...props.workouts.map((w) => ({ label: w.name, value: String(w.id) })),
]);
const toDelete = ref<Workout | null>(null);
const newOpen = ref(false);
const destroy = () =>
    router.delete(`/programme/seances/${toDelete.value!.id}`, {
        preserveScroll: true,
        onFinish: () => (toDelete.value = null),
    });
const NEW_TYPES: [WorkoutType, string][] = [
    ['strength', 'Exercices, séries, charges'],
    ['run', 'Phases, vitesses, fractionné'],
    ['recovery', 'Activité douce facultative'],
];
</script>

<template>
    <Head title="Programme" />
    <UPageHeader headline="Configuration" title="Mon programme">
        <template #links>
            <UFieldGroup aria-label="Section">
                <UButton
                    to="/programme"
                    color="neutral"
                    variant="subtle"
                    aria-current="page"
                    >Programme</UButton
                >
                <UButton to="/exercices" color="neutral" variant="outline"
                    >Bibliothèque d’exercices</UButton
                >
            </UFieldGroup>
        </template>
    </UPageHeader>
    <p class="-mt-4 flex items-start gap-1.5 text-sm text-muted">
        <UIcon name="i-lucide-info" class="mt-0.5 size-4 flex-none" />
        <span
            >Les modifications s’appliquent aux prochaines séances et au tableau
            de bord. Les {{ sessionsCount }} séances déjà enregistrées gardent
            leurs valeurs d’origine.</span
        >
    </p>
    <UAlert
        v-if="warnings.length"
        variant="subtle"
        color="warning"
        icon="i-lucide-triangle-alert"
    >
        <template #description>
            <ul class="space-y-1">
                <li v-for="(w, i) in warnings" :key="i">{{ w }}</li>
            </ul>
        </template>
    </UAlert>

    <section class="space-y-3">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-semibold">Semaine type</h2>
            <span class="text-sm text-muted tabular-nums"
                >{{ planned }} séances / semaine</span
            >
        </div>
        <UCard :ui="{ body: 'p-0 sm:p-0 divide-y divide-default' }">
            <div
                v-for="(n, i) in DAY_NAMES"
                :key="n"
                class="flex flex-wrap items-center gap-3 px-4 py-2.5"
            >
                <span class="w-24 text-sm font-semibold">{{ n }}</span>
                <TypeChip :type="byId(schedule[i + 1])?.type ?? 'rest'" />
                <USelect
                    :model-value="String(schedule[i + 1] ?? 0)"
                    :items="dayItems"
                    :aria-label="'Séance du ' + n.toLowerCase()"
                    class="ml-auto w-full sm:w-64"
                    @update:model-value="
                        (v) => setDay(i + 1, v === '0' ? '' : String(v))
                    "
                />
            </div>
        </UCard>
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Séances</h2>
            <UButton icon="i-lucide-plus" size="sm" @click="newOpen = true"
                >Nouvelle séance</UButton
            >
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <UCard
                v-for="w in workouts"
                :key="w.id"
                :ui="{
                    root: 'flex flex-col',
                    body: 'flex flex-1 flex-col gap-3',
                    footer: 'flex gap-2 py-3 sm:py-3',
                }"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <TypeChip :type="w.type" /><span
                                class="text-xs text-muted tabular-nums"
                                >{{ durationLabel(w) }}</span
                            >
                        </div>
                        <h3 class="text-base font-semibold">{{ w.name }}</h3>
                    </div>
                    <span class="text-xs text-muted">{{
                        daysOf(w).length
                            ? daysOf(w).join(', ')
                            : 'Non planifiée'
                    }}</span>
                </div>
                <template v-if="w.type === 'strength'">
                    <div class="hidden flex-wrap gap-1.5 sm:flex">
                        <span
                            v-for="we in w.exercises.slice(0, 6)"
                            :key="we.exercise_id"
                            class="thumb"
                            ><ExerciseImage
                                :exercise="
                                    exerciseById(exercises, we.exercise_id)
                                "
                                :animated="false"
                                pose="end"
                        /></span>
                    </div>
                    <p class="text-xs text-muted">
                        {{
                            w.exercises
                                .map(
                                    (we) =>
                                        exerciseById(exercises, we.exercise_id)
                                            ?.name,
                                )
                                .join(' · ')
                        }}
                    </p>
                </template>
                <div v-else-if="w.type === 'run'" class="hidden sm:block">
                    <RunTimeline :segments="w.segments" />
                </div>
                <p v-else class="text-sm text-muted">{{ w.objective }}</p>
                <template #footer>
                    <UButton
                        :to="`/programme/seances/${w.id}`"
                        icon="i-lucide-pencil"
                        color="neutral"
                        variant="outline"
                        size="sm"
                        >Modifier</UButton
                    >
                    <UButton
                        icon="i-lucide-copy"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        @click="
                            router.post(
                                `/programme/seances/${w.id}/dupliquer`,
                                {},
                                { preserveScroll: true },
                            )
                        "
                        >Dupliquer</UButton
                    >
                    <UButton
                        icon="i-lucide-trash-2"
                        color="error"
                        variant="ghost"
                        size="sm"
                        class="ml-auto"
                        :aria-label="'Supprimer ' + w.name"
                        @click="toDelete = w"
                    />
                </template>
            </UCard>
        </div>
    </section>

    <Sheet
        v-if="toDelete"
        :title="`Supprimer « ${toDelete.name} » ?`"
        @close="toDelete = null"
    >
        <p class="text-muted">
            <template v-if="daysOf(toDelete).length"
                >Elle est planifiée le
                {{
                    Object.entries(schedule)
                        .filter(([, id]) => id === toDelete!.id)
                        .map(([d]) => DAY_NAMES[+d - 1].toLowerCase())
                        .join(', ')
                }}
                : ces jours passeront en repos. </template
            >Les séances déjà réalisées restent dans l’historique.
        </p>
        <template #footer>
            <UButton color="neutral" variant="outline" @click="toDelete = null"
                >Annuler</UButton
            >
            <UButton color="error" @click="destroy">Supprimer</UButton>
        </template>
    </Sheet>
    <Sheet
        v-if="newOpen"
        title="Nouvelle séance"
        description="Type de séance"
        @close="newOpen = false"
    >
        <div class="grid gap-3 sm:grid-cols-3">
            <ULink
                v-for="[t, d] in NEW_TYPES"
                :key="t"
                :to="`/programme/seances/nouvelle?type=${t}`"
                class="flex flex-col items-start gap-2 rounded-lg border border-default p-4 text-left transition hover:border-primary/50 hover:bg-elevated/50"
            >
                <TypeChip :type="t" /><span class="text-xs text-muted">{{
                    d
                }}</span>
            </ULink>
        </div>
    </Sheet>
</template>
