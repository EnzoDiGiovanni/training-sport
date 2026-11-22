<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { fmt, num } from '@/composables/useFormat';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import type { Profile } from '@/types';

const props = defineProps<{ profile: Profile }>();
const p = props.profile;
const str = (v: number | null) =>
    v == null
        ? ''
        : fmt(v, v % 1 ? (Math.round(v * 10) === v * 10 ? 1 : 2) : 0);
const NUM_KEYS = [
    'height_cm',
    'weight',
    'target_weight_kg',
    'weekly_sessions_goal',
    'weekly_km_goal',
    'steps_min',
    'steps_max',
    'run_level_kmh',
    'treadmill_max_kmh',
    'treadmill_incline_max',
    'dumbbell_max_kg',
    'dumbbell_count',
    'weight_step_kg',
    'default_rest_seconds',
] as const;
type NumKey = (typeof NUM_KEYS)[number];
const form = useForm({
    name: p.name,
    ...(Object.fromEntries(
        NUM_KEYS.map((k) => [k, str(p[k] as number | null)]),
    ) as Record<NumKey, string>),
    goals: (p.goals ?? []).join('\n'),
    dumbbells_combinable: p.dumbbells_combinable,
    has_bench: p.has_bench,
    bench_incline: p.bench_incline,
});
const bmi = computed(
    () => num(form.weight) / Math.pow(num(form.height_cm) / 100, 2),
);
const formEl = ref<HTMLElement>();
useUnsavedGuard(() => form.isDirty && !form.processing);
function save() {
    form.transform((d) => ({
        ...d,
        ...Object.fromEntries(NUM_KEYS.map((k) => [k, num(d[k])])),
        goals: d.goals
            .split('\n')
            .map((x) => x.trim())
            .filter(Boolean),
    })).put('/parametres', {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: () =>
            nextTick(() =>
                (
                    formEl.value?.querySelector(
                        '[aria-invalid="true"]',
                    ) as HTMLElement | null
                )?.focus(),
            ),
    });
}
type F = [NumKey | 'name', string, string, string?];
const SECTIONS: { title: string; sub?: string; cols: number; fields: F[] }[] = [
    {
        title: 'Profil',
        cols: 3,
        fields: [
            ['name', 'Prénom', ''],
            ['height_cm', 'Taille', 'cm'],
            [
                'weight',
                'Poids actuel',
                'kg',
                'Une modification crée une pesée datée d’aujourd’hui.',
            ],
        ],
    },
    {
        title: 'Objectifs',
        cols: 3,
        fields: [
            ['target_weight_kg', 'Poids cible', 'kg'],
            ['weekly_sessions_goal', 'Séances par semaine', ''],
            ['weekly_km_goal', 'Kilomètres par semaine', 'km'],
        ],
    },
    {
        title: 'Activité quotidienne',
        cols: 2,
        fields: [
            ['steps_min', 'Pas par jour, minimum', 'pas'],
            ['steps_max', 'Pas par jour, maximum', 'pas'],
        ],
    },
    {
        title: 'Course et tapis',
        sub: 'Les vitesses du programme et les saisies sont contrôlées par rapport à ces limites.',
        cols: 3,
        fields: [
            ['run_level_kmh', 'Allure actuelle', 'km/h'],
            ['treadmill_max_kmh', 'Vitesse max du tapis', 'km/h'],
            ['treadmill_incline_max', 'Inclinaison max', '%'],
        ],
    },
    {
        title: 'Matériel de musculation',
        cols: 3,
        fields: [
            ['dumbbell_max_kg', 'Charge max par haltère', 'kg'],
            ['dumbbell_count', 'Nombre d’haltères', ''],
            [
                'weight_step_kg',
                'Pas de réglage',
                'kg',
                'Plus petit écart de charge possible',
            ],
        ],
    },
    {
        title: 'Entraînement',
        cols: 3,
        fields: [
            [
                'default_rest_seconds',
                'Repos par défaut',
                's',
                'Appliqué aux nouveaux exercices',
            ],
        ],
    },
];
const TOGGLES: [
    'dumbbells_combinable' | 'has_bench' | 'bench_incline',
    string,
][] = [
    ['dumbbells_combinable', 'Charges combinables en un seul haltère'],
    ['has_bench', 'Banc de musculation'],
    ['bench_incline', 'Banc inclinable'],
];
</script>

<template>
    <Head title="Paramètres" />
    <UPageHeader headline="Profil" title="Paramètres">
        <template #links>
            <UColorModeButton color="neutral" variant="outline" />
            <UButton
                icon="i-lucide-check"
                :loading="form.processing"
                @click="save"
                >Enregistrer</UButton
            >
        </template>
    </UPageHeader>
    <form ref="formEl" class="space-y-6" @submit.prevent="save">
        <UCard v-for="s in SECTIONS" :key="s.title">
            <template #header>
                <h2 class="font-semibold">{{ s.title }}</h2>
                <p v-if="s.sub" class="mt-1 text-sm text-muted">{{ s.sub }}</p>
            </template>
            <div class="space-y-5">
                <div
                    class="grid gap-4"
                    :class="s.cols === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2'"
                >
                    <UFormField
                        v-for="[k, l, u, hint] in s.fields"
                        :key="k"
                        :label="l"
                        :error="form.errors[k]"
                        :help="hint"
                    >
                        <UInput
                            v-model="form[k]"
                            :inputmode="k === 'name' ? undefined : 'decimal'"
                            :class="[
                                'w-full',
                                { 'tabular-nums': k !== 'name' },
                            ]"
                        >
                            <template v-if="u" #trailing
                                ><span class="text-xs text-muted">{{
                                    u
                                }}</span></template
                            >
                        </UInput>
                    </UFormField>
                </div>
                <p
                    v-if="s.title === 'Profil' && !isNaN(bmi)"
                    class="text-xs text-muted"
                >
                    IMC indicatif : {{ fmt(bmi) }}. Peu pertinent en
                    recomposition : il ne distingue pas masse grasse et masse
                    musculaire.
                </p>
                <UFormField
                    v-if="s.title === 'Objectifs'"
                    label="Objectifs personnels"
                    hint="un par ligne"
                >
                    <UTextarea v-model="form.goals" class="w-full" />
                </UFormField>
                <template v-if="s.title === 'Matériel de musculation'">
                    <div class="divide-y divide-default">
                        <USwitch
                            v-for="[k, l] in TOGGLES"
                            :key="k"
                            v-model="form[k]"
                            :label="l"
                            class="flex-row-reverse justify-between py-3"
                        />
                    </div>
                    <UAlert
                        variant="subtle"
                        color="neutral"
                        icon="i-lucide-info"
                        :title="`Charge max utilisable : ${form.dumbbell_max_kg} kg par haltère, ${
                            form.dumbbells_combinable
                                ? fmt(num(form.dumbbell_max_kg) * 2, 0) +
                                  ' kg sur un seul haltère combiné'
                                : 'pas de combinaison'
                        }.`"
                    />
                </template>
            </div>
        </UCard>
        <div
            class="sticky bottom-[calc(4.25rem+env(safe-area-inset-bottom))] z-30 -mx-4 flex items-center justify-end gap-3 border-t border-default bg-default/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:p-0 lg:backdrop-blur-none"
        >
            <span v-if="form.isDirty" class="text-sm text-muted"
                >Modifications non enregistrées</span
            >
            <UButton
                type="submit"
                size="lg"
                icon="i-lucide-check"
                :loading="form.processing"
                >Enregistrer<span class="hidden sm:inline"
                    >&nbsp;les paramètres</span
                ></UButton
            >
        </div>
    </form>
</template>
