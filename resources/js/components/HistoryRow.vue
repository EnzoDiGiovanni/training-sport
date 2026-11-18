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
    <button class="h-row" @click="$emit('open', s)">
        <span class="h-date"
            ><b>{{ parseIso(s.date).getDate() }}</b
            ><span>{{ DAY_SHORT[dayIdx(parseIso(s.date))] }}</span></span
        >
        <span class="stack" style="min-width: 0">
            <span style="font-weight: 500">{{ s.name }}</span>
            <span class="row gap-8" style="font-size: 12.5px"
                ><span
                    :style="{
                        width: '6px',
                        height: '6px',
                        borderRadius: '50%',
                        background:
                            s.type === 'run'
                                ? 'var(--run)'
                                : s.type === 'strength'
                                  ? 'var(--accent)'
                                  : 'var(--recovery)',
                    }"
                ></span
                ><span class="faint"
                    >{{ TYPE_LABEL[s.type]
                    }}{{ s.rpe ? ' · ' + RPE_LABEL[s.rpe] : '' }}</span
                ></span
            >
        </span>
        <span class="h-metrics num">
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
    </button>
</template>
