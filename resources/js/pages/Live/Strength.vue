<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import ExerciseSheet from '@/components/ExerciseSheet.vue';
import Icon from '@/components/Icon.vue';
import RestTimer from '@/components/RestTimer.vue';
import Sheet from '@/components/Sheet.vue';
import {
    cap,
    dateLong,
    daysAgo,
    fmt,
    fmtClock,
    fmtInt,
    fmtKg,
    num,
} from '@/composables/useFormat';
import { useLiveSession } from '@/composables/useLiveSession';
import { useNow } from '@/composables/useNow';
import { useRestTimer } from '@/composables/useRestTimer';
import {
    exerciseById,
    itemVolume,
    kgLabel,
    maxLoadFor,
    setsSummary,
} from '@/lib/domain';
import { json } from '@/lib/http';
import { toast } from '@/lib/toast';
import type {
    Exercise,
    ExerciseHistoryPoint,
    Profile,
    Session,
    SessionItem,
} from '@/types';

type Prev = (SessionItem & { date: string }) | null;
const props = defineProps<{
    session: Session & { started_at: string };
    prev: Record<number, Prev>;
    exercises: Exercise[];
    profile: Profile;
}>();
const items = reactive<SessionItem[]>(
    JSON.parse(JSON.stringify(props.session.exercises)),
);
const { save, flush, status } = useLiveSession(items);
const timer = useRestTimer(props.session.id);
const now = useNow();
// Durée calculée depuis started_at (serveur), pas par un compteur client.
const elapsed = computed(
    () => (now.value - Date.parse(props.session.started_at)) / 1000,
);
const cur = ref(
    Math.max(
        0,
        items.findIndex((e) => e.sets.some((s) => !s.is_completed)),
    ),
);
const kpis = computed(() => ({
    done: items.reduce(
        (a, e) => a + e.sets.filter((s) => s.is_completed).length,
        0,
    ),
    total: items.reduce((a, e) => a + e.sets.length, 0),
    vol: items.reduce((a, e) => a + itemVolume(e), 0),
}));
const ex = (it: SessionItem) => exerciseById(props.exercises, it.exercise_id);
const img = (it: SessionItem) =>
    ex(it) ?? {
        name: it.name,
        illustration_key: it.illustration_key,
        image_url: it.image_url,
    };
const maxFor = (it: SessionItem) => maxLoadFor(it.load_type, props.profile);
const complete = (it: SessionItem) => it.sets.every((s) => s.is_completed);

function jump(i: number) {
    cur.value = i;
    nextTick(() =>
        document
            .getElementById('ex-' + i)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' }),
    );
}
function setField(
    it: SessionItem,
    j: number,
    f: 'weight_kg' | 'reps',
    v: string,
) {
    const n = num(v);
    if (!isNaN(n)) it.sets[j][f] = f === 'reps' ? Math.round(n) : n;
    cur.value = items.indexOf(it);
    save();
}
function toggle(i: number, j: number) {
    const it = items[i],
        s = it.sets[j];
    s.is_completed = !s.is_completed;
    cur.value = i;
    save();
    if (!s.is_completed) return;
    const left = it.sets.findIndex((x) => !x.is_completed);
    const next = items[i + 1];
    if (left >= 0)
        timer.start(
            it.rest_seconds,
            'Prochaine : série ' + (left + 1) + ' · ' + it.name,
        );
    else if (next) {
        timer.start(it.rest_seconds, 'Ensuite : ' + next.name);
        setTimeout(() => jump(i + 1), 350);
    } else {
        timer.stop();
        toast('Dernière série validée, bravo');
    }
}
function addSet(it: SessionItem) {
    const last = it.sets[it.sets.length - 1];
    it.sets.push({
        weight_kg: last?.weight_kg ?? 0,
        reps: last?.reps ?? it.target_reps_max,
        is_completed: false,
    });
    save();
}
function rmSet(it: SessionItem) {
    if (it.sets.length > 1) it.sets.pop();
    save();
}

