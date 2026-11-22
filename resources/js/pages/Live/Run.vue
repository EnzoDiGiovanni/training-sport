<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import EffortRating from '@/components/EffortRating.vue';
import ExerciseIllustration from '@/components/ExerciseIllustration.vue';
import RunTimeline from '@/components/RunTimeline.vue';
import { useChronometer } from '@/composables/useChronometer';
import {
    fmt,
    fmtClock,
    fmtInt,
    num,
    parseClock,
} from '@/composables/useFormat';
import { flatSteps, KIND_COLOR, runMinutes, speedLabel } from '@/lib/domain';
import type { Profile, RepeatBlock, Segment, Session } from '@/types';

const props = defineProps<{
    session: Session & { started_at: string };
    segments: Segment[];
    profile: Profile;
}>();
const chrono = useChronometer(props.session.id);
const steps = flatSteps(props.segments);
const block = props.segments.find((s) => s.kind === 'repeat') as
    | RepeatBlock
    | undefined;
const firstRun = steps.find((s) => s.kind === 'run');

// Formulaire conservé localement (rechargement de page) jusqu'à l'enregistrement.
const key = 'recompo-runform-' + props.session.id;
const r = props.session.run;
const f = reactive(
    JSON.parse(localStorage.getItem(key) || 'null') ?? {
        duration: props.session.duration_seconds
            ? fmtClock(props.session.duration_seconds)
            : '',
        distance: r ? fmt(r.distance_km, 2) : '',
        max: r?.max_speed_kmh != null ? fmt(r.max_speed_kmh, 1) : '',
        incline:
            r?.incline_pct != null
                ? String(r.incline_pct)
                : firstRun
                  ? String(firstRun.incline_pct)
                  : '',
        cycles:
            r?.cycles_completed != null
                ? String(r.cycles_completed)
                : block
                  ? String(block.repeat_count)
                  : '',
        rpe: props.session.rpe,
        comment: props.session.comment ?? '',
    },
);
watch(f, () => localStorage.setItem(key, JSON.stringify(f)), { deep: true });

const el = chrono.elapsed;
const current = computed(() => {
    const m = el.value / 60;
    let t = 0;
    for (let i = 0; i < steps.length; i++) {
        if (m < t + +steps[i].duration_min)
            return {
                step: steps[i],
                left: (t + +steps[i].duration_min - m) * 60,
                next: steps[i + 1],
            };
        t += +steps[i].duration_min;
    }
    return null;
});
// Vitesse moyenne affichée en direct (même formule que le serveur).
const calc = computed(() => {
    const sec = parseClock(f.duration),
        km = num(f.distance);
    return { sec, km, avg: sec > 0 && km > 0 ? km / (sec / 3600) : NaN };
});
const kcal = computed(() =>
    calc.value.km > 0 ? (props.profile.weight ?? 0) * calc.value.km : NaN,
);

const errors = ref<Record<string, string>>({});
const saving = ref(false);
function useChrono() {
    f.duration = fmtClock(el.value);
}
function save() {
    // Contrôle client immédiat (le serveur refait toutes les validations).
    const e: Record<string, string> = {};
    if (!f.duration) e.duration = 'Indique la durée (ou utilise le chrono).';
    else if (isNaN(calc.value.sec) || calc.value.sec <= 0)
        e.duration = 'Format attendu : mm:ss, par exemple 30:00.';
    if (!f.distance)
        e.distance_km = 'Indique la distance affichée par le tapis.';
    errors.value = e;
    if (Object.keys(e).length) return;
    chrono.pause();
    saving.value = true;
    router.post(
        '/seance-en-cours/course',
        {
            duration: f.duration,
            distance_km: num(f.distance),
            max_speed_kmh: f.max ? num(f.max) : null,
            incline_pct: f.incline !== '' ? num(f.incline) : null,
            cycles_completed:
                block && f.cycles !== '' ? Math.round(num(f.cycles)) : null,
            rpe: f.rpe,
            comment: f.comment,
        },
        {
            onError: (er) => (errors.value = er),
            onFinish: () => (saving.value = false),
        },
    );
}
function abandon() {
    chrono.clear();
    localStorage.removeItem(key);
    router.delete('/seance-en-cours');
}
</script>

