<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
import { cap, DAY_NAMES, DAY_SHORT, dateLong, dateShort, fmt, fmtDur, parseIso, weekNumber } from '@/composables/useFormat';
import { durationLabel, estRunKm, exerciseById, kgLabel, KIND_COLOR, repsLabel, speedLabel, totalSets, TYPE_ICON, TYPE_LABEL } from '@/lib/domain';
import { useStartWorkout } from '@/lib/start';
import type { Exercise, Profile, Session, Workout } from '@/types';

type Day = { day: number; date: string; status: string; workout_id: number | null; session_id: number | null };
type WeekTotals = { count: number; km: number; seconds: number };
const props = defineProps<{
    today: string;
    week: Day[];
    workouts: Workout[];
    exercises: Exercise[];
    profile: Profile;
    stats: { week: WeekTotals; prev: WeekTotals; weights: number[]; delta30: number | null };
    recent: Session[];
    weekSessions: Session[];
}>();

const todayDate = parseIso(props.today);
const todayIdx = props.week.findIndex((d) => d.date === props.today);
const sel = ref(todayIdx);
const day = computed(() => props.week[sel.value]);
const w = computed(() => props.workouts.find((x) => x.id === day.value.workout_id) ?? null);
const isToday = computed(() => sel.value === todayIdx);
const dayLabel = computed(() => {
    if (isToday.value) return 'Aujourd’hui';
    const d = parseIso(day.value.date);
    return d < todayDate ? DAY_NAMES[sel.value] + ' dernier' : 'Aperçu · ' + DAY_NAMES[sel.value] + ' ' + dateShort(d);
});
const planned = computed(() => props.week.filter((d) => ['run', 'strength'].includes(props.workouts.find((x) => x.id === d.workout_id)?.type ?? '')).length || 1);
const ex = (id: number) => exerciseById(props.exercises, id);
const RING_ICON: Record<string, string> = { done: 'check', missed: 'x', rest: 'moon' };
const ringIcon = (d: Day) => RING_ICON[d.status] ?? TYPE_ICON[props.workouts.find((x) => x.id === d.workout_id)?.type ?? 'rest'];
const shortName = (d: Day) => {
    const x = props.workouts.find((y) => y.id === d.workout_id);
    return x ? x.name.replace('Musculation', 'Muscu') : 'Repos';
};

const lastKg = computed(() => props.stats.weights.at(-1) ?? props.profile.weight);
const spark = computed(() => {
    const vals = props.stats.weights;
    if (vals.length < 2) return null;
    const W = 120, H = 26, mn = Math.min(...vals) - 0.2, mx = Math.max(...vals) + 0.2;
    const pts = vals.map((v, i) => [(i / (vals.length - 1)) * W, H - ((v - mn) / (mx - mn)) * H]);
    return { d: pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' '), e: pts[pts.length - 1] };
});

const { start, conflict, abandonAndStart } = useStartWorkout();
const shownEx = ref<Exercise | null>(null);
const openSession = ref<Session | null>(null);
const recovery = ref(false);
const doneSession = computed(() => props.weekSessions.find((s) => s.id === day.value.session_id) ?? null);
function openDone() {
    if (doneSession.value) openSession.value = doneSession.value;
}
</script>