const noSets = ref(false);
async function finish() {
    if (!kpis.value.done) return (noSets.value = true);
    await flush();
    timer.stop();
    router.visit('/seance-en-cours/recap');
}
const abandon = () => {
    timer.stop();
    router.delete('/seance-en-cours');
};

const shown = ref<Exercise | null>(null);
const historyFor = ref<{
    it: SessionItem;
    list: ExerciseHistoryPoint[];
} | null>(null);
async function openHistory(it: SessionItem) {
    historyFor.value = {
        it,
        list: it.exercise_id
            ? (
                  await json<ExerciseHistoryPoint[]>(
                      `/api/exercises/${it.exercise_id}/history`,
                  )
              ).reverse()
            : [],
    };
}
const prevTxt = (it: SessionItem, j: number) => {
    const pv = props.prev[it.id]?.sets.filter((s) => s.is_completed)[j];
    if (!pv) return '–';
    return it.load_type === 'bodyweight'
        ? pv.reps + (it.measurement === 'time' ? ' s' : '')
        : fmtKg(pv.weight_kg) + ' × ' + pv.reps;
};
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
            <div class="stack live-title grow">
                <span style="font-weight: 600">{{ session.name }}</span>
                <span class="faint" style="font-size: 12.5px"
                    >Séance en cours<template v-if="status === 'saving'">
                        · enregistrement…</template
                    ><template v-else-if="status === 'error'">
                        ·
                        <span style="color: var(--danger)"
                            >non enregistré, vérifie la connexion</span
                        ></template
                    ></span
                >
            </div>
            <div class="live-kpis">
                <div class="stack">
                    <span class="k">Durée</span
                    ><span class="v">{{ fmtClock(elapsed) }}</span>
                </div>
                <div class="stack">
                    <span class="k">Volume</span
                    ><span class="v">{{ fmtInt(kpis.vol) }} kg</span>
                </div>
                <div class="stack">
                    <span class="k">Séries</span
                    ><span class="v">{{ kpis.done }}/{{ kpis.total }}</span>
                </div>
            </div>
            <button class="btn btn-primary" @click="finish">Terminer</button>
        </div>
        <div class="live-progress" aria-hidden="true">
            <i
                :style="{
                    width:
                        (kpis.total ? (kpis.done / kpis.total) * 100 : 0) + '%',
                }"
            ></i>
        </div>
        <div class="inner" style="margin-top: 12px">
            <div class="ex-nav">
                <button
                    v-for="(e, i) in items"
                    :key="e.id"
                    :class="{ 'is-cur': i === cur, 'is-complete': complete(e) }"
                    @click="jump(i)"
                >
                    <Icon v-if="complete(e)" name="check" /><span
                        v-else
                        class="num faint"
                        >{{ i + 1 }}</span
                    >{{ e.name.split(' ')[0] }}
                </button>
            </div>
        </div>
    </div>

    <div class="stack gap-16" style="margin-top: 8px">
        <article
            v-for="(it, i) in items"
            :id="'ex-' + i"
            :key="it.id"
            class="card ex-card"
            :class="{ 'is-cur': i === cur }"
        >
            <div class="ex-top">
                <button
                    class="illo"
                    style="padding: 0"
                    :aria-label="'Voir les consignes : ' + it.name"
                    @click="shown = ex(it) ?? null"
                >
                    <ExerciseImage :exercise="img(it)" />
                </button>
                <div class="stack gap-8" style="min-width: 0">
                    <div
                        class="row"
                        style="
                            justify-content: space-between;
                            align-items: flex-start;
                        "
                    >
                        <div class="stack">
                            <span class="faint num" style="font-size: 12px"
                                >Exercice {{ i + 1 }} / {{ items.length }}</span
                            >
                            <h3 style="font-size: 17px">{{ it.name }}</h3>
                        </div>
                        <button
                            class="btn btn-ghost btn-icon"
                            aria-label="Performances précédentes"
                            @click="openHistory(it)"
                        >
                            <Icon name="history" />
                        </button>
                    </div>
                    <span class="faint" style="font-size: 13px">{{
                        ex(it)?.primary_muscle
                    }}</span>
                    <div class="row wrapflex gap-8">
                        <span class="chip num"
                            >{{ it.sets.length }} ×
                            {{
                                it.target_reps_min === it.target_reps_max
                                    ? it.target_reps_min
                                    : it.target_reps_min +
                                      '–' +
                                      it.target_reps_max
                            }}{{ it.measurement === 'time' ? ' s' : ''
                            }}{{ it.is_unilateral ? ' / côté' : '' }}</span
                        >
                        <span class="chip num"
                            ><Icon name="clock" :size="12" />Repos
                            {{ fmtClock(it.rest_seconds) }}</span
                        >
                        <span
                            v-if="it.load_type !== 'bodyweight'"
                            class="chip num"
                            >Cible {{ kgLabel(it.target_weight_kg, it) }}</span
                        >
                    </div>
                    <span class="faint num" style="font-size: 12.5px">{{
                        prev[it.id]
                            ? 'Dernière fois (' +
                              daysAgo(prev[it.id]!.date) +
                              ') : ' +
                              setsSummary(prev[it.id]!)
                            : 'Première fois sur cet exercice'
                    }}</span>
                </div>
            </div>
            <div style="overflow-x: auto">
                <table class="sets">
                    <thead>
                        <tr>
                            <th>Série</th>
                            <th>Précédent</th>
                            <th>
                                {{
                                    it.load_type === 'bodyweight'
                                        ? 'Charge'
                                        : it.load_type === 'two_dumbbells'
                                          ? 'kg / haltère'
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
                            <th aria-label="Validée">
                                <Icon name="check" :size="14" />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(s, j) in it.sets"
                            :key="j"
                            :class="{ 'is-done': s.is_completed }"
                        >
                            <td>
                                <span class="set-num">{{ j + 1 }}</span>
                            </td>
                            <td class="prev">{{ prevTxt(it, j) }}</td>
                            <td>
                                <span
                                    v-if="it.load_type === 'bodyweight'"
                                    class="faint"
                                    style="font-size: 13px"
                                    >PDC</span
                                >
                                <input
                                    v-else
                                    class="cell-in"
                                    :class="{
                                        'is-error':
                                            maxFor(it) &&
                                            s.weight_kg > maxFor(it),
                                    }"
                                    inputmode="decimal"
                                    :aria-label="'Charge série ' + (j + 1)"
                                    :value="fmtKg(s.weight_kg)"
                                    :title="
                                        maxFor(it) && s.weight_kg > maxFor(it)
                                            ? 'Au-delà de ton matériel (' +
                                              fmtKg(maxFor(it)) +
                                              ' kg max)'
                                            : ''
                                    "
                                    @input="
                                        setField(
                                            it,
                                            j,
                                            'weight_kg',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                            </td>
                            <td>
                                <input
                                    class="cell-in"
                                    inputmode="numeric"
                                    :aria-label="
                                        (it.measurement === 'time'
                                            ? 'Durée'
                                            : 'Répétitions') +
                                        ' série ' +
                                        (j + 1)
                                    "
                                    :value="s.reps"
                                    @input="
                                        setField(
                                            it,
                                            j,
                                            'reps',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                            </td>
                            <td>
                                <button
                                    class="check"
                                    :aria-label="'Valider la série ' + (j + 1)"
                                    :aria-pressed="s.is_completed"
                                    @click="toggle(i, j)"
                                >
                                    <Icon name="check" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="ex-foot">
                <button class="btn btn-sm" @click="addSet(it)">
                    <Icon name="plus" />Série
                </button>
                <button
                    class="btn btn-sm btn-ghost"
                    :disabled="it.sets.length <= 1"
                    @click="rmSet(it)"
                >
                    <Icon name="minus" />Retirer
                </button>
                <input
                    v-model="it.note"
                    class="note-in"
                    placeholder="Note (sensations, réglage du banc…)"
                    :aria-label="'Note pour ' + it.name"
                    @input="save"
                />
                <button
                    v-if="i < items.length - 1"
                    class="btn btn-sm ex-next"
                    @click="jump(i + 1)"
                >
                    Exercice suivant<Icon name="next" />
                </button>
            </div>
        </article>
    </div>
    <div class="row live-end">
        <button class="btn btn-ghost" @click="abandon">Abandonner</button>
        <button class="btn btn-primary btn-lg" @click="finish">
            <Icon name="check" />Terminer la séance
        </button>
    </div>

    <RestTimer
        v-if="timer.rest.value"
        :label="timer.rest.value.label"
        :left="timer.left.value"
        :total="timer.rest.value.total"
        @adjust="timer.adjust"
        @skip="timer.stop"
    />
    <ExerciseSheet v-if="shown" :exercise="shown" @close="shown = null" />
    <Sheet v-if="noSets" label="Aucune série validée" @close="noSets = false">
        <div class="sheet-head">
            <h3 class="title-m grow">Aucune série validée</h3>
        </div>
        <div class="sheet-body">
            <p class="muted">
                Valide au moins une série avec le bouton ✓ pour enregistrer la
                séance, ou abandonne-la.
            </p>
        </div>
        <div class="sheet-foot">
            <button class="btn btn-danger" @click="abandon">
                Abandonner la séance</button
            ><button class="btn btn-primary" @click="noSets = false">
                Continuer
            </button>
        </div>
    </Sheet>
    <Sheet
        v-if="historyFor"
        label="Performances précédentes"
        @close="historyFor = null"
    >
        <div class="sheet-head">
            <span class="thumb" style="width: 52px; height: 40px"
                ><ExerciseImage
                    :exercise="img(historyFor.it)"
                    :animated="false"
                    pose="end"
            /></span>
            <div class="stack grow">
                <span class="faint" style="font-size: 12px"
                    >Performances précédentes</span
                >
                <h3 class="title-m">{{ historyFor.it.name }}</h3>
            </div>
            <button
                class="btn btn-ghost btn-icon"
                aria-label="Fermer"
                @click="historyFor = null"
            >
                <Icon name="x" />
            </button>
        </div>
        <div class="sheet-body">
            <div
                v-for="h in historyFor.list.slice(0, 8)"
                :key="h.session_id"
                class="stack gap-4"
                style="
                    padding-bottom: 12px;
                    border-bottom: 1px solid var(--border);
                "
            >
                <div class="row" style="justify-content: space-between">
                    <span style="font-weight: 500">{{
                        cap(dateLong(h.date))
                    }}</span
                    ><span class="faint" style="font-size: 12.5px">{{
                        daysAgo(h.date)
                    }}</span>
                </div>
                <div class="row wrapflex gap-8">
                    <span v-for="(s, j) in h.sets" :key="j" class="chip num"
                        >{{ j + 1 }} ·
                        {{
                            historyFor.it.load_type === 'bodyweight'
                                ? ''
                                : fmt(s.weight_kg, s.weight_kg % 1 ? 1 : 0) +
                                  ' kg × '
                        }}{{ s.reps
                        }}{{
                            historyFor.it.measurement === 'time' ? ' s' : ''
                        }}</span
                    >
                </div>
                <span v-if="h.note" class="faint" style="font-size: 12.5px">{{
                    h.note
                }}</span>
            </div>
            <p v-if="!historyFor.list.length" class="empty">
                Aucune performance enregistrée pour cet exercice.
            </p>
        </div>
    </Sheet>
</template>
