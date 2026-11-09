<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { chronoElapsed } from '@/composables/useChronometer';
import { fmtClock } from '@/composables/useFormat';
import { useNow } from '@/composables/useNow';

const page = usePage();
const live = computed(() => page.props.live);
const now = useNow();
// Durée depuis started_at (muscu) ou chrono local (course).
const clock = computed(() => {
    const l = live.value;
    if (!l) return '';
    const run = l.type === 'run' ? chronoElapsed(l.id, now.value) : null;
    return fmtClock(run ?? (l.started_at ? (now.value - Date.parse(l.started_at)) / 1000 : 0));
});
</script>

<template>
    <Link v-if="live" href="/seance-en-cours" class="live-pill" :aria-label="'Reprendre la séance en cours : ' + live.name">
        <span class="live-dot"></span>
        <span class="grow" style="text-align: left">Séance en cours</span>
        <span class="num">{{ clock }}</span>
        <span class="sr">Reprendre</span>
    </Link>
</template>
