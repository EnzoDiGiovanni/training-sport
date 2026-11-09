<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import ExerciseFormSheet from '@/components/ExerciseFormSheet.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExercisePicker from '@/components/ExercisePicker.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import Icon from '@/components/Icon.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import { DAY_SHORT, fmt, fmtKg, num } from '@/composables/useFormat';
import { estRunKm, exerciseById, KIND_LABEL, maxLoadFor, runMinutes, TYPE_LABEL, totalSets } from '@/lib/domain';
import { toast } from '@/lib/toast';
import type { Exercise, Profile, RepeatBlock, Segment, Step, WorkoutExercise, WorkoutType } from '@/types';

type Draft = { id: number | null; name: string; type: WorkoutType; objective: string | null; estimated_duration_min: number | null; days: number[]; exercises: WorkoutExercise[]; segments: Segment[] };
const props = defineProps<{ draft: Draft; exercises: Exercise[]; profile: Profile }>();
const d = reactive<Draft>(JSON.parse(JSON.stringify(props.draft)));
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const p = props.profile;
const ex = (id: number) => exerciseById(props.exercises, id);

function toggleDay(i: number) {
    const k = d.days.indexOf(i);
    if (k >= 0) d.days.splice(k, 1);
    else d.days.push(i);
}
function move<T>(arr: T[], i: number, j: number) {
    if (j < 0 || j >= arr.length) return;
    const [x] = arr.splice(i, 1);
    arr.splice(j, 0, x);
}
function setNum(obj: Record<string, unknown>, key: string, v: string, int = false) {
    const n = num(v);
    obj[key] = isNaN(n) ? null : int ? Math.round(n) : n;
}

// Glisser-déposer (souris) ; les boutons haut/bas restent pour le clavier.
const dragFrom = ref<number | null>(null);
function onDrop(i: number) {
    if (dragFrom.value != null) move(d.exercises, dragFrom.value, i);
    dragFrom.value = null;
}

const picking = ref(false);
const creating = ref(false);
const shown = ref<Exercise | null>(null);
function pick(e: Exercise) {
    d.exercises.push({ exercise_id: e.id, sets: 3, reps_min: e.measurement === 'time' ? 30 : 10, reps_max: e.measurement === 'time' ? 30 : 12, target_weight_kg: e.load_type === 'bodyweight' ? null : Math.min(maxLoadFor(e.load_type, p), 6), rest_seconds: p.default_rest_seconds });
    picking.value = false;
    toast(e.name + ' ajouté');
}
function created(id: number | null) {
    creating.value = false;
    const e = id ? ex(id) : null;
    if (e) pick(e);
}

const newStep = (): Step => ({ kind: 'run', label: 'Course', duration_min: 5, speed_min_kmh: 8, speed_max_kmh: 8, incline_pct: 0 });
function addGroup() {
    d.segments.splice(Math.max(0, d.segments.length - 1), 0, {
        kind: 'repeat',
        repeat_count: 4,
        children: [
            { kind: 'fast', label: 'Accélération', duration_min: 1, speed_min_kmh: 9.5, speed_max_kmh: 9.5, incline_pct: 0 },
            { kind: 'recovery', label: 'Récupération active', duration_min: 2, speed_min_kmh: 7, speed_max_kmh: 7, incline_pct: 0 },
        ],
    });
}
const tooFast = (s: Step) => s.speed_max_kmh > p.treadmill_max_kmh || s.speed_min_kmh > p.treadmill_max_kmh;
function onTypeChange() {
    if (d.type === 'run' && !d.segments.length) d.segments = [{ kind: 'run', label: 'Course', duration_min: 20, speed_min_kmh: 8, speed_max_kmh: 8.5, incline_pct: 1 }];
}