<template>
    <Head :title="session.name" />
    <div class="flex items-center gap-3">
        <UButton
            to="/"
            icon="i-lucide-chevron-left"
            color="neutral"
            variant="ghost"
            aria-label="Retour à l’accueil (la séance continue)"
        />
        <div class="min-w-0 flex-1">
            <p class="truncate font-semibold">{{ session.name }}</p>
            <p class="text-xs text-muted">
                Course sur tapis · {{ runMinutes(segments) }} min prévues
            </p>
        </div>
        <UButton color="error" variant="ghost" @click="abandon"
            >Abandonner</UButton
        >
    </div>
    <div class="grid items-start gap-6 lg:grid-cols-2">
        <UCard :ui="{ body: 'flex flex-col gap-6' }">
            <div class="flex flex-col items-center gap-1 pt-2">
                <span class="text-sm text-muted">Chronomètre</span
                ><span
                    class="text-6xl font-semibold tracking-tight tabular-nums"
                    role="timer"
                    >{{ fmtClock(el) }}</span
                >
            </div>
            <div class="flex justify-center gap-2">
                <UButton
                    v-if="chrono.state.running"
                    size="xl"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-pause"
                    @click="chrono.pause"
                    >Pause</UButton
                >
                <UButton
                    v-else
                    size="xl"
                    icon="i-lucide-play"
                    @click="chrono.start"
                    >{{ el > 0 ? 'Reprendre' : 'Démarrer' }}</UButton
                >
                <UButton
                    size="xl"
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-rotate-ccw"
                    :disabled="el <= 0"
                    aria-label="Remettre le chrono à zéro"
                    @click="chrono.reset"
                />
            </div>
            <div
                v-if="current"
                class="flex items-center gap-3 rounded-lg bg-elevated p-4"
            >
                <span
                    class="h-11 w-1.5 flex-none rounded-full"
                    :style="{ background: KIND_COLOR[current.step.kind] }"
                ></span>
                <div class="flex min-w-0 flex-1 flex-col">
                    <span class="text-xs text-muted"
                        >{{
                            current.step.cycle
                                ? 'Cycle ' +
                                  current.step.cycle +
                                  ' / ' +
                                  current.step.of +
                                  ' · '
                                : ''
                        }}Phase actuelle</span
                    >
                    <span class="font-semibold">{{ current.step.label }}</span>
                    <span class="text-xs text-muted tabular-nums">{{
                        current.next
                            ? 'Ensuite : ' +
                              current.next.label +
                              ' à ' +
                              speedLabel(current.next)
                            : 'Dernière phase'
                    }}</span>
                </div>
                <div class="flex flex-col items-end">
                    <span class="text-3xl font-semibold tabular-nums">{{
                        speedLabel(current.step).replace(' km/h', '')
                    }}</span
                    ><span class="text-xs text-muted tabular-nums"
                        >km/h · reste {{ fmtClock(current.left) }}</span
                    >
                </div>
            </div>
            <div v-else class="rounded-lg bg-elevated p-4">
                <p class="font-semibold">
                    {{ el > 0 ? 'Programme terminé' : 'Prêt à démarrer' }}
                </p>
                <p class="text-sm text-muted">
                    {{
                        el > 0
                            ? 'Saisis les données affichées par le tapis puis enregistre.'
                            : 'Lance le chrono quand tu démarres le tapis.'
                    }}
                </p>
            </div>
            <RunTimeline :segments="segments" :marker-min="el / 60" />
            <div class="illo w-full max-w-64 self-center">
                <ExerciseIllustration
                    illustration-key="run"
                    :animated="chrono.state.running"
                    label="Course sur tapis"
                />
            </div>
        </UCard>

        <UCard>
            <template #header>
                <div class="flex items-baseline justify-between">
                    <h2 class="font-semibold">Données de la séance</h2>
                    <span class="text-xs text-muted"
                        >Relevées sur le tapis</span
                    >
                </div>
            </template>
            <form class="space-y-5" @submit.prevent="save">
                <UFormField label="Durée totale" :error="errors.duration">
                    <div class="flex gap-2">
                        <UInput
                            v-model="f.duration"
                            placeholder="mm:ss"
                            class="flex-1 tabular-nums"
                        >
                            <template #trailing
                                ><span class="text-xs text-muted"
                                    >mm:ss</span
                                ></template
                            >
                        </UInput>
                        <UButton
                            color="neutral"
                            variant="outline"
                            :disabled="el <= 0"
                            @click="useChrono"
                            >Utiliser le chrono</UButton
                        >
                    </div>
                </UFormField>
                <div class="grid grid-cols-2 gap-4">
                    <UFormField label="Distance" :error="errors.distance_km">
                        <UInput
                            v-model="f.distance"
                            inputmode="decimal"
                            placeholder="4,25"
                            class="w-full tabular-nums"
                        >
                            <template #trailing
                                ><span class="text-xs text-muted"
                                    >km</span
                                ></template
                            >
                        </UInput>
                    </UFormField>
                    <UFormField label="Vitesse moyenne" aria-live="polite">
                        <UInput
                            :model-value="
                                !isNaN(calc.avg)
                                    ? fmt(calc.avg, 2) + ' km/h'
                                    : ''
                            "
                            placeholder="calculée automatiquement"
                            disabled
                            class="w-full tabular-nums"
                        />
                    </UFormField>
                    <UFormField
                        label="Vitesse max"
                        :error="errors.max_speed_kmh"
                    >
                        <UInput
                            v-model="f.max"
                            inputmode="decimal"
                            :placeholder="String(profile.treadmill_max_kmh)"
                            class="w-full tabular-nums"
                        >
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
                            class="w-full tabular-nums"
                        >
                            <template #trailing
                                ><span class="text-xs text-muted"
                                    >%</span
                                ></template
                            >
                        </UInput>
                    </UFormField>
                </div>
                <UFormField
                    v-if="block"
                    label="Cycles effectués"
                    :error="errors.cycles_completed"
                >
                    <UInput
                        v-model="f.cycles"
                        inputmode="numeric"
                        class="w-full tabular-nums"
                    >
                        <template #trailing
                            ><span class="text-xs text-muted"
                                >/ {{ block.repeat_count }}</span
                            ></template
                        >
                    </UInput>
                </UFormField>
                <UFormField label="Ressenti"
                    ><EffortRating v-model="f.rpe"
                /></UFormField>
                <UFormField label="Commentaire">
                    <UTextarea
                        v-model="f.comment"
                        placeholder="Sensations, fréquence cardiaque, conditions…"
                        class="w-full"
                    />
                </UFormField>
                <UAlert
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-flame"
                    :description="
                        (!isNaN(kcal)
                            ? `Dépense estimée : ≈ ${fmtInt(kcal)} kcal. `
                            : '') +
                        'Estimation indicative (environ 1 kcal par kg et par km), marge d’erreur de ±20 % ou plus.'
                    "
                />
                <UButton
                    type="submit"
                    size="lg"
                    block
                    icon="i-lucide-check"
                    :loading="saving"
                    >Enregistrer la séance</UButton
                >
            </form>
        </UCard>
    </div>
</template>