<template>
    <Head title="Accueil" />
    <div class="page-head">
        <div class="stack gap-4">
            <span class="eyebrow">{{ cap(dateLong(todayDate)) }} {{ todayDate.getFullYear() }} · semaine {{ weekNumber(todayDate) }}</span>
            <h1 class="title-xl">Bonjour {{ profile.name }}</h1>
        </div>
    </div>

    <!-- Carte du jour -->
    <div v-if="!w" class="card hero" style="grid-template-columns: 1fr">
        <div class="hero-main" style="flex-direction: row; align-items: center; gap: 24px; flex-wrap: wrap">
            <div style="width: 150px; flex: none" class="illo"><ExerciseIllustration illustration-key="walk" label="Récupération" /></div>
            <div class="stack gap-8 grow">
                <div class="row gap-8"><span class="eyebrow">{{ dayLabel }}</span><TypeChip type="rest" /></div>
                <h2 class="title-l">Journée de récupération</h2>
                <p class="muted" style="max-width: 52ch">Profite-en pour recharger les batteries. Le repos fait partie du programme : c’est pendant la récupération que le muscle se construit.</p>
            </div>
        </div>
    </div>
    <div v-else class="card hero">
        <div class="hero-main">
            <div class="row gap-8 wrapflex"><span class="eyebrow">{{ dayLabel }}</span><TypeChip :type="w.type" /></div>
            <div class="stack gap-8"><h2 class="title-xl">{{ w.name }}</h2><p class="muted" style="max-width: 48ch">{{ w.objective }}</p></div>
            <div class="hero-meta">
                <div class="meta-item"><span class="k">Durée estimée</span><span class="v num">{{ durationLabel(w) }}</span></div>
                <div class="meta-item"><span class="k">Type</span><span class="v">{{ TYPE_LABEL[w.type] }}</span></div>
                <div v-if="w.type === 'run'" class="meta-item"><span class="k">Distance estimée</span><span class="v num">≈ {{ fmt(estRunKm(w.segments)) }} km</span></div>
                <div v-if="w.type === 'strength'" class="meta-item"><span class="k">Exercices</span><span class="v num">{{ w.exercises.length }} · {{ totalSets(w.exercises) }} séries</span></div>
            </div>
            <div v-if="w.type === 'run'" class="illo" style="max-width: 300px"><ExerciseIllustration illustration-key="run" label="Course sur tapis" /></div>
            <div class="row wrapflex" style="margin-top: auto">
                <button v-if="day.session_id" class="btn btn-lg" @click="openDone"><Icon name="check" />Séance terminée · voir le détail</button>
                <button v-else-if="w.type === 'recovery'" class="btn btn-primary btn-lg" @click="recovery = true"><Icon name="plus" />Enregistrer une activité</button>
                <button v-else class="btn btn-primary btn-lg" @click="start(w)"><Icon name="play" />{{ isToday ? 'Commencer ma séance' : 'Commencer cette séance' }}</button>
                <Link v-if="!day.session_id && w.type !== 'recovery'" :href="`/seances/${w.id}`" class="btn btn-lg btn-ghost">Voir le détail</Link>
            </div>
        </div>
        <div class="hero-side">
            <template v-if="w.type === 'strength'">
                <div class="row" style="justify-content: space-between"><span class="label">Au programme</span><Link :href="`/seances/${w.id}`" class="link">Détail</Link></div>
                <div class="stack">
                    <button v-for="we in w.exercises" :key="we.exercise_id" class="ex-row" @click="shownEx = ex(we.exercise_id) ?? null">
                        <span class="thumb"><ExerciseImage :exercise="ex(we.exercise_id)" :animated="false" pose="end" /></span>
                        <span class="grow stack"><span class="nm">{{ ex(we.exercise_id)?.name }}</span><span class="sub num">{{ we.sets }} × {{ repsLabel(we.reps_min, we.reps_max, ex(we.exercise_id)!) }} · {{ kgLabel(we.target_weight_kg, ex(we.exercise_id)!) }}</span></span>
                    </button>
                </div>
            </template>
            <template v-else-if="w.type === 'run'">
                <div class="row" style="justify-content: space-between"><span class="label">Déroulé</span><Link :href="`/seances/${w.id}`" class="link">Détail</Link></div>
                <RunTimeline :segments="w.segments" />
                <div class="seg-list">
                    <template v-for="(s, i) in w.segments" :key="i">
                        <div v-if="s.kind === 'repeat'" class="seg-item"><span class="sw" style="background: var(--accent)"></span><span class="grow"><b style="font-weight: 500">{{ s.repeat_count }} cycles</b><br /><span class="faint" style="font-size: 12.5px">{{ s.children.map((x) => x.duration_min + ' min à ' + speedLabel(x)).join(' + ') }}</span></span><span class="t">{{ s.repeat_count * s.children.reduce((a, x) => a + x.duration_min, 0) }} min</span></div>
                        <div v-else class="seg-item"><span class="sw" :style="{ background: KIND_COLOR[s.kind] }"></span><span class="grow"><b style="font-weight: 500">{{ s.label }}</b><br /><span class="faint num" style="font-size: 12.5px">{{ speedLabel(s) }}{{ s.incline_pct ? ' · ' + fmt(s.incline_pct, 0) + ' %' : '' }}</span></span><span class="t">{{ s.duration_min }} min</span></div>
                    </template>
                </div>
            </template>
            <template v-else>
                <div class="illo"><ExerciseIllustration illustration-key="walk" label="Marche" /></div>
                <p class="muted" style="font-size: 13.5px">Marche tranquille, mobilité ou étirements légers. Aucune séance obligatoire.</p>
            </template>
        </div>
    </div>

    <section class="section">
        <div class="section-head"><h2>Cette semaine</h2><span class="faint num" style="font-size: 13px">{{ stats.week.count }} / {{ planned }} séances réalisées</span></div>
        <div class="week">
            <button v-for="(d, i) in week" :key="d.date" class="day" :class="['is-' + d.status, { 'is-today': i === todayIdx }]" :aria-pressed="i === sel" :aria-label="DAY_NAMES[i] + ' : ' + shortName(d)" @click="sel = i">
                <span class="dname">{{ DAY_SHORT[i] }}</span><span class="day-ring"><Icon :name="ringIcon(d)" /></span><span class="dnum">{{ parseIso(d.date).getDate() }}</span><span class="dtype">{{ shortName(d) }}</span>
            </button>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Statistiques rapides</h2></div>
        <div class="stats">
            <div class="card stat"><span class="k"><Icon name="check" />Séances</span><span class="v num">{{ stats.week.count }}<small>/ {{ planned }}</small></span><div class="bar"><i :style="{ width: Math.min(100, (stats.week.count / planned) * 100) + '%' }"></i></div><span class="d">Semaine dernière : {{ stats.prev.count }}/{{ planned }}</span></div>
            <div class="card stat"><span class="k">Kilomètres</span><span class="v num">{{ fmt(stats.week.km) }}<small>km</small></span><div class="bar"><i :style="{ width: Math.min(100, (stats.week.km / profile.weekly_km_goal) * 100) + '%' }"></i></div><span class="d">Objectif {{ fmt(profile.weekly_km_goal, 0) }} km · sem. dernière {{ fmt(stats.prev.km) }} km</span></div>
            <div class="card stat"><span class="k">Temps d’entraînement</span><span class="v num">{{ stats.week.seconds ? fmtDur(stats.week.seconds).replace(' min', '') : '0' }}<small>{{ stats.week.seconds >= 3600 ? '' : 'min' }}</small></span><span class="d">Semaine dernière : {{ fmtDur(stats.prev.seconds) }}</span></div>
            <Link href="/progression?onglet=poids" class="card stat" style="text-align: left">
                <span class="k">Poids</span><span class="v num">{{ fmt(lastKg) }}<small>kg</small></span>
                <svg v-if="spark" viewBox="-2 -3 124 32" style="width: 100%; height: 26px" aria-hidden="true"><path :d="spark.d" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" /><circle :cx="spark.e[0]" :cy="spark.e[1]" r="2.6" fill="var(--accent)" /></svg>
                <span class="d"><template v-if="stats.delta30 != null"><span class="num" :class="stats.delta30 <= 0 ? 'delta-good' : 'delta-bad'">{{ stats.delta30 > 0 ? '+' : '' }}{{ fmt(stats.delta30) }} kg</span> sur 30 jours · </template>cible {{ fmt(profile.target_weight_kg, 0) }} kg</span>
            </Link>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Dernières séances</h2><Link href="/entrainements" class="link">Tout voir</Link></div>
        <div class="card">
            <HistoryRow v-for="s in recent" :key="s.id" :s="s" @open="openSession = s" />
            <p v-if="!recent.length" class="empty">Aucune séance enregistrée pour l’instant.</p>
        </div>
    </section>

    <ExerciseSheet v-if="shownEx" :exercise="shownEx" @close="shownEx = null" />
    <SessionSheet v-if="openSession" :session="openSession" :exercises="exercises" :profile="profile" @close="openSession = null" />
    <RecoverySheet v-if="recovery" :date="day.date" :workout-id="w?.id ?? null" @close="recovery = false" />
    <StartConflictSheet v-if="conflict" :workout="conflict" @close="conflict = null" @abandon="abandonAndStart" />
</template>
