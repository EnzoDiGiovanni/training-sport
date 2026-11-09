<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Chart from '@/components/Chart.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Icon from '@/components/Icon.vue';
import { cap, dateLong, dateShort, fmt, fmtInt, fmtKg, num } from '@/composables/useFormat';
import { setsSummary } from '@/lib/domain';
import type { Exercise, ExerciseHistoryPoint, Profile, Session } from '@/types';

type Week = { monday: string; cur: boolean; count: number; strength: number; km: number; seconds: number };
type WeightPoint = { id: number; date: string; kg: number; ma: number };
const props = defineProps<{
    weeks: Week[];
    runs: Session[];
    runWorkouts: { id: number; name: string }[];
    exercises: Exercise[];
    histories: Record<number, ExerciseHistoryPoint[]>;
    strengthGoal: number;
    weight: { list: WeightPoint[]; last: WeightPoint | null; delta30: number | null; ma_delta30: number | null; rate: number | null; too_fast: boolean };
    profile: Profile;
    today: string;
}>();
const page = usePage();
const initial = new URLSearchParams(page.url.split('?')[1] ?? '').get('onglet');
const tab = ref<'run' | 'strength' | 'weight'>(initial === 'poids' ? 'weight' : initial === 'musculation' ? 'strength' : 'run');

// Course
const runW = ref<number | 'all'>('all');
const runs = computed(() => props.runs.filter((s) => runW.value === 'all' || s.workout_id === runW.value));
const kmWeeks = computed(() => props.weeks.map((w) => ({ label: dateShort(w.monday), value: w.km, cur: w.cur })));
const avgPts = computed(() => runs.value.map((s) => ({ label: dateShort(s.date), value: s.run!.average_speed_kmh ?? 0 })));
const distPts = computed(() => runs.value.map((s) => ({ label: dateShort(s.date), value: s.run!.distance_km })));
const durPts = computed(() => runs.value.map((s) => ({ label: dateShort(s.date), value: s.duration_seconds / 60 })));
const records = computed<[string, number, string, number][]>(() => [
    ['Plus longue distance', Math.max(...runs.value.map((s) => s.run!.distance_km)), 'km', 2],
    ['Meilleure vitesse moyenne', Math.max(...runs.value.map((s) => s.run!.average_speed_kmh ?? 0)), 'km/h', 2],
    ['Plus longue durée', Math.max(...runs.value.map((s) => s.duration_seconds)) / 60, 'min', 0],
    ['Vitesse max atteinte', Math.max(...runs.value.map((s) => s.run!.max_speed_kmh ?? 0)), 'km/h', 1],
]);

// Musculation
const exId = ref<number | null>(props.exercises[0]?.id ?? null);
const ex = computed(() => props.exercises.find((e) => e.id === exId.value) ?? null);
const h = computed(() => (exId.value ? props.histories[exId.value] ?? [] : []));
const bw = computed(() => ex.value?.load_type === 'bodyweight');
const isTime = computed(() => ex.value?.measurement === 'time');
const best = computed(() => h.value.flatMap((x) => x.sets).reduce<{ weight_kg: number; reps: number } | null>((b, s) => (!b || s.weight_kg > b.weight_kg || (s.weight_kg === b.weight_kg && s.reps > b.reps) ? s : b), null));
const mainPts = computed(() => h.value.map((x) => ({ label: dateShort(x.date), value: bw.value ? x.best_reps : x.max_kg })));
const summary = (x: ExerciseHistoryPoint) => setsSummary({ sets: x.sets.map((s) => ({ ...s, is_completed: true })), load_type: ex.value!.load_type, measurement: ex.value!.measurement });
const strengthWeeks = computed(() => props.weeks.map((w) => ({ label: dateShort(w.monday), value: w.strength, cur: w.cur })));

// Poids
const wl = computed(() => props.weight.list);
const wForm = useForm({ measured_on: props.today, weight_kg: '' });
const addWeight = () => wForm.transform((d) => ({ ...d, weight_kg: num(d.weight_kg) })).post('/pesees', { preserveScroll: true, onSuccess: () => wForm.reset('weight_kg') });
const delWeight = (id: number) => router.delete(`/pesees/${id}`, { preserveScroll: true });
const left = computed(() => (props.weight.last && props.profile.target_weight_kg ? props.weight.last.kg - props.profile.target_weight_kg : null));
const signed = (v: number | null, d = 1) => (v == null ? '–' : (v > 0 ? '+' : '') + fmt(v, d));
</script>

