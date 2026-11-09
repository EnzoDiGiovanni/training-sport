<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ExerciseIllustration from '@/components/ExerciseIllustration.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import Icon from '@/components/Icon.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import StartConflictSheet from '@/components/StartConflictSheet.vue';
import TypeChip from '@/components/TypeChip.vue';
import { cap, dateLong, daysAgo, fmt, fmtClock } from '@/composables/useFormat';
import { durationLabel, estRunKm, exerciseById, kgLabel, repsLabel, setsSummary, speedLabel } from '@/lib/domain';
import { useStartWorkout } from '@/lib/start';
import type { Exercise, Profile, Session, SessionItem, Workout } from '@/types';

const props = defineProps<{ workout: Workout; exercises: Exercise[]; profile: Profile; last: Session | null; lastPerf: Record<number, (SessionItem & { date: string }) | null> }>();
const w = props.workout;
const ex = (id: number) => exerciseById(props.exercises, id)!;
const { start, conflict, abandonAndStart } = useStartWorkout();
const shown = ref<Exercise | null>(null);
</script>

<template>
    <Head :title="w.name" />
    <div class="stack gap-12">
        <div><Link href="/" class="btn btn-ghost btn-sm"><Icon name="back" />Accueil</Link></div>
        <div class="page-head">
            <div class="stack gap-8">
                <div class="row gap-8"><TypeChip :type="w.type" /><span class="faint num" style="font-size: 13px">{{ durationLabel(w) }}</span></div>
                <h1 class="title-xl">{{ w.name }}</h1>
                <p class="muted" style="max-width: 60ch">{{ w.objective }}</p>
            </div>
            <div class="row">
                <Link :href="`/programme/seances/${w.id}`" class="btn"><Icon name="edit" />Modifier</Link>
                <button v-if="w.type === 'run' || w.type === 'strength'" class="btn btn-primary" @click="start(w)"><Icon name="play" />Commencer</button>
            </div>
        </div>
    </div>

    <div v-if="w.type === 'run'" class="grid-2" style="grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr)">
        <div class="card card-pad stack gap-16">
            <div class="section-head"><h2>Déroulé de la séance</h2><span class="faint num" style="font-size: 13px">≈ {{ fmt(estRunKm(w.segments)) }} km</span></div>
            <RunTimeline :segments="w.segments" />
            <table class="sets" style="margin: 0 -20px; width: calc(100% + 40px)">
                <thead><tr><th>Phase</th><th>Durée</th><th>Vitesse</th><th>Incl.</th></tr></thead>
                <tbody>
                    <template v-for="(s, i) in w.segments" :key="i">
                        <template v-if="s.kind === 'repeat'">
                            <tr><td colspan="4" style="text-align: left; padding-left: 18px; color: var(--accent); font-size: 13px; font-weight: 500">Répéter {{ s.repeat_count }} fois</td></tr>
                            <tr v-for="(x, j) in s.children" :key="j"><td style="padding-left: 30px">{{ x.label }}</td><td class="num">{{ x.duration_min }} min</td><td class="num">{{ speedLabel(x) }}</td><td class="num">{{ fmt(x.incline_pct || 0, 0) }} %</td></tr>
                        </template>
                        <tr v-else><td>{{ s.label }}</td><td class="num">{{ s.duration_min }} min</td><td class="num">{{ speedLabel(s) }}</td><td class="num">{{ fmt(s.incline_pct || 0, 0) }} %</td></tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="stack gap-16">
            <div class="illo"><ExerciseIllustration illustration-key="run" label="Course sur tapis" /></div>
            <div class="card card-pad stack gap-12">
                <span class="label">Dernière fois</span>
                <template v-if="last && last.run">
                    <div class="row" style="justify-content: space-between"><span class="muted">{{ cap(dateLong(last.date)) }}</span><span class="faint" style="font-size: 12.5px">{{ daysAgo(last.date) }}</span></div>
                    <div class="grid-3">
                        <div class="meta-item"><span class="k">Distance</span><span class="v num">{{ fmt(last.run.distance_km, 2) }} km</span></div>
                        <div class="meta-item"><span class="k">Durée</span><span class="v num">{{ fmtClock(last.duration_seconds) }}</span></div>
                        <div class="meta-item"><span class="k">Vit. moy.</span><span class="v num">{{ fmt(last.run.average_speed_kmh) }}</span></div>
                    </div>
                </template>
                <p v-else class="faint">Pas encore réalisée.</p>
            </div>
            <div class="notice"><Icon name="info" /><span>Tapis limité à {{ fmt(profile.treadmill_max_kmh, profile.treadmill_max_kmh % 1 ? 1 : 0) }} km/h. Inclinaison conseillée : 1 % pour compenser l’absence de résistance de l’air, si c’est confortable.</span></div>
        </div>
    </div>

    <div v-else-if="w.type === 'strength'" class="w-grid">
        <button v-for="(we, i) in w.exercises" :key="we.exercise_id" class="card lib-card" @click="shown = ex(we.exercise_id)">
            <div class="illo"><ExerciseImage :exercise="ex(we.exercise_id)" /></div>
            <div class="body">
                <div class="row" style="justify-content: space-between"><span class="faint num" style="font-size: 12px">{{ String(i + 1).padStart(2, '0') }}</span><span class="faint" style="font-size: 12px">Repos {{ fmtClock(we.rest_seconds) }}</span></div>
                <span style="font-weight: 600; font-size: 15px">{{ ex(we.exercise_id).name }}</span>
                <span class="faint" style="font-size: 13px">{{ ex(we.exercise_id).primary_muscle }}</span>
                <span class="num" style="font-size: 14px; margin-top: 6px">{{ we.sets }} × {{ repsLabel(we.reps_min, we.reps_max, ex(we.exercise_id)) }} · {{ kgLabel(we.target_weight_kg, ex(we.exercise_id)) }}</span>
                <span class="faint num" style="font-size: 12.5px">{{ lastPerf[we.exercise_id] ? 'Dernière fois : ' + setsSummary(lastPerf[we.exercise_id]!) : 'Pas encore réalisé' }}</span>
            </div>
        </button>
    </div>

    <ExerciseSheet v-if="shown" :exercise="shown" @close="shown = null" />
    <StartConflictSheet v-if="conflict" :workout="conflict" @close="conflict = null" @abandon="abandonAndStart" />
</template>
