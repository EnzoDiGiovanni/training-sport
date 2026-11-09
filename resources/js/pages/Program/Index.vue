<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Icon from '@/components/Icon.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import Sheet from '@/components/Sheet.vue';
import TypeChip from '@/components/TypeChip.vue';
import { DAY_NAMES, DAY_SHORT, fmtKg } from '@/composables/useFormat';
import { durationLabel, exerciseById, flatSteps, maxLoadFor, TYPE_ICON, TYPE_LABEL } from '@/lib/domain';
import type { Exercise, Profile, Workout, WorkoutType } from '@/types';

const props = defineProps<{ workouts: Workout[]; schedule: Record<number, number | null>; exercises: Exercise[]; profile: Profile; sessionsCount: number }>();
const byId = (id: number | null) => props.workouts.find((w) => w.id === id);
const planned = computed(() => Object.values(props.schedule).filter((id) => ['run', 'strength'].includes(byId(id)?.type ?? '')).length);

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
                if ((we.target_weight_kg ?? 0) > mx) out.push(`${w.name} · ${ex.name} : ${fmtKg(we.target_weight_kg!)} kg prévus, au-delà de ton matériel (${fmtKg(mx)} kg max${ex.load_type === 'two_dumbbells' ? ' par haltère' : ''}).`);
            });
        if (w.type === 'run')
            flatSteps(w.segments).forEach((s) => {
                if (s.speed_max_kmh > p.treadmill_max_kmh) out.push(`${w.name} · ${s.label} : ${s.speed_max_kmh} km/h prévus, ton tapis est limité à ${p.treadmill_max_kmh} km/h.`);
            });
    });
    return [...new Set(out)];
});

const setDay = (day: number, value: string) => router.put(`/programme/jours/${day}`, { workout_id: value ? +value : null }, { preserveScroll: true });
const daysOf = (w: Workout) => Object.entries(props.schedule).filter(([, id]) => id === w.id).map(([d]) => DAY_SHORT[+d - 1]);
const toDelete = ref<Workout | null>(null);
const newOpen = ref(false);
const destroy = () => router.delete(`/programme/seances/${toDelete.value!.id}`, { preserveScroll: true, onFinish: () => (toDelete.value = null) });
const NEW_TYPES: [WorkoutType, string][] = [['strength', 'Exercices, séries, charges'], ['run', 'Phases, vitesses, fractionné'], ['recovery', 'Activité douce facultative']];
</script>

