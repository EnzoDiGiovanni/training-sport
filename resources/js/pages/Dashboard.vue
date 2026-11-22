<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExerciseIllustration from '@/components/ExerciseIllustration.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import HistoryRow from '@/components/HistoryRow.vue';
import Icon from '@/components/Icon.vue';
import RecoverySheet from '@/components/RecoverySheet.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import SessionSheet from '@/components/SessionSheet.vue';
import StartConflictSheet from '@/components/StartConflictSheet.vue';
import TypeChip from '@/components/TypeChip.vue';
import {
    cap,
    DAY_NAMES,
    DAY_SHORT,
    dateLong,
    dateShort,
    fmt,
    fmtDur,
    parseIso,
    weekNumber,
} from '@/composables/useFormat';
import {
    durationLabel,
    estRunKm,
    exerciseById,
    kgLabel,
    KIND_COLOR,
    repsLabel,
    speedLabel,
    totalSets,
    TYPE_ICON,
    TYPE_LABEL,
} from '@/lib/domain';
import { useStartWorkout } from '@/lib/start';
import type { Exercise, Profile, Session, Workout } from '@/types';

type Day = {
    day: number;
    date: string;
    status: string;
    workout_id: number | null;
    session_id: number | null;
};
type WeekTotals = { count: number; km: number; seconds: number };
const props = defineProps<{
    today: string;
    week: Day[];
    workouts: Workout[];
    exercises: Exercise[];
    profile: Profile;
    stats: {
        week: WeekTotals;
        prev: WeekTotals;
        weights: number[];
        delta30: number | null;
    };
    recent: Session[];
    weekSessions: Session[];
}>();

const todayDate = parseIso(props.today);
const todayIdx = props.week.findIndex((d) => d.date === props.today);
const sel = ref(todayIdx);
const day = computed(() => props.week[sel.value]);
const w = computed(
    () => props.workouts.find((x) => x.id === day.value.workout_id) ?? null,
);
const isToday = computed(() => sel.value === todayIdx);
const dayLabel = computed(() => {
    if (isToday.value) return 'Aujourd’hui';
    const d = parseIso(day.value.date);
    return d < todayDate
        ? DAY_NAMES[sel.value] + ' dernier'
        : 'Aperçu · ' + DAY_NAMES[sel.value] + ' ' + dateShort(d);
});
const planned = computed(
    () =>
        props.week.filter((d) =>
            ['run', 'strength'].includes(
                props.workouts.find((x) => x.id === d.workout_id)?.type ?? '',
            ),
        ).length || 1,
);
const ex = (id: number) => exerciseById(props.exercises, id);
const RING_ICON: Record<string, string> = {
    done: 'check',
    missed: 'x',
    rest: 'moon',
};
const ringIcon = (d: Day) =>
    RING_ICON[d.status] ??
    TYPE_ICON[
        props.workouts.find((x) => x.id === d.workout_id)?.type ?? 'rest'
    ];
const shortName = (d: Day) => {
    const x = props.workouts.find((y) => y.id === d.workout_id);
    return x ? x.name.replace('Musculation', 'Muscu') : 'Repos';
};

const lastKg = computed(
    () => props.stats.weights.at(-1) ?? props.profile.weight,
);
const spark = computed(() => {
    const vals = props.stats.weights;
    if (vals.length < 2) return null;
    const W = 120,
        H = 26,
        mn = Math.min(...vals) - 0.2,
        mx = Math.max(...vals) + 0.2;
    const pts = vals.map((v, i) => [
        (i / (vals.length - 1)) * W,
        H - ((v - mn) / (mx - mn)) * H,
    ]);
    return {
        d: pts
            .map(
                (p, i) =>
                    (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1),
            )
            .join(' '),
        e: pts[pts.length - 1],
    };
});

const { start, conflict, abandonAndStart } = useStartWorkout();
const shownEx = ref<Exercise | null>(null);
const openSession = ref<Session | null>(null);
const recovery = ref(false);
// Prochaine séance après le jour affiché (la semaine type se répète chaque semaine).
const nextUp = computed(() => {
    for (let k = 1; k <= 7; k++) {
        const i = (sel.value + k) % 7;
        const nw = props.workouts.find(
            (x) => x.id === props.week[i].workout_id,
        );
        if (nw)
            return {
                w: nw,
                when: isToday.value && k === 1 ? 'Demain' : DAY_NAMES[i],
            };
    }
    return null;
});
const doneSession = computed(
    () => props.weekSessions.find((s) => s.id === day.value.session_id) ?? null,
);
function openDone() {
    if (doneSession.value) openSession.value = doneSession.value;
}
</script>

