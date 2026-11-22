<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import EffortRating from '@/components/EffortRating.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import {
    cap,
    dateLong,
    dateShort,
    fmt,
    fmtClock,
    fmtInt,
} from '@/composables/useFormat';
import { itemVolume, setsSummary } from '@/lib/domain';
import type { Session } from '@/types';

const props = defineProps<{
    session: Session;
    records: string[];
    previous: Session | null;
    previousVolumes: Record<number, number | null>;
    kcal: number;
}>();
const s = props.session;
const sessionError = computed(
    () => (usePage().props.errors as Record<string, string>).session,
);
const form = useForm({ rpe: s.rpe, comment: s.comment ?? '' });
const tiles = computed<[string, string | number][]>(() => {
    if (s.type === 'strength') {
        const sets = s.exercises.reduce((a, e) => a + e.sets.length, 0);
        const reps = s.exercises.reduce(
            (a, e) =>
                a +
                (e.measurement === 'time'
                    ? 0
                    : e.sets.reduce((b, x) => b + x.reps, 0)),
            0,
        );
        return [
            ['Durée', fmtClock(s.duration_seconds)],
            [
                'Volume',
                fmtInt(s.exercises.reduce((a, e) => a + itemVolume(e), 0)) +
                    ' kg',
            ],
            ['Séries', sets],
            ['Répétitions', reps],
        ];
    }
    return [
        ['Durée', fmtClock(s.duration_seconds)],
        ['Distance', fmt(s.run!.distance_km, 2) + ' km'],
        ['Vitesse moy.', fmt(s.run!.average_speed_kmh, 2) + ' km/h'],
        [
            'Vitesse max',
            s.run!.max_speed_kmh ? fmt(s.run!.max_speed_kmh, 1) + ' km/h' : '–',
        ],
    ];
});
const runDeltas = computed<[string, number, string, number][]>(() => {
    const p = props.previous;
    if (s.type !== 'run' || !p?.run || !s.run) return [];
    return [
        ['Distance', s.run.distance_km - p.run.distance_km, ' km', 2],
        [
            'Vitesse moy.',
            (s.run.average_speed_kmh ?? 0) - (p.run.average_speed_kmh ?? 0),
            ' km/h',
            2,
        ],
        ['Durée', (s.duration_seconds - p.duration_seconds) / 60, ' min', 1],
    ];
});
const save = () =>
    form.post('/seance-en-cours/terminer', {
        onSuccess: () =>
            ['chrono', 'runform', 'rest'].forEach((k) =>
                localStorage.removeItem(`recompo-${k}-${s.id}`),
            ),
    });
</script>

<template>
    <Head title="Récapitulatif" />
    <UCard class="mx-auto w-full max-w-2xl" :ui="{ body: 'space-y-6 sm:p-8' }">
        <div class="flex flex-col items-center gap-2 text-center">
            <span
                class="grid size-14 place-items-center rounded-full bg-primary/10 text-primary"
                ><UIcon name="i-lucide-check" class="size-7"
            /></span>
            <h1 class="text-2xl font-semibold">Séance terminée</h1>
            <p class="text-muted">{{ s.name }} · {{ cap(dateLong(s.date)) }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                v-for="[k, v] in tiles"
                :key="k"
                class="rounded-lg bg-elevated p-3.5"
            >
                <p class="text-xs text-muted">{{ k }}</p>
                <p class="text-lg font-semibold tabular-nums">{{ v }}</p>
            </div>
        </div>
        <div v-if="records.length" class="space-y-2">
            <UAlert
                v-for="p in records"
                :key="p"
                color="warning"
                variant="subtle"
                icon="i-lucide-trophy"
                :title="p"
            />
        </div>

        <div
            v-if="s.type === 'strength'"
            class="divide-y divide-default rounded-lg border border-default"
        >
            <div
                v-for="it in s.exercises"
                :key="it.id"
                class="flex items-center gap-3 px-4 py-3"
            >
                <span class="thumb"
                    ><ExerciseImage
                        :exercise="{
                            name: it.name,
                            illustration_key: it.illustration_key,
                            image_url: it.image_url,
                        }"
                        :animated="false"
                        pose="end"
                /></span>
                <span class="flex min-w-0 flex-1 flex-col"
                    ><span class="text-sm font-medium">{{ it.name }}</span
                    ><span class="text-xs text-muted tabular-nums">{{
                        setsSummary(it)
                    }}</span></span
                >
                <span
                    v-if="
                        previousVolumes[it.id] != null &&
                        it.load_type !== 'bodyweight'
                    "
                    class="text-sm tabular-nums"
                    :class="
                        itemVolume(it) - previousVolumes[it.id]! >= 0
                            ? 'text-success'
                            : 'text-warning'
                    "
                    >{{
                        itemVolume(it) - previousVolumes[it.id]! >= 0
                            ? '+'
                            : ''
                    }}{{
                        fmtInt(itemVolume(it) - previousVolumes[it.id]!)
                    }}
                    kg</span
                >
            </div>
            <UEmpty
                v-if="!s.exercises.length"
                variant="naked"
                title="Aucune série validée."
            />
        </div>
        <div
            v-else-if="runDeltas.length"
            class="space-y-3 rounded-lg border border-default p-4"
        >
            <p class="text-sm font-semibold">
                Comparé à la dernière fois ({{ dateShort(previous!.date) }})
            </p>
            <div class="grid grid-cols-3 gap-3">
                <div v-for="[k, v, u, d] in runDeltas" :key="k">
                    <p class="text-xs text-muted">{{ k }}</p>
                    <p
                        class="font-semibold tabular-nums"
                        :class="v >= 0 ? 'text-success' : 'text-warning'"
                    >
                        {{ v >= 0 ? '+' : '' }}{{ fmt(v, d) }}{{ u }}
                    </p>
                </div>
            </div>
        </div>

        <template v-if="s.type === 'strength'">
            <UFormField label="Ressenti"
                ><EffortRating v-model="form.rpe"
            /></UFormField>
            <UFormField label="Commentaire">
                <UTextarea
                    v-model="form.comment"
                    placeholder="Comment s’est passée la séance ?"
                    class="w-full"
                />
            </UFormField>
        </template>
        <p v-else-if="s.rpe || s.comment" class="text-sm text-muted">
            {{ s.comment }}
        </p>
        <p v-if="sessionError" class="text-sm text-error">{{ sessionError }}</p>
        <p class="text-xs text-dimmed">
            Dépense énergétique estimée : ≈ {{ fmtInt(kcal) }} kcal. Estimation
            indicative, non mesurée.
        </p>
        <div class="flex flex-wrap justify-end gap-2">
            <UButton to="/seance-en-cours" color="neutral" variant="ghost">{{
                s.type === 'strength'
                    ? 'Reprendre la séance'
                    : 'Modifier les données'
            }}</UButton>
            <UButton
                size="lg"
                icon="i-lucide-check"
                :loading="form.processing"
                @click="save"
                >Enregistrer</UButton
            >
        </div>
    </UCard>
</template>
