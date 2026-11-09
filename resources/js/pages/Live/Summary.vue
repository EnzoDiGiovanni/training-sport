<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EffortRating from '@/components/EffortRating.vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Icon from '@/components/Icon.vue';
import { cap, dateLong, dateShort, fmt, fmtClock, fmtInt } from '@/composables/useFormat';
import { itemVolume, setsSummary } from '@/lib/domain';
import type { Session } from '@/types';

const props = defineProps<{ session: Session; records: string[]; previous: Session | null; previousVolumes: Record<number, number | null>; kcal: number }>();
const s = props.session;
const form = useForm({ rpe: s.rpe, comment: s.comment ?? '' });
const tiles = computed<[string, string | number][]>(() => {
    if (s.type === 'strength') {
        const sets = s.exercises.reduce((a, e) => a + e.sets.length, 0);
        const reps = s.exercises.reduce((a, e) => a + (e.measurement === 'time' ? 0 : e.sets.reduce((b, x) => b + x.reps, 0)), 0);
        return [['Durée', fmtClock(s.duration_seconds)], ['Volume', fmtInt(s.exercises.reduce((a, e) => a + itemVolume(e), 0)) + ' kg'], ['Séries', sets], ['Répétitions', reps]];
    }
    return [['Durée', fmtClock(s.duration_seconds)], ['Distance', fmt(s.run!.distance_km, 2) + ' km'], ['Vitesse moy.', fmt(s.run!.average_speed_kmh, 2) + ' km/h'], ['Vitesse max', s.run!.max_speed_kmh ? fmt(s.run!.max_speed_kmh, 1) + ' km/h' : '–']];
});
const runDeltas = computed<[string, number, string, number][]>(() => {
    const p = props.previous;
    if (s.type !== 'run' || !p?.run || !s.run) return [];
    return [['Distance', s.run.distance_km - p.run.distance_km, ' km', 2], ['Vitesse moy.', (s.run.average_speed_kmh ?? 0) - (p.run.average_speed_kmh ?? 0), ' km/h', 2], ['Durée', (s.duration_seconds - p.duration_seconds) / 60, ' min', 1]];
});
const save = () => form.post('/seance-en-cours/terminer', { onSuccess: () => ['chrono', 'runform', 'rest'].forEach((k) => localStorage.removeItem(`recompo-${k}-${s.id}`)) });
</script>

<template>
    <Head title="Récapitulatif" />
    <div class="card card-pad stack gap-20" style="max-width: 720px; margin: 0 auto; width: 100%">
        <div class="recap-hero"><span class="recap-badge"><Icon name="check" /></span><h1 class="title-l">Séance terminée</h1><p class="muted">{{ s.name }} · {{ cap(dateLong(s.date)) }}</p></div>
        <div class="grid-4">
            <div v-for="[k, v] in tiles" :key="k" class="card" style="padding: 14px; background: var(--surface-2)"><div class="faint" style="font-size: 12px">{{ k }}</div><div class="num" style="font-size: 19px; font-weight: 600">{{ v }}</div></div>
        </div>
        <div v-if="records.length" class="stack gap-8"><div v-for="p in records" :key="p" class="pr"><Icon name="trophy" /><span>{{ p }}</span></div></div>

        <div v-if="s.type === 'strength'" class="card">
            <div v-for="it in s.exercises" :key="it.id" class="ex-row" style="cursor: default; padding: 12px 16px; border-bottom: 1px solid var(--border); border-radius: 0">
                <span class="thumb"><ExerciseImage :exercise="{ name: it.name, illustration_key: it.illustration_key, image_url: it.image_url }" :animated="false" pose="end" /></span>
                <span class="grow stack"><span class="nm">{{ it.name }}</span><span class="sub num">{{ setsSummary(it) }}</span></span>
                <span v-if="previousVolumes[it.id] != null && it.load_type !== 'bodyweight'" class="num" :class="itemVolume(it) - previousVolumes[it.id]! >= 0 ? 'delta-good' : 'delta-bad'" style="font-size: 13px">{{ itemVolume(it) - previousVolumes[it.id]! >= 0 ? '+' : '' }}{{ fmtInt(itemVolume(it) - previousVolumes[it.id]!) }} kg</span>
            </div>
            <p v-if="!s.exercises.length" class="empty">Aucune série validée.</p>
        </div>
        <div v-else-if="runDeltas.length" class="card card-pad stack gap-12">
            <span class="label">Comparé à la dernière fois ({{ dateShort(previous!.date) }})</span>
            <div class="grid-3">
                <div v-for="[k, v, u, d] in runDeltas" :key="k" class="meta-item"><span class="k">{{ k }}</span><span class="v num" :class="v >= 0 ? 'delta-good' : 'delta-bad'">{{ v >= 0 ? '+' : '' }}{{ fmt(v, d) }}{{ u }}</span></div>
            </div>
        </div>

        <template v-if="s.type === 'strength'">
            <div class="field"><span class="label">Ressenti</span><EffortRating v-model="form.rpe" /></div>
            <div class="field"><label for="sumComment">Commentaire</label><textarea id="sumComment" v-model="form.comment" class="input" placeholder="Comment s’est passée la séance ?"></textarea></div>
        </template>
        <template v-else>
            <p v-if="s.rpe || s.comment" class="muted" style="font-size: 13.5px">{{ s.comment }}</p>
        </template>
        <p v-if="($page.props.errors as Record<string, string>).session" class="err">{{ ($page.props.errors as Record<string, string>).session }}</p>
        <p class="faint" style="font-size: 12.5px">Dépense énergétique estimée : ≈ {{ fmtInt(kcal) }} kcal. Estimation indicative, non mesurée.</p>
        <div class="row wrapflex" style="justify-content: flex-end">
            <Link href="/seance-en-cours" class="btn btn-ghost">{{ s.type === 'strength' ? 'Reprendre la séance' : 'Modifier les données' }}</Link>
            <button class="btn btn-primary btn-lg" :disabled="form.processing" @click="save"><Icon name="check" />Enregistrer</button>
        </div>
    </div>
</template>