<template>
    <Head title="Accueil" />
    <UPageHeader
        :headline="`${cap(dateLong(todayDate))} ${todayDate.getFullYear()} · semaine ${weekNumber(todayDate)}`"
        :title="`Bonjour ${profile.name}`"
    />

    <!-- Carte du jour -->
    <UCard v-if="!w" :ui="{ body: 'space-y-4' }">
        <div class="flex items-start gap-4">
            <span
                class="grid size-11 flex-none place-items-center rounded-full bg-elevated text-muted"
                ><UIcon name="i-lucide-moon" class="size-5"
            /></span>
            <div class="flex flex-1 flex-col gap-1">
                <span class="text-sm font-medium text-muted">{{
                    dayLabel
                }}</span>
                <h2 class="text-xl font-semibold">Journée de récupération</h2>
                <p class="max-w-prose text-muted">
                    Profite-en pour recharger les batteries. Le repos fait
                    partie du programme : c’est pendant la récupération que le
                    muscle se construit.
                </p>
            </div>
        </div>
        <div
            v-if="nextUp"
            class="flex flex-wrap items-center gap-3 rounded-lg bg-elevated/60 p-4"
        >
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <span class="text-xs text-muted"
                    >Prochaine séance · {{ nextUp.when }}</span
                >
                <span class="flex flex-wrap items-center gap-2 font-semibold"
                    >{{ nextUp.w.name }}<TypeChip :type="nextUp.w.type"
                /></span>
                <span class="text-sm text-muted tabular-nums">{{
                    durationLabel(nextUp.w)
                }}</span>
            </div>
            <UButton
                :to="`/seances/${nextUp.w.id}`"
                color="neutral"
                variant="outline"
                trailing-icon="i-lucide-chevron-right"
                >Voir le détail</UButton
            >
        </div>
    </UCard>
    <UCard
        v-else
        :ui="{
            body: 'p-0 sm:p-0 grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] divide-y lg:divide-y-0 lg:divide-x divide-default',
        }"
    >
        <div class="flex flex-col gap-5 p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-muted">{{
                    dayLabel
                }}</span
                ><TypeChip :type="w.type" />
            </div>
            <div class="space-y-2">
                <h2 class="text-2xl font-semibold text-highlighted">
                    {{ w.name }}
                </h2>
                <p class="max-w-prose text-muted">{{ w.objective }}</p>
            </div>
            <dl class="flex flex-wrap gap-x-8 gap-y-3">
                <div>
                    <dt class="text-xs text-muted">Durée estimée</dt>
                    <dd class="font-semibold tabular-nums">
                        {{ durationLabel(w) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Type</dt>
                    <dd class="font-semibold">{{ TYPE_LABEL[w.type] }}</dd>
                </div>
                <div v-if="w.type === 'run'">
                    <dt class="text-xs text-muted">Distance estimée</dt>
                    <dd class="font-semibold tabular-nums">
                        ≈ {{ fmt(estRunKm(w.segments)) }} km
                    </dd>
                </div>
                <div v-if="w.type === 'strength'">
                    <dt class="text-xs text-muted">Exercices</dt>
                    <dd class="font-semibold tabular-nums">
                        {{ w.exercises.length }} ·
                        {{ totalSets(w.exercises) }} séries
                    </dd>
                </div>
            </dl>
            <div v-if="w.type === 'run'" class="illo max-w-xs">
                <ExerciseIllustration
                    illustration-key="run"
                    label="Course sur tapis"
                />
            </div>
            <div class="mt-auto flex flex-wrap gap-2">
                <UButton
                    v-if="day.session_id"
                    size="lg"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-check"
                    @click="openDone"
                    >Séance terminée · voir le détail</UButton
                >
                <UButton
                    v-else-if="w.type === 'recovery'"
                    size="lg"
                    icon="i-lucide-plus"
                    @click="recovery = true"
                    >Enregistrer une activité</UButton
                >
                <UButton
                    v-else
                    size="lg"
                    icon="i-lucide-play"
                    @click="start(w)"
                    >{{
                        isToday
                            ? 'Commencer ma séance'
                            : 'Commencer cette séance'
                    }}</UButton
                >
                <UButton
                    v-if="!day.session_id && w.type !== 'recovery'"
                    :to="`/seances/${w.id}`"
                    size="lg"
                    color="neutral"
                    variant="ghost"
                    >Voir le détail</UButton
                >
            </div>
        </div>
        <div class="flex flex-col gap-4 bg-elevated/40 p-5 sm:p-6">
            <template v-if="w.type === 'strength'">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold">Au programme</span
                    ><ULink
                        :to="`/seances/${w.id}`"
                        class="text-sm text-primary"
                        >Détail</ULink
                    >
                </div>
                <div class="-mx-2 flex flex-col">
                    <button
                        v-for="we in w.exercises"
                        :key="we.exercise_id"
                        class="flex items-center gap-3 rounded-md p-2 text-left transition-colors hover:bg-elevated"
                        @click="shownEx = ex(we.exercise_id) ?? null"
                    >
                        <span class="thumb"
                            ><ExerciseImage
                                :exercise="ex(we.exercise_id)"
                                :animated="false"
                                pose="end"
                        /></span>
                        <span class="flex min-w-0 flex-1 flex-col"
                            ><span class="truncate text-sm font-medium">{{
                                ex(we.exercise_id)?.name
                            }}</span
                            ><span class="text-xs text-muted tabular-nums"
                                >{{ we.sets }} ×
                                {{
                                    repsLabel(
                                        we.reps_min,
                                        we.reps_max,
                                        ex(we.exercise_id)!,
                                    )
                                }}
                                ·
                                {{
                                    kgLabel(
                                        we.target_weight_kg,
                                        ex(we.exercise_id)!,
                                    )
                                }}</span
                            ></span
                        >
                    </button>
                </div>
            </template>
            <template v-else-if="w.type === 'run'">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold">Déroulé</span
                    ><ULink
                        :to="`/seances/${w.id}`"
                        class="text-sm text-primary"
                        >Détail</ULink
                    >
                </div>
                <RunTimeline :segments="w.segments" />
                <div class="flex flex-col divide-y divide-default">
                    <template v-for="(s, i) in w.segments" :key="i">
                        <div
                            v-if="s.kind === 'repeat'"
                            class="flex items-center gap-3 py-2.5 text-sm"
                        >
                            <span
                                class="h-8 w-1 flex-none rounded-full"
                                style="background: var(--accent)"
                            ></span
                            ><span class="flex-1"
                                ><b class="font-medium"
                                    >{{ s.repeat_count }} cycles</b
                                ><br /><span class="text-xs text-muted">{{
                                    s.children
                                        .map(
                                            (x) =>
                                                x.duration_min +
                                                ' min à ' +
                                                speedLabel(x),
                                        )
                                        .join(' + ')
                                }}</span></span
                            ><span class="text-muted tabular-nums"
                                >{{
                                    s.repeat_count *
                                    s.children.reduce(
                                        (a, x) => a + x.duration_min,
                                        0,
                                    )
                                }}
                                min</span
                            >
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-3 py-2.5 text-sm"
                        >
                            <span
                                class="h-8 w-1 flex-none rounded-full"
                                :style="{ background: KIND_COLOR[s.kind] }"
                            ></span
                            ><span class="flex-1"
                                ><b class="font-medium">{{ s.label }}</b
                                ><br /><span
                                    class="text-xs text-muted tabular-nums"
                                    >{{ speedLabel(s)
                                    }}{{
                                        s.incline_pct
                                            ? ' · ' +
                                              fmt(s.incline_pct, 0) +
                                              ' %'
                                            : ''
                                    }}</span
                                ></span
                            ><span class="text-muted tabular-nums"
                                >{{ s.duration_min }} min</span
                            >
                        </div>
                    </template>
                </div>
            </template>
            <template v-else>
                <div class="illo">
                    <ExerciseIllustration
                        illustration-key="walk"
                        label="Marche"
                    />
                </div>
                <p class="text-sm text-muted">
                    Marche tranquille, mobilité ou étirements légers. Aucune
                    séance obligatoire.
                </p>
            </template>
        </div>
    </UCard>

    <section class="space-y-3">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-semibold">Cette semaine</h2>
            <span class="text-sm text-muted tabular-nums"
                >{{ stats.week.count }} / {{ planned }} séances réalisées</span
            >
        </div>
        <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
            <button
                v-for="(d, i) in week"
                :key="d.date"
                class="flex flex-col items-center gap-1.5 rounded-lg border px-1 py-2.5 text-center transition-colors"
                :class="
                    i === sel
                        ? 'border-primary bg-primary/5'
                        : 'border-default hover:bg-elevated/60'
                "
                :aria-pressed="i === sel"
                :aria-label="DAY_NAMES[i] + ' : ' + shortName(d)"
                @click="sel = i"
            >
                <span
                    class="text-[11px] font-medium uppercase"
                    :class="i === todayIdx ? 'text-primary' : 'text-muted'"
                    >{{ DAY_SHORT[i] }}</span
                ><span
                    class="grid size-8 place-items-center rounded-full"
                    :class="{
                        'bg-primary text-inverted': d.status === 'done',
                        'bg-error/10 text-error': d.status === 'missed',
                        'bg-elevated text-muted':
                            d.status !== 'done' && d.status !== 'missed',
                    }"
                    ><Icon :name="ringIcon(d)" :size="15" /></span
                ><span class="text-sm font-semibold">{{
                    parseIso(d.date).getDate()
                }}</span
                ><span
                    class="hidden w-full truncate text-[11px] text-muted sm:block"
                    >{{ shortName(d) }}</span
                >
            </button>
        </div>
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Statistiques rapides</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UCard :ui="{ body: 'space-y-2' }">
                <p class="text-sm text-muted">Séances</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ stats.week.count
                    }}<span class="ml-1 text-sm font-normal text-muted"
                        >/ {{ planned }}</span
                    >
                </p>
                <UProgress
                    :model-value="
                        Math.min(100, (stats.week.count / planned) * 100)
                    "
                    size="sm"
                />
                <p class="text-xs text-muted">
                    Semaine dernière : {{ stats.prev.count }}/{{ planned }}
                </p>
            </UCard>
            <UCard :ui="{ body: 'space-y-2' }">
                <p class="text-sm text-muted">Kilomètres</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ fmt(stats.week.km)
                    }}<span class="ml-1 text-sm font-normal text-muted"
                        >km</span
                    >
                </p>
                <UProgress
                    :model-value="
                        Math.min(
                            100,
                            (stats.week.km / profile.weekly_km_goal) * 100,
                        )
                    "
                    size="sm"
                    color="info"
                />
                <p class="text-xs text-muted">
                    Objectif {{ fmt(profile.weekly_km_goal, 0) }} km · sem.
                    dernière {{ fmt(stats.prev.km) }} km
                </p>
            </UCard>
            <UCard :ui="{ body: 'space-y-2' }">
                <p class="text-sm text-muted">Temps d’entraînement</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{
                        stats.week.seconds
                            ? fmtDur(stats.week.seconds).replace(' min', '')
                            : '0'
                    }}<span class="ml-1 text-sm font-normal text-muted">{{
                        stats.week.seconds >= 3600 ? '' : 'min'
                    }}</span>
                </p>
                <p class="text-xs text-muted">
                    Semaine dernière : {{ fmtDur(stats.prev.seconds) }}
                </p>
            </UCard>
            <UCard
                as="a"
                href="/progression?onglet=poids"
                class="transition hover:shadow-md hover:ring-primary/40"
                :ui="{ body: 'space-y-2' }"
                @click.prevent="router.visit('/progression?onglet=poids')"
            >
                <p class="text-sm text-muted">Poids</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ fmt(lastKg)
                    }}<span class="ml-1 text-sm font-normal text-muted"
                        >kg</span
                    >
                </p>
                <svg
                    v-if="spark"
                    viewBox="-2 -3 124 32"
                    class="h-6 w-full"
                    aria-hidden="true"
                >
                    <path
                        :d="spark.d"
                        fill="none"
                        stroke="var(--accent)"
                        stroke-width="1.6"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                        vector-effect="non-scaling-stroke"
                    />
                    <circle
                        :cx="spark.e[0]"
                        :cy="spark.e[1]"
                        r="2.6"
                        fill="var(--accent)"
                    />
                </svg>
                <p class="text-xs text-muted">
                    <template v-if="stats.delta30 != null"
                        ><span
                            class="font-medium tabular-nums"
                            :class="
                                stats.delta30 <= 0
                                    ? 'text-success'
                                    : 'text-warning'
                            "
                            >{{ stats.delta30 > 0 ? '+' : ''
                            }}{{ fmt(stats.delta30) }} kg</span
                        >
                        sur 30 jours · </template
                    >cible {{ fmt(profile.target_weight_kg, 0) }} kg
                </p>
            </UCard>
        </div>
    </section>

    <section class="space-y-3">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-semibold">Dernières séances</h2>
            <ULink to="/entrainements" class="text-sm text-primary"
                >Tout voir</ULink
            >
        </div>
        <UCard :ui="{ body: 'p-0 sm:p-0 divide-y divide-default' }">
            <HistoryRow
                v-for="s in recent"
                :key="s.id"
                :s="s"
                @open="openSession = s"
            />
            <UEmpty
                v-if="!recent.length"
                variant="naked"
                icon="i-lucide-calendar"
                title="Aucune séance enregistrée pour l’instant."
            />
        </UCard>
    </section>

    <ExerciseSheet v-if="shownEx" :exercise="shownEx" @close="shownEx = null" />
    <SessionSheet
        v-if="openSession"
        :session="openSession"
        :exercises="exercises"
        :profile="profile"
        @close="openSession = null"
    />
    <RecoverySheet
        v-if="recovery"
        :date="day.date"
        :workout-id="w?.id ?? null"
        @close="recovery = false"
    />
    <StartConflictSheet
        v-if="conflict"
        :workout="conflict"
        @close="conflict = null"
        @abandon="abandonAndStart"
    />
</template>
