<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
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
} from '@/composables/useFormat';
import { useLiveSession } from '@/composables/useLiveSession';
import { useNow } from '@/composables/useNow';
import { useMediaQuery } from '@vueuse/core';
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
// Sur mobile, un exercice à la fois (la barre du haut sert à naviguer).
const compact = useMediaQuery('(max-width: 1023px)');
// Vibration quand le repos se termine (si le téléphone le permet).
watch(
    () => !!timer.rest.value && timer.left.value <= 0,
    (over) => over && navigator.vibrate?.([200, 100, 200]),
);
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
// Champ vidé (null) : on garde la valeur précédente au lieu de la réécrire pendant la saisie.
function setField(
    it: SessionItem,
    j: number,
    f: 'weight_kg' | 'reps',
    v: number | null | undefined,
) {
    if (v == null || isNaN(v)) return;
    it.sets[j][f] = f === 'reps' ? Math.round(v) : v;
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
    <div
        class="sticky top-0 z-20 -mx-4 -mt-6 space-y-3 border-b border-default bg-default/90 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mt-10 lg:py-4"
    >
        <div class="flex flex-wrap items-center gap-3">
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
                    Séance en cours<template v-if="status === 'saving'">
                        · enregistrement…</template
                    ><template v-else-if="status === 'error'">
                        ·
                        <span class="text-error"
                            >non enregistré, vérifie la connexion</span
                        ></template
                    >
                </p>
            </div>
            <dl
                class="order-last flex w-full gap-6 tabular-nums sm:order-none sm:w-auto"
            >
                <div>
                    <dt class="text-xs text-muted">Durée</dt>
                    <dd class="font-semibold">{{ fmtClock(elapsed) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Volume</dt>
                    <dd class="font-semibold">{{ fmtInt(kpis.vol) }} kg</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Séries</dt>
                    <dd class="font-semibold">
                        {{ kpis.done }}/{{ kpis.total }}
                    </dd>
                </div>
            </dl>
            <UButton @click="finish">Terminer</UButton>
        </div>
        <UProgress
            :model-value="kpis.total ? (kpis.done / kpis.total) * 100 : 0"
            size="xs"
            aria-hidden="true"
        />
        <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5">
            <UButton
                v-for="(e, i) in items"
                :key="e.id"
                size="sm"
                :color="i === cur ? 'primary' : 'neutral'"
                :variant="
                    i === cur ? 'soft' : complete(e) ? 'subtle' : 'outline'
                "
                class="flex-none"
                @click="jump(i)"
            >
                <Icon
                    v-if="complete(e)"
                    name="check"
                    class="text-primary"
                /><span v-else class="text-muted tabular-nums">{{ i + 1 }}</span
                >{{ e.name.split(' ')[0] }}
            </UButton>
        </div>
    </div>

    <div class="space-y-4" :class="{ 'pb-40 lg:pb-24': timer.rest.value }">
        <UCard
            v-for="(it, i) in items"
            v-show="!compact || i === cur"
            :id="'ex-' + i"
            :key="it.id"
            as="article"
            class="scroll-mt-48 transition"
            :class="{ 'ring-2 ring-primary/50': i === cur }"
            :ui="{ body: 'space-y-4' }"
        >
            <div class="grid gap-4 sm:grid-cols-[200px_1fr]">
                <button
                    class="illo"
                    :aria-label="'Voir les consignes : ' + it.name"
                    @click="shown = ex(it) ?? null"
                >
                    <ExerciseImage :exercise="img(it)" />
                </button>
                <div class="flex min-w-0 flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs text-muted tabular-nums">
                                Exercice {{ i + 1 }} / {{ items.length }}
                            </p>
                            <h3 class="text-lg font-semibold">{{ it.name }}</h3>
                        </div>
                        <UTooltip text="Performances précédentes">
                            <UButton
                                icon="i-lucide-history"
                                color="neutral"
                                variant="ghost"
                                aria-label="Performances précédentes"
                                @click="openHistory(it)"
                            />
                        </UTooltip>
                    </div>
                    <p class="text-sm text-muted">
                        {{ ex(it)?.primary_muscle }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <UBadge
                            color="neutral"
                            variant="subtle"
                            class="tabular-nums"
                            >{{ it.sets.length }} ×
                            {{
                                it.target_reps_min === it.target_reps_max
                                    ? it.target_reps_min
                                    : it.target_reps_min +
                                      '–' +
                                      it.target_reps_max
                            }}{{ it.measurement === 'time' ? ' s' : ''
                            }}{{ it.is_unilateral ? ' / côté' : '' }}</UBadge
                        >
                        <UBadge
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-clock"
                            class="tabular-nums"
                            >Repos {{ fmtClock(it.rest_seconds) }}</UBadge
                        >
                        <UBadge
                            v-if="it.load_type !== 'bodyweight'"
                            color="neutral"
                            variant="subtle"
                            class="tabular-nums"
                            >Cible
                            {{ kgLabel(it.target_weight_kg, it) }}</UBadge
                        >
                    </div>
                    <p class="text-xs text-dimmed tabular-nums">
                        {{
                            prev[it.id]
                                ? 'Dernière fois (' +
                                  daysAgo(prev[it.id]!.date) +
                                  ') : ' +
                                  setsSummary(prev[it.id]!)
                                : 'Première fois sur cet exercice'
                        }}
                    </p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="sets">
                    <thead>
                        <tr>
                            <th>Série</th>
                            <th class="hidden sm:table-cell">Précédent</th>
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
                            <th aria-label="Validée" class="text-center">
                                <Icon name="check" :size="14" />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(s, j) in it.sets"
                            :key="j"
                            :class="{ 'bg-primary/5': s.is_completed }"
                        >
                            <td class="text-muted tabular-nums">{{ j + 1 }}</td>
                            <td
                                class="hidden text-xs text-dimmed tabular-nums sm:table-cell"
                            >
                                {{ prevTxt(it, j) }}
                            </td>
                            <td>
                                <span
                                    v-if="it.load_type === 'bodyweight'"
                                    class="text-sm text-muted"
                                    >PDC</span
                                >
                                <UInputNumber
                                    v-else
                                    :model-value="s.weight_kg"
                                    :min="0"
                                    :step="profile.weight_step_kg || 1"
                                    :step-snapping="false"
                                    size="sm"
                                    class="w-24 tabular-nums sm:w-28"
                                    :color="
                                        maxFor(it) && s.weight_kg > maxFor(it)
                                            ? 'error'
                                            : undefined
                                    "
                                    :highlight="
                                        !!maxFor(it) && s.weight_kg > maxFor(it)
                                    "
                                    :aria-label="'Charge série ' + (j + 1)"
                                    :title="
                                        maxFor(it) && s.weight_kg > maxFor(it)
                                            ? 'Au-delà de ton matériel (' +
                                              fmtKg(maxFor(it)) +
                                              ' kg max)'
                                            : ''
                                    "
                                    @update:model-value="
                                        (v) => setField(it, j, 'weight_kg', v)
                                    "
                                />
                            </td>
                            <td>
                                <UInputNumber
                                    :model-value="s.reps"
                                    :min="0"
                                    :step="it.measurement === 'time' ? 5 : 1"
                                    :step-snapping="false"
                                    size="sm"
                                    class="w-24 tabular-nums sm:w-28"
                                    :aria-label="
                                        (it.measurement === 'time'
                                            ? 'Durée'
                                            : 'Répétitions') +
                                        ' série ' +
                                        (j + 1)
                                    "
                                    @update:model-value="
                                        (v) => setField(it, j, 'reps', v)
                                    "
                                />
                            </td>
                            <td class="text-center">
                                <UButton
                                    icon="i-lucide-check"
                                    size="lg"
                                    :color="
                                        s.is_completed ? 'primary' : 'neutral'
                                    "
                                    :variant="
                                        s.is_completed ? 'solid' : 'outline'
                                    "
                                    :aria-label="'Valider la série ' + (j + 1)"
                                    :aria-pressed="s.is_completed"
                                    @click="toggle(i, j)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-plus"
                    color="neutral"
                    variant="outline"
                    size="sm"
                    @click="addSet(it)"
                    >Série</UButton
                >
                <UButton
                    icon="i-lucide-minus"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    :disabled="it.sets.length <= 1"
                    @click="rmSet(it)"
                    >Retirer</UButton
                >
                <UInput
                    :model-value="it.note ?? ''"
                    variant="soft"
                    size="sm"
                    placeholder="Note (sensations, réglage du banc…)"
                    :aria-label="'Note pour ' + it.name"
                    class="min-w-48 flex-1"
                    @update:model-value="
                        (v) => {
                            it.note = String(v);
                            save();
                        }
                    "
                />
                <UButton
                    v-if="i < items.length - 1"
                    trailing-icon="i-lucide-chevron-right"
                    color="neutral"
                    variant="outline"
                    size="sm"
                    @click="jump(i + 1)"
                    >Exercice suivant</UButton
                >
            </div>
        </UCard>
    </div>
    <div class="flex items-center justify-between gap-2">
        <UButton color="error" variant="ghost" @click="abandon"
            >Abandonner</UButton
        >
        <UButton size="lg" icon="i-lucide-check" @click="finish"
            >Terminer la séance</UButton
        >
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
    <Sheet v-if="noSets" title="Aucune série validée" @close="noSets = false">
        <p class="text-muted">
            Valide au moins une série avec le bouton ✓ pour enregistrer la
            séance, ou abandonne-la.
        </p>
        <template #footer>
            <UButton color="error" variant="soft" @click="abandon"
                >Abandonner la séance</UButton
            >
            <UButton @click="noSets = false">Continuer</UButton>
        </template>
    </Sheet>
    <Sheet
        v-if="historyFor"
        :title="historyFor.it.name"
        description="Performances précédentes"
        @close="historyFor = null"
    >
        <div class="divide-y divide-default">
            <div
                v-for="h in historyFor.list.slice(0, 8)"
                :key="h.session_id"
                class="space-y-2 py-3 first:pt-0"
            >
                <div class="flex justify-between text-sm">
                    <span class="font-medium">{{ cap(dateLong(h.date)) }}</span
                    ><span class="text-muted">{{ daysAgo(h.date) }}</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <UBadge
                        v-for="(s, j) in h.sets"
                        :key="j"
                        color="neutral"
                        variant="subtle"
                        class="tabular-nums"
                        >{{ j + 1 }} ·
                        {{
                            historyFor.it.load_type === 'bodyweight'
                                ? ''
                                : fmt(s.weight_kg, s.weight_kg % 1 ? 1 : 0) +
                                  ' kg × '
                        }}{{ s.reps
                        }}{{
                            historyFor.it.measurement === 'time' ? ' s' : ''
                        }}</UBadge
                    >
                </div>
                <p v-if="h.note" class="text-xs text-muted">{{ h.note }}</p>
            </div>
        </div>
        <UEmpty
            v-if="!historyFor.list.length"
            variant="naked"
            title="Aucune performance enregistrée pour cet exercice."
        />
    </Sheet>
</template>