<template>
    <Head title="Progression" />
    <div class="page-head">
        <div class="stack gap-4"><span class="eyebrow">8 dernières semaines</span><h1 class="title-xl">Progression</h1></div>
        <div class="seg" role="group" aria-label="Catégorie">
            <button :aria-pressed="tab === 'run'" @click="tab = 'run'">Course</button>
            <button :aria-pressed="tab === 'strength'" @click="tab = 'strength'">Musculation</button>
            <button :aria-pressed="tab === 'weight'" @click="tab = 'weight'">Poids</button>
        </div>
    </div>

    <template v-if="tab === 'run'">
        <div class="card card-pad">
            <div class="chart-head"><div class="stack"><span class="label">Kilométrage hebdomadaire</span><span class="big num">{{ fmt(weeks.at(-2)?.km ?? 0) }} km</span></div><span class="faint" style="font-size: 12.5px; text-align: right">semaine dernière<br />objectif {{ fmt(profile.weekly_km_goal, 0) }} km</span></div>
            <Chart kind="bar" :data="kmWeeks" unit="km" :d="1" :goal="profile.weekly_km_goal" :h="210" />
        </div>
        <div class="filters">
            <button class="fchip" :aria-pressed="runW === 'all'" @click="runW = 'all'">Toutes les courses</button>
            <button v-for="w in runWorkouts" :key="w.id" class="fchip" :aria-pressed="runW === w.id" @click="runW = w.id">{{ w.name }}</button>
        </div>
        <div v-if="runs.length" class="charts-grid">
            <div class="card card-pad">
                <div class="chart-head"><div class="stack"><span class="label">Vitesse moyenne</span><span class="big num">{{ fmt(avgPts.at(-1)!.value, 2) }} km/h</span></div><span class="faint" style="font-size: 12.5px; text-align: right"><span class="num" :class="avgPts.at(-1)!.value >= avgPts[0].value ? 'delta-good' : 'delta-bad'">{{ signed(avgPts.at(-1)!.value - avgPts[0].value, 2) }} km/h</span><br />depuis le {{ avgPts[0].label }}</span></div>
                <Chart kind="line" :data="avgPts" unit="km/h" :d="2" />
            </div>
            <div class="card card-pad">
                <div class="chart-head"><div class="stack"><span class="label">Distance par séance</span><span class="big num">{{ fmt(distPts.at(-1)!.value, 2) }} km</span></div><span class="faint" style="font-size: 12.5px; text-align: right">{{ runs.length }} courses · {{ fmt(distPts.reduce((a, x) => a + x.value, 0)) }} km au total</span></div>
                <Chart kind="line" :data="distPts" unit="km" :d="2" />
            </div>
            <div class="card card-pad">
                <div class="chart-head"><div class="stack"><span class="label">Durée par séance</span><span class="big num">{{ fmtInt(durPts.at(-1)!.value) }} min</span></div><span class="faint" style="font-size: 12.5px; text-align: right">dernière séance</span></div>
                <Chart kind="line" :data="durPts" unit="min" :d="0" />
            </div>
            <div class="card card-pad stack gap-12">
                <span class="label">Records</span>
                <div v-for="[k, v, u, d] in records" :key="k" class="row" style="justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border)"><span class="muted">{{ k }}</span><span class="num" style="font-weight: 500">{{ fmt(v, d) }} {{ u }}</span></div>
                <p class="faint" style="font-size: 12px">Les vitesses comparées entre séances différentes (fondamentale, fractionné) n’ont pas le même sens : filtre par séance pour suivre une progression fiable.</p>
            </div>
        </div>
        <div v-else class="card empty">Aucune course pour ce filtre.</div>
    </template>

    <template v-else-if="tab === 'strength'">
        <div v-if="!ex" class="card empty">Aucune séance de musculation enregistrée pour l’instant.</div>
        <template v-else>
            <div class="card card-pad row gap-16 wrapflex">
                <span class="thumb thumb-lg"><ExerciseImage :exercise="ex" :animated="false" pose="end" /></span>
                <div class="field grow" style="min-width: 220px"><label for="statsEx">Exercice</label><select id="statsEx" v-model="exId" class="input"><option v-for="e in exercises" :key="e.id" :value="e.id">{{ e.name }}</option></select></div>
                <div class="grid-3 grow" style="min-width: 280px">
                    <div class="meta-item"><span class="k">{{ bw ? 'Meilleure série' : 'Charge actuelle' }}</span><span class="v num">{{ bw ? (best ? best.reps + (isTime ? ' s' : '') : '–') : fmtKg(h.at(-1)?.max_kg ?? 0) + ' kg' }}</span></div>
                    <div class="meta-item"><span class="k">Progression</span><span class="v num delta-good">{{ bw ? signed((h.at(-1)?.reps ?? 0) - (h[0]?.reps ?? 0), 0) + (isTime ? ' s' : ' reps') : signed((h.at(-1)?.max_kg ?? 0) - (h[0]?.max_kg ?? 0)) + ' kg' }}</span></div>
                    <div class="meta-item"><span class="k">Séances</span><span class="v num">{{ h.length }}</span></div>
                </div>
            </div>
            <div class="charts-grid">
                <div class="card card-pad">
                    <div class="chart-head"><div class="stack"><span class="label">{{ bw ? (isTime ? 'Meilleur maintien' : 'Meilleure série') : 'Charge max par séance' }}</span><span class="big num">{{ bw ? (mainPts.at(-1)?.value ?? 0) + (isTime ? ' s' : ' reps') : fmtKg(h.at(-1)?.max_kg ?? 0) + ' kg' }}</span></div><span class="faint" style="font-size: 12.5px">{{ ex.load_type === 'two_dumbbells' ? 'par haltère' : '' }}</span></div>
                    <Chart kind="line" :data="mainPts" :unit="bw ? (isTime ? 's' : 'reps') : 'kg'" :d="bw ? 0 : 1" step />
                </div>
                <div class="card card-pad">
                    <div class="chart-head"><div class="stack"><span class="label">{{ isTime ? 'Temps total de maintien' : 'Répétitions totales' }}</span><span class="big num">{{ h.at(-1)?.reps ?? 0 }}{{ isTime ? ' s' : '' }}</span></div><span class="faint" style="font-size: 12.5px">par séance</span></div>
                    <Chart kind="bar" :data="h.map((x) => ({ label: dateShort(x.date), value: x.reps }))" :unit="isTime ? 's' : 'reps'" :d="0" />
                </div>
                <div v-if="!bw" class="card card-pad">
                    <div class="chart-head"><div class="stack"><span class="label">Volume par séance</span><span class="big num">{{ fmtInt(h.at(-1)?.volume ?? 0) }} kg</span></div><span class="faint" style="font-size: 12.5px">charge × reps × haltères</span></div>
                    <Chart kind="line" :data="h.map((x) => ({ label: dateShort(x.date), value: x.volume }))" unit="kg" :d="0" />
                </div>
                <div class="card card-pad">
                    <div class="chart-head"><div class="stack"><span class="label">Séances de musculation</span><span class="big num">{{ weeks.at(-2)?.strength ?? 0 }} / sem.</span></div><span class="faint" style="font-size: 12.5px">semaine dernière</span></div>
                    <Chart kind="bar" :data="strengthWeeks" unit="séances" :d="0" :goal="strengthGoal || null" />
                </div>
            </div>
            <section class="section">
                <div class="section-head"><h2>Historique de l’exercice</h2></div>
                <div class="card">
                    <div v-for="x in h.slice().reverse().slice(0, 8)" :key="x.session_id" class="h-row" style="grid-template-columns: 90px 1fr auto">
                        <span class="muted num" style="font-size: 13px">{{ dateShort(x.date) }}</span><span class="num">{{ summary(x) }}</span><span class="faint num" style="font-size: 13px">{{ bw ? '' : fmtInt(x.volume) + ' kg' }}</span>
                    </div>
                </div>
            </section>
        </template>
    </template>

    <template v-else>
        <div v-if="weight.too_fast" class="notice warn"><Icon name="warn" /><span>Tu perds plus de 0,75 kg par semaine en moyenne. En recomposition, une perte trop rapide augmente le risque de perdre du muscle : vise un rythme plus progressif.</span></div>
        <div class="grid-4">
            <div class="card stat"><span class="k">Poids actuel</span><span class="v num" style="font-size: 22px">{{ weight.last ? fmt(weight.last.kg) + ' kg' : '–' }}</span><span class="d">{{ weight.last ? cap(dateLong(weight.last.date)) : 'Aucune pesée' }}</span></div>
            <div class="card stat"><span class="k">Sur 30 jours</span><span class="v num" :class="{ 'delta-good': (weight.delta30 ?? 0) <= 0 }" style="font-size: 22px">{{ signed(weight.delta30) }} kg</span><span class="d">moyenne mobile : {{ signed(weight.ma_delta30) }} kg</span></div>
            <div class="card stat"><span class="k">Rythme moyen</span><span class="v num" style="font-size: 22px">{{ fmt(weight.rate, 2) }} kg / sem.</span><span class="d">{{ weight.rate == null ? '–' : weight.rate < 0 && weight.rate > -0.75 ? 'rythme progressif, compatible recompo' : weight.rate <= -0.75 ? 'rapide : risque de perte musculaire' : 'stable ou en hausse' }}</span></div>
            <div class="card stat"><span class="k">Objectif</span><span class="v num" style="font-size: 22px">{{ fmt(profile.target_weight_kg) }} kg</span><span class="d">{{ left == null ? '–' : left > 0 ? 'encore ' + fmt(left) + ' kg' : 'objectif atteint' }}</span></div>
        </div>
        <div class="card card-pad">
            <div class="chart-head">
                <div class="stack"><span class="label">Historique du poids</span><span class="faint" style="font-size: 12.5px"><span style="display: inline-block; width: 10px; height: 2px; background: var(--accent); vertical-align: middle; margin-right: 6px"></span>moyenne mobile 7 jours <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--muted); vertical-align: middle; margin: 0 6px 0 14px"></span>pesées</span></div>
            </div>
            <Chart kind="weight" :data="wl.map((x) => ({ label: dateShort(x.date), value: x.kg, ma: x.ma }))" unit="kg" :d="1" :h="240" :goal="profile.target_weight_kg" />
            <p class="faint" style="font-size: 12px; margin-top: 10px">Le poids varie naturellement de 0,5 à 1 kg d’un jour à l’autre (eau, sel, digestion). Fie-toi à la moyenne mobile. En recomposition, le poids peut stagner alors que la masse grasse baisse : le tour de taille serait un bon indicateur complémentaire.</p>
        </div>
        <div class="grid-2" style="align-items: start">
            <form class="card card-pad stack gap-12" @submit.prevent="addWeight">
                <h2 class="title-m">Nouvelle pesée</h2>
                <div class="grid-2">
                    <div class="field"><label for="wDate">Date</label><input id="wDate" v-model="wForm.measured_on" type="date" class="input" :max="today" /><span v-if="wForm.errors.measured_on" class="err">{{ wForm.errors.measured_on }}</span></div>
                    <div class="field"><label for="wKg">Poids</label><div class="input-unit"><input id="wKg" v-model="wForm.weight_kg" class="input num" :class="{ 'is-error': wForm.errors.weight_kg }" inputmode="decimal" :placeholder="weight.last ? fmt(weight.last.kg) : ''" /><span>kg</span></div><span v-if="wForm.errors.weight_kg" class="err">{{ wForm.errors.weight_kg }}</span></div>
                </div>
                <button class="btn btn-primary" :disabled="wForm.processing"><Icon name="plus" />Enregistrer la pesée</button>
                <p class="faint" style="font-size: 12px">Conseil : pèse-toi le matin, à jeun, après être passé aux toilettes, 2 à 3 fois par semaine. Une nouvelle pesée le même jour remplace la précédente.</p>
            </form>
            <div class="card">
                <div style="padding: 14px 18px 6px" class="label">Dernières pesées</div>
                <div v-for="x in wl.slice().reverse().slice(0, 6)" :key="x.id" class="row" style="padding: 10px 18px; border-top: 1px solid var(--border)">
                    <span class="grow muted">{{ cap(dateLong(x.date)) }}</span><span class="num">{{ fmt(x.kg) }} kg</span>
                    <button class="btn btn-ghost btn-icon" :aria-label="'Supprimer la pesée du ' + dateShort(x.date)" :disabled="wl.length <= 1" @click="delWeight(x.id)"><Icon name="trash" /></button>
                </div>
                <p v-if="!wl.length" class="empty">Aucune pesée.</p>
            </div>
        </div>
    </template>
</template>
