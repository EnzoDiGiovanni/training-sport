<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import ExerciseFormSheet from '@/components/ExerciseFormSheet.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExercisePicker from '@/components/ExercisePicker.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import StepEditor from '@/components/StepEditor.vue';
import { DAY_SHORT, fmt, fmtKg, num } from '@/composables/useFormat';
import {
    estRunKm,
    exerciseById,
    maxLoadFor,
    runMinutes,
    TYPE_LABEL,
    totalSets,
} from '@/lib/domain';
import { toast } from '@/lib/toast';
import type {
    Exercise,
    Profile,
    RepeatBlock,
    Segment,
    Step,
    WorkoutExercise,
    WorkoutType,
} from '@/types';

type Draft = {
    id: number | null;
    name: string;
    type: WorkoutType;
    objective: string | null;
    estimated_duration_min: number | null;
    days: number[];
    exercises: WorkoutExercise[];
    segments: Segment[];
};
const props = defineProps<{
    draft: Draft;
    exercises: Exercise[];
    profile: Profile;
}>();
const d = reactive<Draft>(JSON.parse(JSON.stringify(props.draft)));
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const saved = ref(JSON.stringify(d));
const dirty = computed(() => JSON.stringify(d) !== saved.value);
useUnsavedGuard(() => dirty.value && !saving.value);
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
function setNum(
    obj: Record<string, unknown>,
    key: string,
    v: string,
    int = false,
) {
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
    d.exercises.push({
        exercise_id: e.id,
        sets: 3,
        reps_min: e.measurement === 'time' ? 30 : 10,
        reps_max: e.measurement === 'time' ? 30 : 12,
        target_weight_kg:
            e.load_type === 'bodyweight'
                ? null
                : Math.min(maxLoadFor(e.load_type, p), 6),
        rest_seconds: p.default_rest_seconds,
    });
    picking.value = false;
    toast(e.name + ' ajouté');
}
function created(id: number | null) {
    creating.value = false;
    const e = id ? ex(id) : null;
    if (e) pick(e);
}

const newStep = (): Step => ({
    kind: 'run',
    label: 'Course',
    duration_min: 5,
    speed_min_kmh: 8,
    speed_max_kmh: 8,
    incline_pct: 0,
});
function addGroup() {
    d.segments.splice(Math.max(0, d.segments.length - 1), 0, {
        kind: 'repeat',
        repeat_count: 4,
        children: [
            {
                kind: 'fast',
                label: 'Accélération',
                duration_min: 1,
                speed_min_kmh: 9.5,
                speed_max_kmh: 9.5,
                incline_pct: 0,
            },
            {
                kind: 'recovery',
                label: 'Récupération active',
                duration_min: 2,
                speed_min_kmh: 7,
                speed_max_kmh: 7,
                incline_pct: 0,
            },
        ],
    });
}
function onTypeChange() {
    if (d.type === 'run' && !d.segments.length)
        d.segments = [
            {
                kind: 'run',
                label: 'Course',
                duration_min: 20,
                speed_min_kmh: 8,
                speed_max_kmh: 8.5,
                incline_pct: 1,
            },
        ];
}

function save() {
    saving.value = true;
    const payload = { ...d, days: d.days };
    const opts = {
        preserveScroll: true,
        onSuccess: () => (saved.value = JSON.stringify(d)),
        onError: (e: Record<string, string>) => (errors.value = e),
        onFinish: () => (saving.value = false),
    };
    if (d.id) router.put(`/programme/seances/${d.id}`, payload, opts);
    else router.post('/programme/seances', payload, opts);
}
const runMin = computed(() => runMinutes(d.segments));
const TYPES = (['strength', 'run', 'recovery'] as WorkoutType[]).map(
    (value) => ({
        value,
        label: TYPE_LABEL[value],
    }),
);
const overMax = (we: WorkoutExercise) => {
    const e = ex(we.exercise_id);
    return (
        !!e &&
        e.load_type !== 'bodyweight' &&
        (we.target_weight_kg ?? 0) > maxLoadFor(e.load_type, p)
    );
};
</script>

