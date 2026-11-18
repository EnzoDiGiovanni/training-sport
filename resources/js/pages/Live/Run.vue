<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import EffortRating from '@/components/EffortRating.vue';
import ExerciseIllustration from '@/components/ExerciseIllustration.vue';
import Icon from '@/components/Icon.vue';
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
    <div class="live-head">
        <div class="inner">
            <Link
                href="/"
                class="btn btn-ghost btn-icon"
                aria-label="Retour à l’accueil (la séance continue)"
                ><Icon name="back"
            /></Link>
            <div class="stack grow">
                <span style="font-weight: 600">{{ session.name }}</span
                ><span class="faint" style="font-size: 12.5px"
                    >Course sur tapis · {{ runMinutes(segments) }} min
                    prévues</span
                >
            </div>
            <button class="btn btn-ghost" @click="abandon">Abandonner</button>
        </div>
    </div>
    <div class="run-grid">
        <div class="card card-pad stack gap-20">
            <div
                class="stack gap-8"
                style="align-items: center; padding: 12px 0 4px"
            >
                <span class="faint" style="font-size: 12.5px">Chronomètre</span
                ><span class="chrono" role="timer">{{ fmtClock(el) }}</span>
            </div>
            <div class="row" style="justify-content: center">
                <button
                    v-if="chrono.state.running"
                    class="btn btn-lg"
                    @click="chrono.pause"
                >
                    <Icon name="pause" />Pause
                </button>
                <button
                    v-else
                    class="btn btn-primary btn-lg"
                    @click="chrono.start"
                >
                    <Icon name="play" />{{ el > 0 ? 'Reprendre' : 'Démarrer' }}
                </button>
                <button
                    class="btn btn-lg btn-ghost"
                    :disabled="el <= 0"
                    aria-label="Remettre le chrono à zéro"
                    @click="chrono.reset"
                >
                    <Icon name="reset" />
                </button>
            </div>
            <div v-if="current" class="now-seg">
                <span
                    class="sw"
                    :style="{
                        width: '6px',
                        height: '44px',
                        borderRadius: '3px',
                        background: KIND_COLOR[current.step.kind],
                    }"
                ></span>
                <div class="stack grow">
                    <span class="faint" style="font-size: 12px"
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
                    <span style="font-weight: 600">{{
                        current.step.label
                    }}</span>
                    <span class="faint num" style="font-size: 12.5px">{{
                        current.next
                            ? 'Ensuite : ' +
                              current.next.label +
                              ' à ' +
                              speedLabel(current.next)
                            : 'Dernière phase'
                    }}</span>
                </div>
                <div class="stack" style="align-items: flex-end">
                    <span class="sp">{{
                        speedLabel(current.step).replace(' km/h', '')
                    }}</span
                    ><span class="faint num" style="font-size: 12px"
                        >km/h · reste {{ fmtClock(current.left) }}</span
                    >
                </div>
            </div>
            <div v-else class="now-seg">
                <div class="grow">
                    <span style="font-weight: 600">{{
                        el > 0 ? 'Programme terminé' : 'Prêt à démarrer'
                    }}</span
                    ><br />
                    <span class="faint" style="font-size: 12.5px">{{
                        el > 0
                            ? 'Saisis les données affichées par le tapis puis enregistre.'
                            : 'Lance le chrono quand tu démarres le tapis.'
                    }}</span>
                </div>
            </div>
            <RunTimeline :segments="segments" :marker-min="el / 60" />
            <div
                class="illo"
                style="max-width: 260px; align-self: center; width: 100%"
            >
                <ExerciseIllustration
                    illustration-key="run"
                    :animated="chrono.state.running"
                    label="Course sur tapis"
                />
            </div>
        </div>

        <form class="card card-pad stack gap-16" @submit.prevent="save">
            <div class="section-head">
                <h2>Données de la séance</h2>
                <span class="faint" style="font-size: 12.5px"
                    >Relevées sur le tapis</span
                >
            </div>
            <div class="field">
                <label for="rf-dur">Durée totale</label>
                <div class="row gap-8">
                    <div class="input-unit grow">
                        <input
                            id="rf-dur"
                            v-model="f.duration"
                            class="input num"
                            :class="{ 'is-error': errors.duration }"
                            placeholder="mm:ss"
                        /><span>mm:ss</span>
                    </div>
                    <button
                        type="button"
                        class="btn"
                        :disabled="el <= 0"
                        @click="useChrono"
                    >
                        Utiliser le chrono
                    </button>
                </div>
                <span v-if="errors.duration" class="err">{{
                    errors.duration
                }}</span>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="rf-dist">Distance</label>
                    <div class="input-unit">
                        <input
                            id="rf-dist"
                            v-model="f.distance"
                            class="input num"
                            :class="{ 'is-error': errors.distance_km }"
                            inputmode="decimal"
                            placeholder="4,25"
                        /><span>km</span>
                    </div>
                    <span v-if="errors.distance_km" class="err">{{
                        errors.distance_km
                    }}</span>
                </div>
                <div class="field">
                    <span class="label">Vitesse moyenne</span>
                    <div
                        class="input num"
                        style="
                            display: flex;
                            align-items: center;
                            background: var(--surface-2);
                            border-color: var(--border);
                        "
                        aria-live="polite"
                    >
                        <template v-if="!isNaN(calc.avg)"
                            >{{ fmt(calc.avg, 2) }} km/h</template
                        ><span v-else class="faint" style="font-size: 13px"
                            >calculée automatiquement</span
                        >
                    </div>
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="rf-max">Vitesse max</label>
                    <div class="input-unit">
                        <input
                            id="rf-max"
                            v-model="f.max"
                            class="input num"
                            :class="{ 'is-error': errors.max_speed_kmh }"
                            inputmode="decimal"
                            :placeholder="String(profile.treadmill_max_kmh)"
                        /><span>km/h</span>
                    </div>
                    <span v-if="errors.max_speed_kmh" class="err">{{
                        errors.max_speed_kmh
                    }}</span>
                </div>
                <div class="field">
                    <label for="rf-inc">Inclinaison</label>
                    <div class="input-unit">
                        <input
                            id="rf-inc"
                            v-model="f.incline"
                            class="input num"
                            :class="{ 'is-error': errors.incline_pct }"
                            inputmode="decimal"
                        /><span>%</span>
                    </div>
                    <span v-if="errors.incline_pct" class="err">{{
                        errors.incline_pct
                    }}</span>
                </div>
            </div>
            <div v-if="block" class="field">
                <label for="rf-cyc">Cycles effectués</label>
                <div class="input-unit">
                    <input
                        id="rf-cyc"
                        v-model="f.cycles"
                        class="input num"
                        :class="{ 'is-error': errors.cycles_completed }"
                        inputmode="numeric"
                    /><span>/ {{ block.repeat_count }}</span>
                </div>
                <span v-if="errors.cycles_completed" class="err">{{
                    errors.cycles_completed
                }}</span>
            </div>
            <div class="field">
                <span class="label">Ressenti</span
                ><EffortRating v-model="f.rpe" />
            </div>
            <div class="field">
                <label for="rf-comment">Commentaire</label
                ><textarea
                    id="rf-comment"
                    v-model="f.comment"
                    class="input"
                    placeholder="Sensations, fréquence cardiaque, conditions…"
                ></textarea>
            </div>
            <div class="notice">
                <Icon name="flame" /><span
                    ><template v-if="!isNaN(kcal)"
                        >Dépense estimée :
                        <b class="num">≈ {{ fmtInt(kcal) }} kcal</b>. </template
                    >Estimation indicative (environ 1 kcal par kg et par km),
                    marge d’erreur de ±20 % ou plus.</span
                >
            </div>
            <button class="btn btn-primary btn-lg btn-block" :disabled="saving">
                <Icon name="check" />Enregistrer la séance
            </button>
        </form>
    </div>
</template>
