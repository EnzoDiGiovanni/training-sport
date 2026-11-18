<script setup lang="ts">
// Détail modifiable d'une séance réalisée (historique, accueil, progression).
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import EffortRating from '@/components/EffortRating.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Icon from '@/components/Icon.vue';
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
    <Sheet :wide="s.type === 'strength'" :label="s.name" @close="emit('close')">
        <div class="sheet-head">
            <div class="stack grow">
                <span class="faint" style="font-size: 12px">{{
                    cap(dateLong(s.date))
                }}</span>
                <h3 class="title-m">{{ s.name }}</h3>
            </div>
            <TypeChip :type="s.type" />
            <button
                class="btn btn-ghost btn-icon"
                aria-label="Fermer"
                @click="emit('close')"
            >
                <Icon name="x" />
            </button>
        </div>
        <div class="sheet-body">
            <div class="grid-2">
                <div class="field">
                    <label for="ss-date">Date</label
                    ><input
                        id="ss-date"
                        v-model="f.date"
                        type="date"
                        class="input"
                        :class="{ 'is-error': errors.date }"
                    /><span v-if="errors.date" class="err">{{
                        errors.date
                    }}</span>
                </div>
                <div class="field">
                    <label for="ss-dur">Durée</label>
                    <div class="input-unit">
                        <input
                            id="ss-dur"
                            v-model="f.duration"
                            class="input num"
                            :class="{ 'is-error': errors.duration }"
                        /><span>mm:ss</span>
                    </div>
                    <span v-if="errors.duration" class="err">{{
                        errors.duration
                    }}</span>
                </div>
            </div>
            <template v-if="s.type === 'run' && s.run">
                <div class="grid-3">
                    <div class="field">
                        <label for="ss-dist">Distance</label>
                        <div class="input-unit">
                            <input
                                id="ss-dist"
                                v-model="f.distance"
                                class="input num"
                                :class="{ 'is-error': errors.distance_km }"
                                inputmode="decimal"
                            /><span>km</span>
                        </div>
                        <span v-if="errors.distance_km" class="err">{{
                            errors.distance_km
                        }}</span>
                    </div>
                    <div class="field">
                        <label for="ss-max">Vitesse max</label>
                        <div class="input-unit">
                            <input
                                id="ss-max"
                                v-model="f.max"
                                class="input num"
                                :class="{ 'is-error': errors.max_speed_kmh }"
                                inputmode="decimal"
                            /><span>km/h</span>
                        </div>
                        <span v-if="errors.max_speed_kmh" class="err">{{
                            errors.max_speed_kmh
                        }}</span>
                    </div>
                    <div class="field">
                        <label for="ss-inc">Inclinaison</label>
                        <div class="input-unit">
                            <input
                                id="ss-inc"
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
                <div class="row wrapflex gap-16">
                    <span class="muted"
                        >Vitesse moyenne calculée :
                        <b class="num" style="color: var(--text)"
                            >{{ fmt(avg, 2) }} km/h</b
                        ></span
                    >
                    <span v-if="s.run.cycles_completed != null" class="muted"
                        >Cycles :
                        <b class="num" style="color: var(--text)"
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
                <div v-for="it in s.exercises" :key="it.id" class="stack gap-8">
                    <div class="row">
                        <span class="thumb" style="width: 52px; height: 40px"
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
                        ><span class="grow" style="font-weight: 500">{{
                            it.name
                        }}</span>
                    </div>
                    <table
                        class="sets"
                        style="
                            background: var(--bg);
                            border-radius: 10px;
                            overflow: hidden;
                        "
                    >
                        <thead>
                            <tr>
                                <th>Série</th>
                                <th>
                                    {{
                                        it.load_type === 'bodyweight'
                                            ? ''
                                            : 'kg'
                                    }}
                                </th>
                                <th>
                                    {{
                                        it.measurement === 'time'
                                            ? 'Secondes'
                                            : 'Reps'
                                    }}
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(x, j) in f.sets.filter(
                                    (y) => y.item === it.id,
                                )"
                                :key="x.id"
                            >
                                <td>
                                    <span class="set-num">{{ j + 1 }}</span>
                                </td>
                                <td>
                                    <span
                                        v-if="it.load_type === 'bodyweight'"
                                        class="faint"
                                        >PDC</span
                                    ><input
                                        v-else
                                        v-model="x.kg"
                                        class="cell-in"
                                        inputmode="decimal"
                                        :aria-label="'Charge série ' + (j + 1)"
                                    />
                                </td>
                                <td>
                                    <input
                                        v-model="x.reps"
                                        class="cell-in"
                                        inputmode="numeric"
                                        :aria-label="
                                            'Répétitions série ' + (j + 1)
                                        "
                                    />
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    <span v-if="it.note" class="faint" style="font-size: 12.5px"
                        >Note : {{ it.note }}</span
                    >
                </div>
                <span class="faint num" style="font-size: 12.5px"
                    >Volume enregistré : {{ fmtInt(s.volume) }} kg</span
                >
            </template>
            <div v-if="s.type === 'recovery'" class="field">
                <label for="ss-act">Activité</label
                ><input id="ss-act" v-model="f.activity" class="input" />
            </div>
            <div class="field">
                <span class="label">Ressenti</span
                ><EffortRating v-model="f.rpe" />
            </div>
            <div class="field">
                <label for="ss-com">Commentaire</label
                ><textarea
                    id="ss-com"
                    v-model="f.comment"
                    class="input"
                ></textarea>
            </div>
            <p
                v-if="s.type === 'run' && s.run"
                class="faint"
                style="font-size: 12.5px"
            >
                Dépense estimée : ≈
                {{ fmtInt((profile.weight ?? 0) * (num(f.distance) || 0)) }}
                kcal (estimation indicative).
            </p>
            <p class="faint" style="font-size: 12px">
                Cette séance conserve les valeurs du programme au moment où elle
                a été réalisée. Modifier le programme ne la change pas.
            </p>
        </div>
        <div class="sheet-foot">
            <template v-if="confirmDel">
                <span
                    class="muted grow"
                    style="font-size: 13px; align-self: center"
                    >Supprimer définitivement ?</span
                >
                <button class="btn" @click="confirmDel = false">Non</button
                ><button class="btn btn-danger" @click="destroy">
                    Supprimer
                </button>
            </template>
            <template v-else>
                <button
                    class="btn btn-danger"
                    style="margin-right: auto"
                    @click="confirmDel = true"
                >
                    <Icon name="trash" />Supprimer
                </button>
                <button class="btn" @click="emit('close')">Annuler</button
                ><button class="btn btn-primary" @click="save">
                    Enregistrer
                </button>
            </template>
        </div>
    </Sheet>
</template>