function save() {
    saving.value = true;
    const payload = { ...d, days: d.days };
    const opts = { preserveScroll: true, onError: (e: Record<string, string>) => (errors.value = e), onFinish: () => (saving.value = false) };
    if (d.id) router.put(`/programme/seances/${d.id}`, payload, opts);
    else router.post('/programme/seances', payload, opts);
}
const runMin = computed(() => runMinutes(d.segments));
const STEP_FIELDS: [keyof Step, string, string][] = [['duration_min', 'Durée', 'min'], ['speed_min_kmh', 'Vitesse min', 'km/h'], ['speed_max_kmh', 'Vitesse max', 'km/h'], ['incline_pct', 'Incl.', '%']];
</script>

<template>
    <Head :title="d.name || 'Nouvelle séance'" />
    <div class="stack gap-12">
        <div><Link href="/programme" class="btn btn-ghost btn-sm"><Icon name="back" />Programme</Link></div>
        <div class="page-head">
            <div class="stack gap-4"><span class="eyebrow">{{ d.id ? 'Modifier la séance' : 'Nouvelle séance' }}</span><h1 class="title-xl">{{ d.name || 'Nouvelle séance' }}</h1></div>
            <div class="row"><Link href="/programme" class="btn">Annuler</Link><button class="btn btn-primary" :disabled="saving" @click="save"><Icon name="check" />Enregistrer</button></div>
        </div>
    </div>
    <div class="notice"><Icon name="info" /><span>Les séances déjà réalisées ne sont pas modifiées : elles gardent les valeurs du programme au moment où elles ont été faites.</span></div>

    <div class="card card-pad stack gap-16">
        <div class="grid-2">
            <div class="field"><label for="d-name">Nom de la séance</label><input id="d-name" v-model="d.name" class="input" :class="{ 'is-error': errors.name }" /><span v-if="errors.name" class="err">{{ errors.name }}</span></div>
            <div class="field"><label for="d-type">Type</label>
                <select id="d-type" v-model="d.type" class="input" @change="onTypeChange"><option v-for="t in ['strength', 'run', 'recovery'] as const" :key="t" :value="t">{{ TYPE_LABEL[t] }}</option></select>
            </div>
        </div>
        <div class="field"><label for="d-obj">Objectif</label><textarea id="d-obj" v-model="d.objective" class="input" style="min-height: 60px"></textarea></div>
        <div class="grid-2">
            <div v-if="d.type !== 'run'" class="field"><label for="d-dur">Durée estimée</label><div class="input-unit"><input id="d-dur" class="input num" inputmode="numeric" :value="d.estimated_duration_min ?? ''" @change="setNum(d, 'estimated_duration_min', ($event.target as HTMLInputElement).value, true)" /><span>min</span></div></div>
            <div v-else class="field"><span class="label">Durée</span><div class="input num" style="display: flex; align-items: center; background: var(--surface-2); border-color: var(--border)">{{ runMin }} min (calculée)</div></div>
            <div class="field"><span class="label">Jours planifiés</span><div class="filters"><button v-for="(n, i) in DAY_SHORT" :key="n" type="button" class="fchip" :aria-pressed="d.days.includes(i + 1)" @click="toggleDay(i + 1)">{{ n }}</button></div></div>
        </div>
    </div>

    <section v-if="d.type === 'strength'" class="section">
        <div class="section-head"><h2>Exercices</h2><span class="faint num" style="font-size: 13px">{{ d.exercises.length }} exercices · {{ totalSets(d.exercises) }} séries</span></div>
        <div class="card" style="padding: 4px 18px">
            <div v-for="(we, i) in d.exercises" :key="i + '-' + we.exercise_id" class="edit-ex" draggable="true" :style="{ opacity: dragFrom === i ? 0.5 : 1 }" @dragstart="dragFrom = i" @dragover.prevent @drop="onDrop(i)" @dragend="dragFrom = null">
                <div class="order-btns">
                    <button type="button" :disabled="i === 0" aria-label="Monter" @click="move(d.exercises, i, i - 1)"><Icon name="up" :size="15" /></button>
                    <button type="button" :disabled="i === d.exercises.length - 1" aria-label="Descendre" @click="move(d.exercises, i, i + 1)"><Icon name="down" :size="15" /></button>
                </div>
                <button class="thumb" style="width: 72px; height: 54px" :aria-label="'Voir ' + ex(we.exercise_id)?.name" @click="shown = ex(we.exercise_id) ?? null"><ExerciseImage :exercise="ex(we.exercise_id)" :animated="false" pose="end" /></button>
                <div style="min-width: 0">
                    <div style="font-weight: 500">{{ ex(we.exercise_id)?.name }}</div>
                    <div class="faint" style="font-size: 12.5px">{{ ex(we.exercise_id)?.primary_muscle }} · {{ ex(we.exercise_id)?.equipment }}</div>
                    <div class="params">
                        <div class="field"><label :for="`de-${i}-sets`">Séries</label><div class="input-unit" style="width: 92px"><input :id="`de-${i}-sets`" class="input num" style="height: 36px" inputmode="numeric" :value="we.sets" @change="setNum(we, 'sets', ($event.target as HTMLInputElement).value, true)" /></div></div>
                        <div class="field"><label :for="`de-${i}-min`">{{ ex(we.exercise_id)?.measurement === 'time' ? 'Durée min' : 'Reps min' }}</label><div class="input-unit" style="width: 92px"><input :id="`de-${i}-min`" class="input num" style="height: 36px" inputmode="numeric" :value="we.reps_min" @change="setNum(we, 'reps_min', ($event.target as HTMLInputElement).value, true)" /><span>{{ ex(we.exercise_id)?.measurement === 'time' ? 's' : '' }}</span></div></div>
                        <div class="field"><label :for="`de-${i}-max`">{{ ex(we.exercise_id)?.measurement === 'time' ? 'Durée max' : 'Reps max' }}</label><div class="input-unit" style="width: 92px"><input :id="`de-${i}-max`" class="input num" style="height: 36px" inputmode="numeric" :value="we.reps_max" @change="setNum(we, 'reps_max', ($event.target as HTMLInputElement).value, true)" /><span>{{ ex(we.exercise_id)?.measurement === 'time' ? 's' : '' }}</span></div></div>
                        <div v-if="ex(we.exercise_id)?.load_type !== 'bodyweight'" class="field"><label :for="`de-${i}-kg`">{{ ex(we.exercise_id)?.load_type === 'two_dumbbells' ? 'Charge / haltère' : 'Charge' }}</label><div class="input-unit" style="width: 104px"><input :id="`de-${i}-kg`" class="input num" style="height: 36px" :style="(we.target_weight_kg ?? 0) > maxLoadFor(ex(we.exercise_id)!.load_type, p) ? 'border-color:var(--danger)' : ''" inputmode="decimal" :value="we.target_weight_kg != null ? fmt(we.target_weight_kg, we.target_weight_kg % 1 ? 1 : 0) : ''" @change="setNum(we, 'target_weight_kg', ($event.target as HTMLInputElement).value)" /><span>kg</span></div></div>
                        <div class="field"><label :for="`de-${i}-rest`">Repos</label><div class="input-unit" style="width: 92px"><input :id="`de-${i}-rest`" class="input num" style="height: 36px" inputmode="numeric" :value="we.rest_seconds" @change="setNum(we, 'rest_seconds', ($event.target as HTMLInputElement).value, true)" /><span>s</span></div></div>
                    </div>
                    <div v-if="ex(we.exercise_id) && ex(we.exercise_id)!.load_type !== 'bodyweight' && (we.target_weight_kg ?? 0) > maxLoadFor(ex(we.exercise_id)!.load_type, p)" class="err" style="margin-top: 6px">Au-delà de ton matériel : {{ fmtKg(maxLoadFor(ex(we.exercise_id)!.load_type, p)) }} kg max{{ ex(we.exercise_id)!.load_type === 'two_dumbbells' ? ' par haltère' : '' }}.</div>
                    <div v-if="we.reps_min > we.reps_max" class="err" style="margin-top: 6px">Le minimum dépasse le maximum.</div>
                </div>
                <button class="btn btn-ghost btn-icon rm" :aria-label="'Retirer ' + ex(we.exercise_id)?.name" @click="d.exercises.splice(i, 1)"><Icon name="trash" /></button>
            </div>
            <p v-if="!d.exercises.length" class="empty">Aucun exercice. Ajoute-en un depuis la bibliothèque.</p>
        </div>
        <span v-if="errors.exercises" class="err">{{ errors.exercises }}</span>
        <div><button class="btn" @click="picking = true"><Icon name="plus" />Ajouter un exercice</button></div>
    </section>

    <section v-if="d.type === 'run'" class="section">
        <div class="section-head"><h2>Déroulé</h2><span class="faint num" style="font-size: 13px">{{ runMin }} min · ≈ {{ fmt(estRunKm(d.segments)) }} km</span></div>
        <div class="card card-pad stack gap-12"><RunTimeline :segments="d.segments" /></div>
        <div class="card" style="padding: 4px 18px">
            <template v-for="(s, i) in d.segments" :key="i">
                <div v-if="s.kind === 'repeat'" style="padding: 12px 0; border-bottom: 1px solid var(--border)">
                    <div class="row wrapflex" style="justify-content: space-between">
                        <div class="row gap-8">
                            <span class="chip chip-strength">Bloc fractionné</span><span class="muted" style="font-size: 13px">Répéter</span>
                            <div class="stepper">
                                <button type="button" aria-label="Moins de cycles" @click="(s as RepeatBlock).repeat_count = Math.max(1, s.repeat_count - 1)"><Icon name="minus" :size="14" /></button>
                                <input class="num" aria-label="Nombre de cycles" :value="s.repeat_count" @change="(s as RepeatBlock).repeat_count = Math.max(1, Math.min(20, Math.round(num(($event.target as HTMLInputElement).value)) || 1))" />
                                <button type="button" aria-label="Plus de cycles" @click="(s as RepeatBlock).repeat_count = Math.min(20, s.repeat_count + 1)"><Icon name="plus" :size="14" /></button>
                            </div>
                            <span class="muted" style="font-size: 13px">fois</span>
                        </div>
                        <div class="row gap-8">
                            <div class="order-btns" style="flex-direction: row"><button type="button" :disabled="i === 0" aria-label="Monter le bloc" @click="move(d.segments, i, i - 1)"><Icon name="up" :size="15" /></button><button type="button" :disabled="i === d.segments.length - 1" aria-label="Descendre le bloc" @click="move(d.segments, i, i + 1)"><Icon name="down" :size="15" /></button></div>
                            <button class="btn btn-sm btn-ghost" @click="s.children.push(newStep())"><Icon name="plus" />Phase</button>
                            <button class="btn btn-ghost btn-icon" aria-label="Supprimer le bloc" @click="d.segments.splice(i, 1)"><Icon name="trash" /></button>
                        </div>
                    </div>
                    <div style="padding-left: 18px; border-left: 2px solid var(--accent-line); margin-top: 8px">
                        <div v-for="(st, j) in s.children" :key="j" class="edit-ex" style="grid-template-columns: auto 1fr auto">
                            <div class="order-btns"><button type="button" :disabled="j === 0" aria-label="Monter" @click="move(s.children, j, j - 1)"><Icon name="up" :size="15" /></button><button type="button" :disabled="j === s.children.length - 1" aria-label="Descendre" @click="move(s.children, j, j + 1)"><Icon name="down" :size="15" /></button></div>
                            <div class="params" style="margin: 0; align-items: flex-end">
                                <div class="field" style="flex: 1; min-width: 150px"><label :for="`sg-${i}-${j}-l`">Phase</label><input :id="`sg-${i}-${j}-l`" v-model="st.label" class="input" style="height: 36px" /></div>
                                <div class="field"><label :for="`sg-${i}-${j}-k`">Type</label><select :id="`sg-${i}-${j}-k`" v-model="st.kind" class="input" style="height: 36px; width: 150px"><option v-for="(l, k) in KIND_LABEL" :key="k" :value="k">{{ l }}</option></select></div>
                                <div v-for="[f, l, u] in STEP_FIELDS" :key="f" class="field"><label :for="`sg-${i}-${j}-${f}`">{{ l }}</label><div class="input-unit" :style="{ width: f === 'duration_min' || f === 'incline_pct' ? '84px' : '104px' }"><input :id="`sg-${i}-${j}-${f}`" class="input num" style="height: 36px" :style="(f === 'speed_max_kmh' || f === 'speed_min_kmh') && (st[f] as number) > p.treadmill_max_kmh ? 'border-color:var(--danger)' : ''" inputmode="decimal" :value="fmt(st[f] as number, (st[f] as number) % 1 ? 1 : 0)" @change="setNum(st, f, ($event.target as HTMLInputElement).value)" /><span>{{ u }}</span></div></div>
                            </div>
                            <button class="btn btn-ghost btn-icon" aria-label="Supprimer la phase" @click="s.children.splice(j, 1)"><Icon name="trash" /></button>
                            <div v-if="tooFast(st)" class="err" style="grid-column: 2">Ton tapis est limité à {{ p.treadmill_max_kmh }} km/h.</div>
                        </div>
                    </div>
                </div>
                <div v-else class="edit-ex" style="grid-template-columns: auto 1fr auto">
                    <div class="order-btns"><button type="button" :disabled="i === 0" aria-label="Monter" @click="move(d.segments, i, i - 1)"><Icon name="up" :size="15" /></button><button type="button" :disabled="i === d.segments.length - 1" aria-label="Descendre" @click="move(d.segments, i, i + 1)"><Icon name="down" :size="15" /></button></div>
                    <div class="params" style="margin: 0; align-items: flex-end">
                        <div class="field" style="flex: 1; min-width: 150px"><label :for="`sg-${i}-l`">Phase</label><input :id="`sg-${i}-l`" v-model="s.label" class="input" style="height: 36px" /></div>
                        <div class="field"><label :for="`sg-${i}-k`">Type</label><select :id="`sg-${i}-k`" v-model="s.kind" class="input" style="height: 36px; width: 150px"><option v-for="(l, k) in KIND_LABEL" :key="k" :value="k">{{ l }}</option></select></div>
                        <div v-for="[f, l, u] in STEP_FIELDS" :key="f" class="field"><label :for="`sg-${i}-${f}`">{{ l }}</label><div class="input-unit" :style="{ width: f === 'duration_min' || f === 'incline_pct' ? '84px' : '104px' }"><input :id="`sg-${i}-${f}`" class="input num" style="height: 36px" :style="(f === 'speed_max_kmh' || f === 'speed_min_kmh') && (s[f] as number) > p.treadmill_max_kmh ? 'border-color:var(--danger)' : ''" inputmode="decimal" :value="fmt(s[f] as number, (s[f] as number) % 1 ? 1 : 0)" @change="setNum(s, f, ($event.target as HTMLInputElement).value)" /><span>{{ u }}</span></div></div>
                    </div>
                    <button class="btn btn-ghost btn-icon" aria-label="Supprimer la phase" @click="d.segments.splice(i, 1)"><Icon name="trash" /></button>
                    <div v-if="tooFast(s)" class="err" style="grid-column: 2">Ton tapis est limité à {{ p.treadmill_max_kmh }} km/h.</div>
                </div>
            </template>
        </div>
        <span v-if="errors.segments" class="err">{{ errors.segments }}</span>
        <div class="row gap-8"><button class="btn" @click="d.segments.push(newStep())"><Icon name="plus" />Ajouter une phase</button><button class="btn btn-ghost" @click="addGroup"><Icon name="plus" />Bloc fractionné</button></div>
    </section>

    <div class="row" style="justify-content: flex-end"><button class="btn btn-primary btn-lg" :disabled="saving" @click="save"><Icon name="check" />Enregistrer</button></div>

    <ExercisePicker v-if="picking" :exercises="exercises" :selected="d.exercises.map((e) => e.exercise_id)" @close="picking = false" @pick="pick" @create="picking = false; creating = true" />
    <ExerciseFormSheet v-if="creating" @close="creating = false" @saved="created" />
    <ExerciseSheet v-if="shown" :exercise="shown" @close="shown = null" />
</template>