<template>
    <Head :title="d.name || 'Nouvelle séance'" />
    <div class="space-y-3">
        <UButton
            to="/programme"
            icon="i-lucide-chevron-left"
            color="neutral"
            variant="ghost"
            size="sm"
            class="-ml-2"
            >Programme</UButton
        >
        <UPageHeader
            :headline="d.id ? 'Modifier la séance' : 'Nouvelle séance'"
            :title="d.name || 'Nouvelle séance'"
            :ui="{ root: 'pt-0' }"
        >
            <template #links>
                <UButton to="/programme" color="neutral" variant="outline"
                    >Annuler</UButton
                >
                <UButton icon="i-lucide-check" :loading="saving" @click="save"
                    >Enregistrer</UButton
                >
            </template>
        </UPageHeader>
    </div>
    <p class="-mt-4 flex items-start gap-1.5 text-sm text-muted">
        <UIcon name="i-lucide-info" class="mt-0.5 size-4 flex-none" />
        <span
            >Les séances déjà réalisées ne sont pas modifiées : elles gardent
            les valeurs du programme au moment où elles ont été faites.</span
        >
    </p>

    <UCard :ui="{ body: 'space-y-5' }">
        <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="Nom de la séance" :error="errors.name">
                <UInput v-model="d.name" class="w-full" />
            </UFormField>
            <UFormField label="Type">
                <USelect
                    v-model="d.type"
                    :items="TYPES"
                    class="w-full"
                    @update:model-value="onTypeChange"
                />
            </UFormField>
        </div>
        <UFormField label="Objectif">
            <UTextarea
                :model-value="d.objective ?? ''"
                :rows="2"
                class="w-full"
                @update:model-value="(v) => (d.objective = String(v))"
            />
        </UFormField>
        <div class="grid gap-4 sm:grid-cols-2">
            <UFormField v-if="d.type !== 'run'" label="Durée estimée">
                <UInput
                    inputmode="numeric"
                    class="w-full"
                    :model-value="String(d.estimated_duration_min ?? '')"
                    @change="
                        setNum(
                            d,
                            'estimated_duration_min',
                            ($event.target as HTMLInputElement).value,
                            true,
                        )
                    "
                >
                    <template #trailing
                        ><span class="text-xs text-muted">min</span></template
                    >
                </UInput>
            </UFormField>
            <UFormField v-else label="Durée">
                <UInput
                    :model-value="runMin + ' min (calculée)'"
                    disabled
                    class="w-full tabular-nums"
                />
            </UFormField>
            <UFormField label="Jours planifiés">
                <div class="flex flex-wrap gap-1.5">
                    <UButton
                        v-for="(n, i) in DAY_SHORT"
                        :key="n"
                        size="sm"
                        :color="d.days.includes(i + 1) ? 'primary' : 'neutral'"
                        :variant="d.days.includes(i + 1) ? 'soft' : 'outline'"
                        :aria-pressed="d.days.includes(i + 1)"
                        @click="toggleDay(i + 1)"
                        >{{ n }}</UButton
                    >
                </div>
            </UFormField>
        </div>
    </UCard>

    <section v-if="d.type === 'strength'" class="space-y-3">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-semibold">Exercices</h2>
            <span class="text-sm text-muted tabular-nums"
                >{{ d.exercises.length }} exercices ·
                {{ totalSets(d.exercises) }} séries</span
            >
        </div>
        <UCard :ui="{ body: 'p-0 sm:p-0 divide-y divide-default' }">
            <div
                v-for="(we, i) in d.exercises"
                :key="i + '-' + we.exercise_id"
                class="flex items-start gap-3 p-4"
                draggable="true"
                :class="{ 'opacity-50': dragFrom === i }"
                @dragstart="dragFrom = i"
                @dragover.prevent
                @drop="onDrop(i)"
                @dragend="dragFrom = null"
            >
                <div class="flex flex-col">
                    <UButton
                        icon="i-lucide-chevron-up"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        :disabled="i === 0"
                        aria-label="Monter"
                        @click="move(d.exercises, i, i - 1)"
                    />
                    <UButton
                        icon="i-lucide-chevron-down"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        :disabled="i === d.exercises.length - 1"
                        aria-label="Descendre"
                        @click="move(d.exercises, i, i + 1)"
                    />
                </div>
                <button
                    class="thumb thumb-lg"
                    :aria-label="'Voir ' + ex(we.exercise_id)?.name"
                    @click="shown = ex(we.exercise_id) ?? null"
                >
                    <ExerciseImage
                        :exercise="ex(we.exercise_id)"
                        :animated="false"
                        pose="end"
                    />
                </button>
                <div class="min-w-0 flex-1 space-y-3">
                    <div>
                        <p class="font-medium">
                            {{ ex(we.exercise_id)?.name }}
                        </p>
                        <p class="text-xs text-muted">
                            {{ ex(we.exercise_id)?.primary_muscle }} ·
                            {{ ex(we.exercise_id)?.equipment }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <UFormField label="Séries" size="sm">
                            <UInput
                                inputmode="numeric"
                                class="w-20"
                                :model-value="we.sets"
                                @change="
                                    setNum(
                                        we,
                                        'sets',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                        true,
                                    )
                                "
                            />
                        </UFormField>
                        <UFormField
                            v-for="k in ['reps_min', 'reps_max'] as const"
                            :key="k"
                            size="sm"
                            :label="
                                (ex(we.exercise_id)?.measurement === 'time'
                                    ? 'Durée '
                                    : 'Reps ') +
                                (k === 'reps_min' ? 'min' : 'max')
                            "
                        >
                            <UInput
                                inputmode="numeric"
                                class="w-20"
                                :model-value="we[k]"
                                @change="
                                    setNum(
                                        we,
                                        k,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                        true,
                                    )
                                "
                            >
                                <template
                                    v-if="
                                        ex(we.exercise_id)?.measurement ===
                                        'time'
                                    "
                                    #trailing
                                    ><span class="text-xs text-muted"
                                        >s</span
                                    ></template
                                >
                            </UInput>
                        </UFormField>
                        <UFormField
                            v-if="
                                ex(we.exercise_id)?.load_type !== 'bodyweight'
                            "
                            size="sm"
                            :label="
                                ex(we.exercise_id)?.load_type ===
                                'two_dumbbells'
                                    ? 'Charge / haltère'
                                    : 'Charge'
                            "
                        >
                            <UInput
                                inputmode="decimal"
                                class="w-24"
                                :color="overMax(we) ? 'error' : undefined"
                                :highlight="overMax(we)"
                                :model-value="
                                    we.target_weight_kg != null
                                        ? fmt(
                                              we.target_weight_kg,
                                              we.target_weight_kg % 1 ? 1 : 0,
                                          )
                                        : ''
                                "
                                @change="
                                    setNum(
                                        we,
                                        'target_weight_kg',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            >
                                <template #trailing
                                    ><span class="text-xs text-muted"
                                        >kg</span
                                    ></template
                                >
                            </UInput>
                        </UFormField>
                        <UFormField label="Repos" size="sm">
                            <UInput
                                inputmode="numeric"
                                class="w-20"
                                :model-value="we.rest_seconds"
                                @change="
                                    setNum(
                                        we,
                                        'rest_seconds',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                        true,
                                    )
                                "
                            >
                                <template #trailing
                                    ><span class="text-xs text-muted"
                                        >s</span
                                    ></template
                                >
                            </UInput>
                        </UFormField>
                    </div>
                    <p v-if="overMax(we)" class="text-xs text-error">
                        Au-delà de ton matériel :
                        {{
                            fmtKg(maxLoadFor(ex(we.exercise_id)!.load_type, p))
                        }}
                        kg max{{
                            ex(we.exercise_id)!.load_type === 'two_dumbbells'
                                ? ' par haltère'
                                : ''
                        }}.
                    </p>
                    <p
                        v-if="we.reps_min > we.reps_max"
                        class="text-xs text-error"
                    >
                        Le minimum dépasse le maximum.
                    </p>
                </div>
                <UButton
                    icon="i-lucide-trash-2"
                    color="neutral"
                    variant="ghost"
                    :aria-label="'Retirer ' + ex(we.exercise_id)?.name"
                    @click="d.exercises.splice(i, 1)"
                />
            </div>
            <UEmpty
                v-if="!d.exercises.length"
                variant="naked"
                icon="i-lucide-dumbbell"
                title="Aucun exercice. Ajoute-en un depuis la bibliothèque."
            />
        </UCard>
        <p v-if="errors.exercises" class="text-sm text-error">
            {{ errors.exercises }}
        </p>
        <UButton
            icon="i-lucide-plus"
            color="neutral"
            variant="outline"
            @click="picking = true"
            >Ajouter un exercice</UButton
        >
    </section>

    <section v-if="d.type === 'run'" class="space-y-3">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-semibold">Déroulé</h2>
            <span class="text-sm text-muted tabular-nums"
                >{{ runMin }} min · ≈ {{ fmt(estRunKm(d.segments)) }} km</span
            >
        </div>
        <UCard><RunTimeline :segments="d.segments" /></UCard>
        <UCard :ui="{ body: 'py-1 sm:py-1 divide-y divide-default' }">
            <template v-for="(s, i) in d.segments" :key="i">
                <div v-if="s.kind === 'repeat'" class="py-3">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2 text-sm">
                            <UBadge variant="subtle">Bloc fractionné</UBadge>
                            <span class="text-muted">Répéter</span>
                            <UInputNumber
                                :model-value="s.repeat_count"
                                :min="1"
                                :max="20"
                                size="sm"
                                class="w-28"
                                aria-label="Nombre de cycles"
                                @update:model-value="
                                    (v) =>
                                        ((s as RepeatBlock).repeat_count =
                                            Math.max(
                                                1,
                                                Math.min(
                                                    20,
                                                    Math.round(v ?? 1),
                                                ),
                                            ))
                                "
                            />
                            <span class="text-muted">fois</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <UButton
                                icon="i-lucide-chevron-up"
                                color="neutral"
                                variant="ghost"
                                size="xs"
                                :disabled="i === 0"
                                aria-label="Monter le bloc"
                                @click="move(d.segments, i, i - 1)"
                            />
                            <UButton
                                icon="i-lucide-chevron-down"
                                color="neutral"
                                variant="ghost"
                                size="xs"
                                :disabled="i === d.segments.length - 1"
                                aria-label="Descendre le bloc"
                                @click="move(d.segments, i, i + 1)"
                            />
                            <UButton
                                icon="i-lucide-plus"
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                @click="s.children.push(newStep())"
                                >Phase</UButton
                            >
                            <UButton
                                icon="i-lucide-trash-2"
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                aria-label="Supprimer le bloc"
                                @click="d.segments.splice(i, 1)"
                            />
                        </div>
                    </div>
                    <div class="mt-2 border-l-2 border-primary/40 pl-4">
                        <StepEditor
                            v-for="(st, j) in s.children"
                            :key="j"
                            :step="st"
                            :id="`sg-${i}-${j}`"
                            :first="j === 0"
                            :last="j === s.children.length - 1"
                            :max-kmh="p.treadmill_max_kmh"
                            @up="move(s.children, j, j - 1)"
                            @down="move(s.children, j, j + 1)"
                            @remove="s.children.splice(j, 1)"
                        />
                    </div>
                </div>
                <StepEditor
                    v-else
                    :step="s"
                    :id="`sg-${i}`"
                    :first="i === 0"
                    :last="i === d.segments.length - 1"
                    :max-kmh="p.treadmill_max_kmh"
                    @up="move(d.segments, i, i - 1)"
                    @down="move(d.segments, i, i + 1)"
                    @remove="d.segments.splice(i, 1)"
                />
            </template>
        </UCard>
        <p v-if="errors.segments" class="text-sm text-error">
            {{ errors.segments }}
        </p>
        <div class="flex gap-2">
            <UButton
                icon="i-lucide-plus"
                color="neutral"
                variant="outline"
                @click="d.segments.push(newStep())"
                >Ajouter une phase</UButton
            >
            <UButton
                icon="i-lucide-plus"
                color="neutral"
                variant="ghost"
                @click="addGroup"
                >Bloc fractionné</UButton
            >
        </div>
    </section>

    <div
        class="sticky bottom-[calc(4.25rem+env(safe-area-inset-bottom))] z-30 -mx-4 flex items-center justify-end gap-3 border-t border-default bg-default/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:p-0 lg:backdrop-blur-none"
    >
        <span v-if="dirty" class="text-sm text-muted"
            >Modifications non enregistrées</span
        >
        <UButton size="lg" icon="i-lucide-check" :loading="saving" @click="save"
            >Enregistrer</UButton
        >
    </div>

    <ExercisePicker
        v-if="picking"
        :exercises="exercises"
        :selected="d.exercises.map((e) => e.exercise_id)"
        @close="picking = false"
        @pick="pick"
        @create="
            picking = false;
            creating = true;
        "
    />
    <ExerciseFormSheet
        v-if="creating"
        @close="creating = false"
        @saved="created"
    />
    <ExerciseSheet v-if="shown" :exercise="shown" @close="shown = null" />
</template>
