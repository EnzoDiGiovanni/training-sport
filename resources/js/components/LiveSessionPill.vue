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
    return fmtClock(
        run ??
            (l.started_at ? (now.value - Date.parse(l.started_at)) / 1000 : 0),
    );
});
</script>

<template>
    <Link
        v-if="live"
        href="/seance-en-cours"
        class="flex items-center gap-2.5 rounded-lg border border-primary/30 bg-default px-3 py-2.5 text-sm font-medium text-primary shadow-sm"
        :aria-label="'Reprendre la séance en cours : ' + live.name"
    >
        <span class="relative flex size-2">
            <span
                class="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-60"
            ></span>
            <span
                class="relative inline-flex size-2 rounded-full bg-primary"
            ></span>
        </span>
        <span class="flex-1 text-left">Séance en cours</span>
        <span class="tabular-nums">{{ clock }}</span>
        <span class="sr-only">Reprendre</span>
    </Link>
</template>
