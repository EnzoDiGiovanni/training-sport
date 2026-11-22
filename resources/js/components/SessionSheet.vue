<script setup lang="ts">
// Détail modifiable d'une séance réalisée (historique, accueil, progression).
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import EffortRating from '@/components/EffortRating.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Sheet from '@/components/Sheet.vue';
import TypeChip from '@/components/TypeChip.vue';
import {
    cap,
    dateLong,
    fmt,
    fmtClock,
    fmtInt,
    fmtKg,
    num,
    parseClock,
} from '@/composables/useFormat';
import type { Exercise, Profile, Session } from '@/types';

const props = defineProps<{
    session: Session;
    exercises: Exercise[];
    profile: Profile;
}>();
const emit = defineEmits<{ close: [] }>();
const s = props.session;
const f = reactive({
    date: s.date,
    duration: fmtClock(s.duration_seconds),
    rpe: s.rpe,
    comment: s.comment ?? '',
    activity: s.activity ?? '',
    distance: s.run ? fmt(s.run.distance_km, 2) : '',
    max: s.run?.max_speed_kmh != null ? fmt(s.run.max_speed_kmh, 1) : '',
    incline:
        s.run?.incline_pct != null
            ? fmt(s.run.incline_pct, s.run.incline_pct % 1 ? 1 : 0)
            : '',
    sets: s.exercises.flatMap((e) =>
        e.sets
            .filter((x) => x.is_completed)
            .map((x) => ({
                id: x.id!,
                item: e.id,
                kg: fmtKg(x.weight_kg),
                reps: String(x.reps),
            })),
    ),
});
const errors = ref<Record<string, string>>({});
const confirmDel = ref(false);
const avg = computed(() => {
    const sec = parseClock(f.duration),
        km = num(f.distance);
    return sec > 0 && km > 0 ? km / (sec / 3600) : NaN;
});
const exImg = (id: number | null) =>
    props.exercises.find((e) => e.id === id) ?? null;

