<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import HistoryRow from '@/components/HistoryRow.vue';
import SessionSheet from '@/components/SessionSheet.vue';
import { addDays, cap, dateShort, fmt, fmtDur, iso, MONTHS, mondayOf, parseIso } from '@/composables/useFormat';
import type { Exercise, Profile, Session, WorkoutType } from '@/types';

const props = defineProps<{ sessions: Session[]; exercises: Exercise[]; profile: Profile; today: string }>();
const type = ref<'all' | WorkoutType>('all');
const by = ref<'week' | 'month'>('week');
const thisMonday = iso(mondayOf(parseIso(props.today)));

const groups = computed(() => {
    const out: { key: string; d: Date; items: Session[] }[] = [];
    props.sessions
        .filter((s) => type.value === 'all' || s.type === type.value)
        .forEach((s) => {
            const d = parseIso(s.date);
            const key = by.value === 'week' ? iso(mondayOf(d)) : d.getFullYear() + '-' + d.getMonth();
            let g = out.find((x) => x.key === key);
            if (!g) out.push((g = { key, d, items: [] }));
            g.items.push(s);
        });
    return out;
});
function label(g: { d: Date }) {
    if (by.value === 'month') return cap(MONTHS[g.d.getMonth()]) + ' ' + g.d.getFullYear();
    const m = mondayOf(g.d);
    return iso(m) === thisMonday ? 'Cette semaine' : 'Semaine du ' + dateShort(m) + ' au ' + dateShort(addDays(m, 6));
}
function sum(g: { items: Session[] }) {
    const km = g.items.reduce((a, s) => a + (s.run?.distance_km ?? 0), 0);
    const sec = g.items.reduce((a, s) => a + s.duration_seconds, 0);
    const n = g.items.filter((s) => s.type !== 'recovery').length;
    return `${n} séance${n > 1 ? 's' : ''}${km ? ' · ' + fmt(km) + ' km' : ''} · ${fmtDur(sec)}`;
}
const FILTERS: ['all' | WorkoutType, string][] = [['all', 'Toutes'], ['run', 'Course'], ['strength', 'Musculation'], ['recovery', 'Récupération']];
const open = ref<Session | null>(null);
</script>

<template>
    <Head title="Entraînements" />
    <div class="page-head">
        <div class="stack gap-4"><span class="eyebrow">Historique</span><h1 class="title-xl">Entraînements</h1></div>
        <span class="faint num" style="font-size: 13px">{{ sessions.length }} séances enregistrées</span>
    </div>
    <div class="row wrapflex" style="justify-content: space-between">
        <div class="filters"><button v-for="[v, l] in FILTERS" :key="v" class="fchip" :aria-pressed="type === v" @click="type = v">{{ l }}</button></div>
        <div class="seg" role="group" aria-label="Regrouper par">
            <button :aria-pressed="by === 'week'" @click="by = 'week'">Par semaine</button>
            <button :aria-pressed="by === 'month'" @click="by = 'month'">Par mois</button>
        </div>
    </div>
    <section v-for="g in groups" :key="g.key" class="week-group">
        <div class="week-group-head"><span style="color: var(--text); font-weight: 500">{{ label(g) }}</span><span class="num">{{ sum(g) }}</span></div>
        <div class="card"><HistoryRow v-for="s in g.items" :key="s.id" :s="s" @open="open = s" /></div>
    </section>
    <div v-if="!groups.length" class="card empty">{{ sessions.length ? 'Aucune séance pour ce filtre.' : 'Aucune séance enregistrée. Lance ta première séance depuis l’accueil.' }}</div>
    <SessionSheet v-if="open" :key="open.id" :session="open" :exercises="exercises" :profile="profile" @close="open = null" />
</template>
