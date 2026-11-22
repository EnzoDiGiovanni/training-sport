<script setup lang="ts">
// Frise des vitesses : une barre par phase, hauteur ∝ vitesse max, curseur optionnel (minutes écoulées).
import { computed } from 'vue';
import { flatSteps, KIND_COLOR, speedLabel } from '@/lib/domain';
import type { Segment } from '@/types';

const props = defineProps<{ segments: Segment[]; markerMin?: number | null }>();
const steps = computed(() => flatSteps(props.segments));
const total = computed(
    () => steps.value.reduce((a, s) => a + +s.duration_min, 0) || 1,
);
const vMax = computed(() =>
    Math.max(10, ...steps.value.map((s) => +s.speed_max_kmh)),
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div class="relative">
            <div class="flex h-14 items-end gap-0.5">
                <div
                    v-for="(s, i) in steps"
                    :key="i"
                    class="flex h-full items-end"
                    :style="{ flex: s.duration_min + ' 0 0' }"
                    :title="`${s.label} · ${s.duration_min} min · ${speedLabel(s)}`"
                >
                    <i
                        class="block w-full rounded-sm"
                        :style="{
                            height:
                                Math.max(18, (s.speed_max_kmh / vMax) * 100) +
                                '%',
                            background: KIND_COLOR[s.kind] || 'var(--run)',
                        }"
                    ></i>
                </div>
            </div>
            <span
                v-if="markerMin != null"
                :style="{
                    position: 'absolute',
                    top: '-4px',
                    bottom: '-4px',
                    left: Math.min(100, (markerMin / total) * 100) + '%',
                    width: '2px',
                    background: 'var(--text)',
                    borderRadius: '2px',
                }"
            ></span>
        </div>
        <div class="flex justify-between text-[11px] text-dimmed tabular-nums">
            <span>0</span><span>{{ Math.round(total / 2) }} min</span
            ><span>{{ total }} min</span>
        </div>
    </div>
</template>