<template>
    <Head title="Programme" />
    <div class="page-head">
        <div class="stack gap-4"><span class="eyebrow">Configuration</span><h1 class="title-xl">Mon programme</h1></div>
        <div class="seg" role="group" aria-label="Section">
            <Link href="/programme" as="button" :aria-pressed="true">Programme</Link>
            <Link href="/exercices" as="button" :aria-pressed="false">Bibliothèque d’exercices</Link>
        </div>
    </div>
    <div class="notice"><Icon name="info" /><span>Les modifications s’appliquent aux prochaines séances et au tableau de bord. Les {{ sessionsCount }} séances déjà enregistrées gardent leurs valeurs d’origine.</span></div>
    <div v-if="warnings.length" class="notice warn"><Icon name="warn" /><span><template v-for="(w, i) in warnings" :key="i">{{ w }}<br v-if="i < warnings.length - 1" /></template></span></div>

    <section class="section">
        <div class="section-head"><h2>Semaine type</h2><span class="faint num" style="font-size: 13px">{{ planned }} séances / semaine</span></div>
        <div class="plan-grid">
            <div v-for="(n, i) in DAY_NAMES" :key="n" class="card plan-day">
                <span class="dn">{{ n }}</span>
                <span class="grow" style="display: flex; flex-direction: column; gap: 8px">
                    <TypeChip :type="byId(schedule[i + 1])?.type ?? 'rest'" />
                    <select class="input" :aria-label="'Séance du ' + n.toLowerCase()" :value="schedule[i + 1] ?? ''" @change="setDay(i + 1, ($event.target as HTMLSelectElement).value)">
                        <option value="">Repos</option>
                        <option v-for="w in workouts" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </span>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head"><h2>Séances</h2><button class="btn btn-sm btn-primary" @click="newOpen = true"><Icon name="plus" />Nouvelle séance</button></div>
        <div class="w-grid">
            <div v-for="w in workouts" :key="w.id" class="card w-card">
                <div class="row" style="justify-content: space-between; align-items: flex-start">
                    <div class="stack gap-4">
                        <div class="row gap-8"><TypeChip :type="w.type" /><span class="faint num" style="font-size: 12.5px">{{ durationLabel(w) }}</span></div>
                        <h3 style="font-size: 17px">{{ w.name }}</h3>
                    </div>
                    <span class="faint" style="font-size: 12.5px">{{ daysOf(w).length ? daysOf(w).join(', ') : 'Non planifiée' }}</span>
                </div>
                <template v-if="w.type === 'strength'">
                    <div class="thumbs"><span v-for="we in w.exercises.slice(0, 6)" :key="we.exercise_id" class="thumb"><ExerciseImage :exercise="exerciseById(exercises, we.exercise_id)" :animated="false" pose="end" /></span></div>
                    <span class="faint" style="font-size: 12.5px">{{ w.exercises.map((we) => exerciseById(exercises, we.exercise_id)?.name).join(' · ') }}</span>
                </template>
                <RunTimeline v-else-if="w.type === 'run'" :segments="w.segments" />
                <span v-else class="faint" style="font-size: 13px">{{ w.objective }}</span>
                <div class="row gap-8" style="margin-top: auto">
                    <Link class="btn btn-sm" :href="`/programme/seances/${w.id}`"><Icon name="edit" />Modifier</Link>
                    <button class="btn btn-sm btn-ghost" @click="router.post(`/programme/seances/${w.id}/dupliquer`, {}, { preserveScroll: true })"><Icon name="copy" />Dupliquer</button>
                    <button class="btn btn-sm btn-ghost" style="margin-left: auto" :aria-label="'Supprimer ' + w.name" @click="toDelete = w"><Icon name="trash" /></button>
                </div>
            </div>
        </div>
    </section>

    <Sheet v-if="toDelete" label="Supprimer la séance" @close="toDelete = null">
        <div class="sheet-head"><h3 class="grow title-m">Supprimer « {{ toDelete.name }} » ?</h3></div>
        <div class="sheet-body">
            <p class="muted">
                <template v-if="daysOf(toDelete).length">Elle est planifiée le {{ Object.entries(schedule).filter(([, id]) => id === toDelete!.id).map(([d]) => DAY_NAMES[+d - 1].toLowerCase()).join(', ') }} : ces jours passeront en repos. </template>Les séances déjà réalisées restent dans l’historique.
            </p>
        </div>
        <div class="sheet-foot"><button class="btn" @click="toDelete = null">Annuler</button><button class="btn btn-danger" @click="destroy">Supprimer</button></div>
    </Sheet>
    <Sheet v-if="newOpen" label="Nouvelle séance" @close="newOpen = false">
        <div class="sheet-head"><h3 class="grow title-m">Nouvelle séance</h3><button class="btn btn-ghost btn-icon" aria-label="Fermer" @click="newOpen = false"><Icon name="x" /></button></div>
        <div class="sheet-body">
            <span class="label">Type de séance</span>
            <div class="grid-3">
                <Link v-for="[t, d] in NEW_TYPES" :key="t" :href="`/programme/seances/nouvelle?type=${t}`" class="card card-pad stack gap-8" style="align-items: flex-start; text-align: left">
                    <span class="chip" :class="'chip-' + t"><Icon :name="TYPE_ICON[t]" :size="13" />{{ TYPE_LABEL[t] }}</span><span class="faint" style="font-size: 12.5px">{{ d }}</span>
                </Link>
            </div>
        </div>
    </Sheet>
</template>
