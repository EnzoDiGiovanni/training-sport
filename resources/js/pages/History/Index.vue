<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import HistoryRow from '@/components/HistoryRow.vue';
import SessionSheet from '@/components/SessionSheet.vue';
import {
    addDays,
    cap,
    dateShort,
    fmt,
    fmtDur,
    iso,
    MONTHS,
    mondayOf,
    parseIso,
} from '@/composables/useFormat';
import type { Exercise, Profile, Session, WorkoutType } from '@/types';

const props = defineProps<{
    sessions: Session[];
    exercises: Exercise[];
    profile: Profile;
    today: string;
}>();
const type = ref<'all' | WorkoutType>('all');
const by = ref<'week' | 'month'>('week');
const thisMonday = iso(mondayOf(parseIso(props.today)));

const groups = computed(() => {
    const out: { key: string; d: Date; items: Session[] }[] = [];
    props.sessions
        .filter((s) => type.value === 'all' || s.type === type.value)
        .forEach((s) => {
            const d = parseIso(s.date);
            const key =
                by.value === 'week'
                    ? iso(mondayOf(d))
                    : d.getFullYear() + '-' + d.getMonth();
            let g = out.find((x) => x.key === key);
            if (!g) out.push((g = { key, d, items: [] }));
            g.items.push(s);
        });
    return out;
});
function label(g: { d: Date }) {
    if (by.value === 'month')
        return cap(MONTHS[g.d.getMonth()]) + ' ' + g.d.getFullYear();
    const m = mondayOf(g.d);
    return iso(m) === thisMonday
        ? 'Cette semaine'
        : 'Semaine du ' + dateShort(m) + ' au ' + dateShort(addDays(m, 6));
}
function sum(g: { items: Session[] }) {
    const km = g.items.reduce((a, s) => a + (s.run?.distance_km ?? 0), 0);
    const sec = g.items.reduce((a, s) => a + s.duration_seconds, 0);
    const n = g.items.filter((s) => s.type !== 'recovery').length;
    return `${n} séance${n > 1 ? 's' : ''}${km ? ' · ' + fmt(km) + ' km' : ''} · ${fmtDur(sec)}`;
}
const FILTERS: ['all' | WorkoutType, string][] = [
    ['all', 'Toutes'],
    ['run', 'Course'],
    ['strength', 'Musculation'],
    ['recovery', 'Récupération'],
];
const open = ref<Session | null>(null);
</script>

<template>
    <Head title="Entraînements" />
    <UPageHeader
        headline="Historique"
        title="Entraînements"
        :description="sessions.length + ' séances enregistrées'"
    />
    <div class="flex flex-wrap items-center justify-between gap-3">
        <UTabs
            v-model="type"
            :items="FILTERS.map(([value, label]) => ({ value, label }))"
            :content="false"
            :ui="{ list: 'w-auto', trigger: 'grow-0' }"
            variant="pill"
            size="sm"
        />
        <UTabs
            v-model="by"
            :items="[
                { value: 'week', label: 'Par semaine' },
                { value: 'month', label: 'Par mois' },
            ]"
            :content="false"
            :ui="{ list: 'w-auto', trigger: 'grow-0' }"
            size="sm"
            aria-label="Regrouper par"
        />
    </div>
    <section v-for="g in groups" :key="g.key" class="space-y-2">
        <div class="flex items-baseline justify-between px-1 text-sm">
            <span class="font-semibold">{{ label(g) }}</span
            ><span class="text-muted tabular-nums">{{ sum(g) }}</span>
        </div>
        <UCard :ui="{ body: 'p-0 sm:p-0 divide-y divide-default' }">
            <HistoryRow
                v-for="s in g.items"
                :key="s.id"
                :s="s"
                @open="open = s"
            />
        </UCard>
    </section>
    <UEmpty
        v-if="!groups.length"
        icon="i-lucide-calendar-x"
        :title="
            sessions.length
                ? 'Aucune séance pour ce filtre.'
                : 'Aucune séance enregistrée. Lance ta première séance depuis l’accueil.'
        "
    />
    <SessionSheet
        v-if="open"
        :key="open.id"
        :session="open"
        :exercises="exercises"
        :profile="profile"
        @close="open = null"
    />
</template>
