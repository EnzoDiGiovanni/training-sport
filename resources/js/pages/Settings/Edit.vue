<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import Icon from '@/components/Icon.vue';
import { fmt, num } from '@/composables/useFormat';
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
        onError: () =>
            nextTick(() =>
                (
                    formEl.value?.querySelector(
                        '.is-error',
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
    <div class="page-head">
        <div class="stack gap-4">
            <span class="eyebrow">Profil</span>
            <h1 class="title-xl">Paramètres</h1>
        </div>
        <button
            class="btn btn-primary"
            :disabled="form.processing"
            @click="save"
        >
            <Icon name="check" />Enregistrer
        </button>
    </div>
    <form ref="formEl" class="stack gap-20" @submit.prevent="save">
        <section
            v-for="s in SECTIONS"
            :key="s.title"
            class="card card-pad stack gap-16"
        >
            <div class="stack gap-4">
                <h2 class="title-m">{{ s.title }}</h2>
                <p v-if="s.sub" class="faint" style="font-size: 13px">
                    {{ s.sub }}
                </p>
            </div>
            <div :class="'grid-' + s.cols">
                <div v-for="[k, l, u, hint] in s.fields" :key="k" class="field">
                    <label :for="'p-' + k">{{ l }}</label>
                    <div :class="{ 'input-unit': u }">
                        <input
                            :id="'p-' + k"
                            v-model="form[k]"
                            class="input"
                            :class="{
                                num: k !== 'name',
                                'is-error': form.errors[k],
                            }"
                            :inputmode="k === 'name' ? undefined : 'decimal'"
                            :aria-invalid="!!form.errors[k]"
                        /><span v-if="u">{{ u }}</span>
                    </div>
                    <span v-if="form.errors[k]" class="err">{{
                        form.errors[k]
                    }}</span
                    ><span v-else-if="hint" class="hint">{{ hint }}</span>
                </div>
            </div>
            <p
                v-if="s.title === 'Profil' && !isNaN(bmi)"
                class="faint"
                style="font-size: 12.5px"
            >
                IMC indicatif : {{ fmt(bmi) }}. Peu pertinent en recomposition :
                il ne distingue pas masse grasse et masse musculaire.
            </p>
            <div v-if="s.title === 'Objectifs'" class="field">
                <label for="p-goals">Objectifs personnels (un par ligne)</label
                ><textarea
                    id="p-goals"
                    v-model="form.goals"
                    class="input"
                ></textarea>
            </div>
            <template v-if="s.title === 'Matériel de musculation'">
                <div class="stack">
                    <label
                        v-for="[k, l] in TOGGLES"
                        :key="k"
                        class="row"
                        style="
                            justify-content: space-between;
                            padding: 6px 0;
                            cursor: pointer;
                            min-height: 44px;
                        "
                        ><span>{{ l }}</span
                        ><input
                            v-model="form[k]"
                            type="checkbox"
                            style="
                                width: 20px;
                                height: 20px;
                                accent-color: var(--accent);
                            "
                    /></label>
                </div>
                <div class="notice">
                    <Icon name="info" /><span
                        >Charge max utilisable : {{ form.dumbbell_max_kg }} kg
                        par haltère,
                        {{
                            form.dumbbells_combinable
                                ? fmt(num(form.dumbbell_max_kg) * 2, 0) +
                                  ' kg sur un seul haltère combiné'
                                : 'pas de combinaison'
                        }}.</span
                    >
                </div>
            </template>
        </section>
        <div class="row" style="justify-content: flex-end">
            <button class="btn btn-primary btn-lg" :disabled="form.processing">
                <Icon name="check" />Enregistrer les paramètres
            </button>
        </div>
    </form>
</template>
