<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Chart from '@/components/Chart.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import {
    cap,
    dateLong,
    dateShort,
    fmt,
    fmtInt,
    fmtKg,
    num,
} from '@/composables/useFormat';
import { setsSummary } from '@/lib/domain';
import type { Exercise, ExerciseHistoryPoint, Profile, Session } from '@/types';

type Week = {
    monday: string;
    cur: boolean;
    count: number;
    strength: number;
    km: number;
    seconds: number;
};
type WeightPoint = { id: number; date: string; kg: number; ma: number };
const props = defineProps<{
    weeks: Week[];
    runs: Session[];
    runWorkouts: { id: number; name: string }[];
    exercises: Exercise[];
    histories: Record<number, ExerciseHistoryPoint[]>;
    strengthGoal: number;
    weight: {
        list: WeightPoint[];
        last: WeightPoint | null;
        delta30: number | null;
        ma_delta30: number | null;
        rate: number | null;
        too_fast: boolean;
    };
    profile: Profile;
    today: string;
}>();
const page = usePage();
const initial = new URLSearchParams(page.url.split('?')[1] ?? '').get('onglet');
const tab = ref<'run' | 'strength' | 'weight'>(
    initial === 'poids'
        ? 'weight'
        : initial === 'musculation'
          ? 'strength'
          : 'run',
);

// Course
const runW = ref<number | 'all'>('all');
const runs = computed(() =>
    props.runs.filter(
        (s) => runW.value === 'all' || s.workout_id === runW.value,
    ),
);
const kmWeeks = computed(() =>
    props.weeks.map((w) => ({
        label: dateShort(w.monday),
        value: w.km,
        cur: w.cur,
    })),
);
const avgPts = computed(() =>
    runs.value.map((s) => ({
        label: dateShort(s.date),
        value: s.run!.average_speed_kmh ?? 0,
    })),
);
const distPts = computed(() =>
    runs.value.map((s) => ({
        label: dateShort(s.date),
        value: s.run!.distance_km,
    })),
);
const durPts = computed(() =>
    runs.value.map((s) => ({
        label: dateShort(s.date),
        value: s.duration_seconds / 60,
    })),
);
const records = computed<[string, number, string, number][]>(() => [
    [
        'Plus longue distance',
        Math.max(...runs.value.map((s) => s.run!.distance_km)),
        'km',
        2,
    ],
    [
        'Meilleure vitesse moyenne',
        Math.max(...runs.value.map((s) => s.run!.average_speed_kmh ?? 0)),
        'km/h',
        2,
    ],
    [
        'Plus longue durée',
        Math.max(...runs.value.map((s) => s.duration_seconds)) / 60,
        'min',
        0,
    ],
    [
        'Vitesse max atteinte',
        Math.max(...runs.value.map((s) => s.run!.max_speed_kmh ?? 0)),
        'km/h',
        1,
    ],
]);

// Musculation
const exId = ref<number | null>(props.exercises[0]?.id ?? null);
const exItems = computed(() =>
    props.exercises.map((e) => ({ label: e.name, value: e.id })),
);
const ex = computed(
    () => props.exercises.find((e) => e.id === exId.value) ?? null,
);
const h = computed(() =>
    exId.value ? (props.histories[exId.value] ?? []) : [],
);
const bw = computed(() => ex.value?.load_type === 'bodyweight');
const isTime = computed(() => ex.value?.measurement === 'time');
const best = computed(() =>
    h.value
        .flatMap((x) => x.sets)
        .reduce<{ weight_kg: number; reps: number } | null>(
            (b, s) =>
                !b ||
                s.weight_kg > b.weight_kg ||
                (s.weight_kg === b.weight_kg && s.reps > b.reps)
                    ? s
                    : b,
            null,
        ),
);
const mainPts = computed(() =>
    h.value.map((x) => ({
        label: dateShort(x.date),
        value: bw.value ? x.best_reps : x.max_kg,
    })),
);
const summary = (x: ExerciseHistoryPoint) =>
    setsSummary({
        sets: x.sets.map((s) => ({ ...s, is_completed: true })),
        load_type: ex.value!.load_type,
        measurement: ex.value!.measurement,
    });