function save() {
    router.put(
        `/entrainements/${s.id}`,
        {
            date: f.date,
            duration: f.duration,
            rpe: f.rpe,
            comment: f.comment,
            activity: f.activity || null,
            distance_km: s.run ? num(f.distance) : null,
            max_speed_kmh: f.max ? num(f.max) : null,
            incline_pct: f.incline ? num(f.incline) : null,
            sets: f.sets.map((x) => ({
                id: x.id,
                weight_kg: num(x.kg) || 0,
                reps: Math.round(num(x.reps)) || 0,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => emit('close'),
            onError: (e) => (errors.value = e),
        },
    );
}
const destroy = () =>
    router.delete(`/entrainements/${s.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
</script>

<template>
    <Sheet
        :wide="s.type === 'strength'"
        :title="s.name"
        :description="cap(dateLong(s.date))"
        @close="emit('close')"
    >
        <template #actions><TypeChip :type="s.type" /></template>
        <div class="grid grid-cols-2 gap-4">
            <UFormField label="Date" :error="errors.date">
                <UInput v-model="f.date" type="date" class="w-full" />
            </UFormField>
            <UFormField label="Durée" :error="errors.duration">
                <UInput v-model="f.duration" class="w-full tabular-nums">
                    <template #trailing
                        ><span class="text-xs text-muted">mm:ss</span></template
                    >
                </UInput>
            </UFormField>
        </div>
        <template v-if="s.type === 'run' && s.run">
            <div class="grid grid-cols-3 gap-4">
                <UFormField label="Distance" :error="errors.distance_km">
                    <UInput
                        v-model="f.distance"
                        inputmode="decimal"
                        class="w-full"
                    >
                        <template #trailing
                            ><span class="text-xs text-muted"
                                >km</span
                            ></template
                        >
                    </UInput>
                </UFormField>
                <UFormField label="Vitesse max" :error="errors.max_speed_kmh">
                    <UInput v-model="f.max" inputmode="decimal" class="w-full">
                        <template #trailing
                            ><span class="text-xs text-muted"
                                >km/h</span
                            ></template
                        >
                    </UInput>
                </UFormField>
                <UFormField label="Inclinaison" :error="errors.incline_pct">
                    <UInput
                        v-model="f.incline"
                        inputmode="decimal"
                        class="w-full"
                    >
                        <template #trailing
                            ><span class="text-xs text-muted">%</span></template
                        >
                    </UInput>
                </UFormField>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-muted">
                <span
                    >Vitesse moyenne calculée :
                    <b class="text-highlighted tabular-nums"
                        >{{ fmt(avg, 2) }} km/h</b
                    ></span
                >
                <span v-if="s.run.cycles_completed != null"
                    >Cycles :
                    <b class="text-highlighted tabular-nums"
                        >{{ s.run.cycles_completed
                        }}{{
                            s.run.cycles_planned
                                ? ' / ' + s.run.cycles_planned
                                : ''
                        }}</b
                    ></span
                >
            </div>
        </template>
        <template v-if="s.type === 'strength'">
            <div v-for="it in s.exercises" :key="it.id" class="space-y-2">
                <div class="flex items-center gap-3">
                    <span class="thumb"
                        ><ExerciseImage
                            :exercise="
                                exImg(it.exercise_id) ?? {
                                    name: it.name,
                                    illustration_key: it.illustration_key,
                                    image_url: it.image_url,
                                }
                            "
                            :animated="false"
                            pose="end" /></span
                    ><span class="flex-1 font-medium">{{ it.name }}</span>
                </div>
                <table class="sets">
                    <thead>
                        <tr>
                            <th>Série</th>
                            <th>
                                {{ it.load_type === 'bodyweight' ? '' : 'kg' }}
                            </th>
                            <th>
                                {{
                                    it.measurement === 'time'
                                        ? 'Secondes'
                                        : 'Reps'
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(x, j) in f.sets.filter(
                                (y) => y.item === it.id,
                            )"
                            :key="x.id"
                        >
                            <td>{{ j + 1 }}</td>
                            <td>
                                <span
                                    v-if="it.load_type === 'bodyweight'"
                                    class="text-muted"
                                    >PDC</span
                                ><UInput
                                    v-else
                                    v-model="x.kg"
                                    size="sm"
                                    inputmode="decimal"
                                    class="w-24"
                                    :aria-label="'Charge série ' + (j + 1)"
                                />
                            </td>
                            <td>
                                <UInput
                                    v-model="x.reps"
                                    size="sm"
                                    inputmode="numeric"
                                    class="w-24"
                                    :aria-label="'Répétitions série ' + (j + 1)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="it.note" class="text-xs text-muted">
                    Note : {{ it.note }}
                </p>
            </div>
            <p class="text-xs text-muted tabular-nums">
                Volume enregistré : {{ fmtInt(s.volume) }} kg
            </p>
        </template>
        <UFormField v-if="s.type === 'recovery'" label="Activité">
            <UInput v-model="f.activity" class="w-full" />
        </UFormField>
        <UFormField label="Ressenti"
            ><EffortRating v-model="f.rpe"
        /></UFormField>
        <UFormField label="Commentaire">
            <UTextarea v-model="f.comment" class="w-full" />
        </UFormField>
        <p v-if="s.type === 'run' && s.run" class="text-xs text-muted">
            Dépense estimée : ≈
            {{ fmtInt((profile.weight ?? 0) * (num(f.distance) || 0)) }}
            kcal (estimation indicative).
        </p>
        <p class="text-xs text-dimmed">
            Cette séance conserve les valeurs du programme au moment où elle a
            été réalisée. Modifier le programme ne la change pas.
        </p>
        <template #footer>
            <template v-if="confirmDel">
                <span class="mr-auto self-center text-sm text-muted"
                    >Supprimer définitivement ?</span
                >
                <UButton
                    color="neutral"
                    variant="outline"
                    @click="confirmDel = false"
                    >Non</UButton
                >
                <UButton color="error" @click="destroy">Supprimer</UButton>
            </template>
            <template v-else>
                <UButton
                    icon="i-lucide-trash-2"
                    color="error"
                    variant="ghost"
                    class="mr-auto"
                    @click="confirmDel = true"
                    >Supprimer</UButton
                >
                <UButton
                    color="neutral"
                    variant="outline"
                    @click="emit('close')"
                    >Annuler</UButton
                >
                <UButton @click="save">Enregistrer</UButton>
            </template>
        </template>
    </Sheet>
</template>
