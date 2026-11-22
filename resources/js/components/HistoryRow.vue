<script setup lang="ts">
import {
    DAY_SHORT,
    dayIdx,
    fmt,
    fmtClock,
    fmtDur,
    fmtInt,
    parseIso,
} from '@/composables/useFormat';
import { RPE_LABEL, TYPE_LABEL } from '@/lib/domain';
import type { Session } from '@/types';

defineProps<{ s: Session }>();
defineEmits<{ open: [Session] }>();
</script>

<template>
    <button
        class="grid w-full grid-cols-[auto_1fr_auto] items-center gap-x-4 gap-y-1 px-4 py-3 text-left transition-colors hover:bg-elevated/60 sm:grid-cols-[auto_1fr_auto_auto]"
        @click="$emit('open', s)"
    >
        <span
            class="row-span-2 flex w-10 flex-none flex-col items-center rounded-md bg-elevated py-1 leading-tight sm:row-span-1"
            ><b class="text-base">{{ parseIso(s.date).getDate() }}</b
            ><span class="text-[11px] text-muted">{{
                DAY_SHORT[dayIdx(parseIso(s.date))]
            }}</span></span
        >
        <span class="flex min-w-0 flex-1 flex-col">
            <span class="truncate font-medium">{{ s.name }}</span>
            <span class="flex items-center gap-1.5 text-xs text-muted"
                ><span
                    class="size-1.5 rounded-full"
                    :style="{
                        background:
                            s.type === 'run'
                                ? 'var(--run)'
                                : s.type === 'strength'
                                  ? 'var(--accent)'
                                  : 'var(--recovery)',
                    }"
                ></span
                >{{ TYPE_LABEL[s.type]
                }}{{ s.rpe ? ' · ' + RPE_LABEL[s.rpe] : '' }}</span
            >
        </span>
        <span
            class="col-start-2 flex flex-wrap gap-x-3 text-xs text-toned tabular-nums sm:col-start-3 sm:row-start-1 sm:gap-x-4 sm:text-sm"
        >
            <template v-if="s.type === 'run' && s.run"
                ><span>{{ fmt(s.run.distance_km, 2) }} km</span
                ><span>{{ fmtClock(s.duration_seconds) }}</span
                ><span>{{ fmt(s.run.average_speed_kmh) }} km/h</span></template
            >
            <template v-else-if="s.type === 'strength'"
                ><span>{{ fmtDur(s.duration_seconds) }}</span
                ><span>{{ fmtInt(s.volume) }} kg</span
                ><span
                    >{{
                        s.exercises.reduce(
                            (a, e) =>
                                a + e.sets.filter((x) => x.is_completed).length,
                            0,
                        )
                    }}
                    séries</span
                ></template
            >
            <template v-else
                ><span>{{ fmtDur(s.duration_seconds) }}</span
                ><span>{{ s.activity }}</span></template
            >
        </span>
        <UIcon
            name="i-lucide-chevron-right"
            class="col-start-3 row-span-2 row-start-1 size-4 text-dimmed sm:col-start-4 sm:row-span-1"
        />
    </button>
</template>