const strengthWeeks = computed(() =>
    props.weeks.map((w) => ({
        label: dateShort(w.monday),
        value: w.strength,
        cur: w.cur,
    })),
);

// Poids
const wl = computed(() => props.weight.list);
const wForm = useForm({ measured_on: props.today, weight_kg: '' });
const addWeight = () =>
    wForm
        .transform((d) => ({ ...d, weight_kg: num(d.weight_kg) }))
        .post('/pesees', {
            preserveScroll: true,
            onSuccess: () => wForm.reset('weight_kg'),
        });
const delWeight = (id: number) =>
    router.delete(`/pesees/${id}`, { preserveScroll: true });
const left = computed(() =>
    props.weight.last && props.profile.target_weight_kg
        ? props.weight.last.kg - props.profile.target_weight_kg
        : null,
);
const signed = (v: number | null, d = 1) =>
    v == null ? '–' : (v > 0 ? '+' : '') + fmt(v, d);
</script>

<template>
    <Head title="Progression" />
    <UPageHeader headline="8 dernières semaines" title="Progression">
        <template #links>
            <UTabs
                v-model="tab"
                :items="[
                    {
                        value: 'run',
                        label: 'Course',
                        icon: 'i-lucide-footprints',
                    },
                    {
                        value: 'strength',
                        label: 'Musculation',
                        icon: 'i-lucide-dumbbell',
                    },
                    { value: 'weight', label: 'Poids', icon: 'i-lucide-scale' },
                ]"
                :content="false"
                :ui="{ list: 'w-auto', trigger: 'grow-0' }"
                aria-label="Catégorie"
            />
        </template>
    </UPageHeader>

    <template v-if="tab === 'run'">
        <UCard>
            <div class="chart-head">
                <div>
                    <p class="text-sm text-muted">Kilométrage hebdomadaire</p>
                    <p class="big">{{ fmt(weeks.at(-2)?.km ?? 0) }} km</p>
                </div>
                <p class="text-right text-xs text-muted">
                    semaine dernière<br />objectif
                    {{ fmt(profile.weekly_km_goal, 0) }} km
                </p>
            </div>
            <Chart
                kind="bar"
                :data="kmWeeks"
                unit="km"
                :d="1"
                :goal="profile.weekly_km_goal"
                :h="210"
            />
        </UCard>
        <UTabs
            v-model="runW"
            :items="[
                { value: 'all', label: 'Toutes les courses' },
                ...runWorkouts.map((w) => ({ value: w.id, label: w.name })),
            ]"
            :content="false"
            :ui="{ list: 'w-auto', trigger: 'grow-0' }"
            variant="pill"
            size="sm"
            class="max-w-full overflow-x-auto"
        />
        <div v-if="runs.length" class="grid gap-4 lg:grid-cols-2">
            <UCard>
                <div class="chart-head">
                    <div>
                        <p class="text-sm text-muted">Vitesse moyenne</p>
                        <p class="big">
                            {{ fmt(avgPts.at(-1)!.value, 2) }} km/h
                        </p>
                    </div>
                    <p class="text-right text-xs text-muted">
                        <span
                            class="font-medium tabular-nums"
                            :class="
                                avgPts.at(-1)!.value >= avgPts[0].value
                                    ? 'text-success'
                                    : 'text-warning'
                            "
                            >{{
                                signed(
                                    avgPts.at(-1)!.value - avgPts[0].value,
                                    2,
                                )
                            }}
                            km/h</span
                        ><br />depuis le {{ avgPts[0].label }}
                    </p>
                </div>
                <Chart kind="line" :data="avgPts" unit="km/h" :d="2" />
            </UCard>
            <UCard>
                <div class="chart-head">
                    <div>
                        <p class="text-sm text-muted">Distance par séance</p>
                        <p class="big">
                            {{ fmt(distPts.at(-1)!.value, 2) }} km
                        </p>
                    </div>
                    <p class="text-right text-xs text-muted">
                        {{ runs.length }} courses ·
                        {{ fmt(distPts.reduce((a, x) => a + x.value, 0)) }} km
                        au total
                    </p>
                </div>
                <Chart kind="line" :data="distPts" unit="km" :d="2" />
            </UCard>
            <UCard>
                <div class="chart-head">
                    <div>
                        <p class="text-sm text-muted">Durée par séance</p>
                        <p class="big">
                            {{ fmtInt(durPts.at(-1)!.value) }} min
                        </p>
                    </div>
                    <p class="text-right text-xs text-muted">dernière séance</p>
                </div>
                <Chart kind="line" :data="durPts" unit="min" :d="0" />
            </UCard>
            <UCard>
                <template #header>
                    <h3 class="flex items-center gap-2 font-semibold">
                        <UIcon
                            name="i-lucide-trophy"
                            class="text-warning"
                        />Records
                    </h3>
                </template>
                <dl class="divide-y divide-default">
                    <div
                        v-for="[k, v, u, d] in records"
                        :key="k"
                        class="flex justify-between py-2.5 text-sm"
                    >
                        <dt class="text-muted">{{ k }}</dt>
                        <dd class="font-medium tabular-nums">
                            {{ fmt(v, d) }} {{ u }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-dimmed">
                    Les vitesses comparées entre séances différentes
                    (fondamentale, fractionné) n’ont pas le même sens : filtre
                    par séance pour suivre une progression fiable.
                </p>
            </UCard>
        </div>
        <UEmpty
            v-else
            icon="i-lucide-footprints"
            title="Aucune course pour ce filtre."
        />
    </template>

    <template v-else-if="tab === 'strength'">
        <UEmpty
            v-if="!ex"
            icon="i-lucide-dumbbell"
            title="Aucune séance de musculation enregistrée pour l’instant."
        />
        <template v-else>
            <UCard :ui="{ body: 'flex flex-wrap items-center gap-4' }">
                <span class="thumb thumb-lg"
                    ><ExerciseImage :exercise="ex" :animated="false" pose="end"
                /></span>
                <UFormField label="Exercice" class="min-w-56 flex-1">
                    <USelectMenu
                        :model-value="exId ?? undefined"
                        @update:model-value="(v) => (exId = v)"
                        :items="exItems"
                        value-key="value"
                        class="w-full"
                    />
                </UFormField>
                <dl class="grid min-w-72 flex-1 grid-cols-3 gap-3">
                    <div>
                        <dt class="text-xs text-muted">
                            {{ bw ? 'Meilleure série' : 'Charge actuelle' }}
                        </dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{
                                bw
                                    ? best
                                        ? best.reps + (isTime ? ' s' : '')
                                        : '–'
                                    : fmtKg(h.at(-1)?.max_kg ?? 0) + ' kg'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Progression</dt>
                        <dd
                            class="text-lg font-semibold text-success tabular-nums"
                        >
                            {{
                                bw
                                    ? signed(
                                          (h.at(-1)?.reps ?? 0) -
                                              (h[0]?.reps ?? 0),
                                          0,
                                      ) + (isTime ? ' s' : ' reps')
                                    : signed(
                                          (h.at(-1)?.max_kg ?? 0) -
                                              (h[0]?.max_kg ?? 0),
                                      ) + ' kg'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Séances</dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ h.length }}
                        </dd>
                    </div>
                </dl>
            </UCard>
            <div class="grid gap-4 lg:grid-cols-2">
                <UCard>
                    <div class="chart-head">
                        <div>
                            <p class="text-sm text-muted">
                                {{
                                    bw
                                        ? isTime
                                            ? 'Meilleur maintien'
                                            : 'Meilleure série'
                                        : 'Charge max par séance'
                                }}
                            </p>
                            <p class="big">
                                {{
                                    bw
                                        ? (mainPts.at(-1)?.value ?? 0) +
                                          (isTime ? ' s' : ' reps')
                                        : fmtKg(h.at(-1)?.max_kg ?? 0) + ' kg'
                                }}
                            </p>
                        </div>
                        <p class="text-xs text-muted">
                            {{
                                ex.load_type === 'two_dumbbells'
                                    ? 'par haltère'
                                    : ''
                            }}
                        </p>
                    </div>
                    <Chart
                        kind="line"
                        :data="mainPts"
                        :unit="bw ? (isTime ? 's' : 'reps') : 'kg'"
                        :d="bw ? 0 : 1"
                        step
                    />
                </UCard>
                <UCard>
                    <div class="chart-head">
                        <div>
                            <p class="text-sm text-muted">
                                {{
                                    isTime
                                        ? 'Temps total de maintien'
                                        : 'Répétitions totales'
                                }}
                            </p>
                            <p class="big">
                                {{ h.at(-1)?.reps ?? 0
                                }}{{ isTime ? ' s' : '' }}
                            </p>
                        </div>
                        <p class="text-xs text-muted">par séance</p>
                    </div>
                    <Chart
                        kind="bar"
                        :data="
                            h.map((x) => ({
                                label: dateShort(x.date),
                                value: x.reps,
                            }))
                        "
                        :unit="isTime ? 's' : 'reps'"
                        :d="0"
                    />
                </UCard>
                <UCard v-if="!bw">
                    <div class="chart-head">
                        <div>
                            <p class="text-sm text-muted">Volume par séance</p>
                            <p class="big">
                                {{ fmtInt(h.at(-1)?.volume ?? 0) }} kg
                            </p>
                        </div>
                        <p class="text-xs text-muted">
                            charge × reps × haltères
                        </p>
                    </div>
                    <Chart
                        kind="line"
                        :data="
                            h.map((x) => ({
                                label: dateShort(x.date),
                                value: x.volume,
                            }))
                        "
                        unit="kg"
                        :d="0"
                    />
                </UCard>
                <UCard>
                    <div class="chart-head">
                        <div>
                            <p class="text-sm text-muted">
                                Séances de musculation
                            </p>
                            <p class="big">
                                {{ weeks.at(-2)?.strength ?? 0 }} / sem.
                            </p>
                        </div>
                        <p class="text-xs text-muted">semaine dernière</p>
                    </div>
                    <Chart
                        kind="bar"
                        :data="strengthWeeks"
                        unit="séances"
                        :d="0"
                        :goal="strengthGoal || null"
                    />
                </UCard>
            </div>
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">Historique de l’exercice</h2>
                <UCard :ui="{ body: 'p-0 sm:p-0 divide-y divide-default' }">
                    <div
                        v-for="x in h.slice().reverse().slice(0, 8)"
                        :key="x.session_id"
                        class="grid grid-cols-[90px_1fr_auto] gap-3 px-4 py-3 text-sm tabular-nums"
                    >
                        <span class="text-muted">{{ dateShort(x.date) }}</span
                        ><span>{{ summary(x) }}</span
                        ><span class="text-muted">{{
                            bw ? '' : fmtInt(x.volume) + ' kg'
                        }}</span>
                    </div>
                </UCard>
            </section>
        </template>
    </template>

    <template v-else>
        <UAlert
            v-if="weight.too_fast"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            description="Tu perds plus de 0,75 kg par semaine en moyenne. En recomposition, une perte trop rapide augmente le risque de perdre du muscle : vise un rythme plus progressif."
        />
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <UCard :ui="{ body: 'space-y-1' }">
                <p class="text-sm text-muted">Poids actuel</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ weight.last ? fmt(weight.last.kg) + ' kg' : '–' }}
                </p>
                <p class="text-xs text-muted">
                    {{
                        weight.last
                            ? cap(dateLong(weight.last.date))
                            : 'Aucune pesée'
                    }}
                </p>
            </UCard>
            <UCard :ui="{ body: 'space-y-1' }">
                <p class="text-sm text-muted">Sur 30 jours</p>
                <p
                    class="text-2xl font-semibold tabular-nums"
                    :class="{ 'text-success': (weight.delta30 ?? 0) <= 0 }"
                >
                    {{ signed(weight.delta30) }} kg
                </p>
                <p class="text-xs text-muted">
                    moyenne mobile : {{ signed(weight.ma_delta30) }} kg
                </p>
            </UCard>
            <UCard :ui="{ body: 'space-y-1' }">
                <p class="text-sm text-muted">Rythme moyen</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ fmt(weight.rate, 2) }}
                    <span class="text-sm font-normal text-muted"
                        >kg / sem.</span
                    >
                </p>
                <p class="text-xs text-muted">
                    {{
                        weight.rate == null
                            ? '–'
                            : weight.rate < 0 && weight.rate > -0.75
                              ? 'rythme progressif, compatible recompo'
                              : weight.rate <= -0.75
                                ? 'rapide : risque de perte musculaire'
                                : 'stable ou en hausse'
                    }}
                </p>
            </UCard>
            <UCard :ui="{ body: 'space-y-1' }">
                <p class="text-sm text-muted">Objectif</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ fmt(profile.target_weight_kg) }} kg
                </p>
                <p class="text-xs text-muted">
                    {{
                        left == null
                            ? '–'
                            : left > 0
                              ? 'encore ' + fmt(left) + ' kg'
                              : 'objectif atteint'
                    }}
                </p>
            </UCard>
        </div>
        <UCard>
            <div class="chart-head">
                <div>
                    <p class="text-sm font-semibold">Historique du poids</p>
                    <p class="flex items-center gap-1.5 text-xs text-muted">
                        <span class="inline-block h-0.5 w-3 bg-primary"></span>
                        moyenne mobile 7 jours
                        <span
                            class="ml-3 inline-block size-1.5 rounded-full bg-(--ui-text-muted)"
                        ></span>
                        pesées
                    </p>
                </div>
            </div>
            <Chart
                kind="weight"
                :data="
                    wl.map((x) => ({
                        label: dateShort(x.date),
                        value: x.kg,
                        ma: x.ma,
                    }))
                "
                unit="kg"
                :d="1"
                :h="240"
                :goal="profile.target_weight_kg"
            />
            <p class="mt-3 text-xs text-dimmed">
                Le poids varie naturellement de 0,5 à 1 kg d’un jour à l’autre
                (eau, sel, digestion). Fie-toi à la moyenne mobile. En
                recomposition, le poids peut stagner alors que la masse grasse
                baisse : le tour de taille serait un bon indicateur
                complémentaire.
            </p>
        </UCard>
        <div class="grid items-start gap-4 lg:grid-cols-2">
            <UCard>
                <template #header>
                    <h2 class="font-semibold">Nouvelle pesée</h2>
                </template>
                <form class="space-y-4" @submit.prevent="addWeight">
                    <div class="grid grid-cols-2 gap-4">
                        <UFormField
                            label="Date"
                            :error="wForm.errors.measured_on"
                        >
                            <UInput
                                v-model="wForm.measured_on"
                                type="date"
                                :max="today"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            label="Poids"
                            :error="wForm.errors.weight_kg"
                        >
                            <UInput
                                v-model="wForm.weight_kg"
                                inputmode="decimal"
                                :placeholder="
                                    weight.last ? fmt(weight.last.kg) : ''
                                "
                                class="w-full tabular-nums"
                            >
                                <template #trailing
                                    ><span class="text-xs text-muted"
                                        >kg</span
                                    ></template
                                >
                            </UInput>
                        </UFormField>
                    </div>
                    <UButton
                        type="submit"
                        icon="i-lucide-plus"
                        :loading="wForm.processing"
                        >Enregistrer la pesée</UButton
                    >
                    <p class="text-xs text-dimmed">
                        Conseil : pèse-toi le matin, à jeun, après être passé
                        aux toilettes, 2 à 3 fois par semaine. Une nouvelle
                        pesée le même jour remplace la précédente.
                    </p>
                </form>
            </UCard>
            <UCard :ui="{ body: 'p-0 sm:p-0 divide-y divide-default' }">
                <template #header>
                    <h2 class="font-semibold">Dernières pesées</h2>
                </template>
                <div
                    v-for="x in wl.slice().reverse().slice(0, 6)"
                    :key="x.id"
                    class="flex items-center gap-3 px-4 py-2"
                >
                    <span class="flex-1 text-sm text-muted">{{
                        cap(dateLong(x.date))
                    }}</span
                    ><span class="text-sm tabular-nums"
                        >{{ fmt(x.kg) }} kg</span
                    >
                    <UButton
                        icon="i-lucide-trash-2"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        :aria-label="
                            'Supprimer la pesée du ' + dateShort(x.date)
                        "
                        :disabled="wl.length <= 1"
                        @click="delWeight(x.id)"
                    />
                </div>
                <UEmpty
                    v-if="!wl.length"
                    variant="naked"
                    title="Aucune pesée."
                />
            </UCard>
        </div>
    </template>
</template>
