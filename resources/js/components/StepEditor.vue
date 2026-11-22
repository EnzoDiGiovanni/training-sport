<script setup lang="ts">
// Ligne d'édition d'une phase de course (déroulé du programme).
import { fmt, num } from '@/composables/useFormat';
import { KIND_LABEL } from '@/lib/domain';
import type { Step } from '@/types';

const props = defineProps<{
    step: Step;
    id: string;
    first: boolean;
    last: boolean;
    maxKmh: number;
}>();
defineEmits<{ up: []; down: []; remove: [] }>();
const KINDS = Object.entries(KIND_LABEL).map(([value, label]) => ({
    value,
    label,
}));
const FIELDS: [keyof Step, string, string][] = [
    ['duration_min', 'Durée', 'min'],
    ['speed_min_kmh', 'Vitesse min', 'km/h'],
    ['speed_max_kmh', 'Vitesse max', 'km/h'],
    ['incline_pct', 'Incl.', '%'],
];
const tooFast = (s: Step) =>
    s.speed_max_kmh > props.maxKmh || s.speed_min_kmh > props.maxKmh;
function setNum(key: keyof Step, v: string) {
    const n = num(v);
    (props.step as Record<string, unknown>)[key] = isNaN(n) ? null : n;
}
</script>

<template>
    <div class="flex items-start gap-3 py-3">
        <div class="flex flex-col pt-5">
            <UButton
                icon="i-lucide-chevron-up"
                color="neutral"
                variant="ghost"
                size="xs"
                :disabled="first"
                aria-label="Monter"
                @click="$emit('up')"
            />
            <UButton
                icon="i-lucide-chevron-down"
                color="neutral"
                variant="ghost"
                size="xs"
                :disabled="last"
                aria-label="Descendre"
                @click="$emit('down')"
            />
        </div>
        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
            <div class="flex flex-wrap items-end gap-3">
                <UFormField label="Phase" size="sm" class="min-w-36 flex-1">
                    <UInput
                        :id="`${id}-l`"
                        v-model="step.label"
                        class="w-full"
                    />
                </UFormField>
                <UFormField label="Type" size="sm">
                    <USelect v-model="step.kind" :items="KINDS" class="w-40" />
                </UFormField>
                <UFormField
                    v-for="[f, l, u] in FIELDS"
                    :key="f"
                    :label="l"
                    size="sm"
                >
                    <UInput
                        inputmode="decimal"
                        :class="
                            f === 'duration_min' || f === 'incline_pct'
                                ? 'w-24'
                                : 'w-28'
                        "
                        :color="
                            (f === 'speed_max_kmh' || f === 'speed_min_kmh') &&
                            (step[f] as number) > maxKmh
                                ? 'error'
                                : undefined
                        "
                        :highlight="
                            (f === 'speed_max_kmh' || f === 'speed_min_kmh') &&
                            (step[f] as number) > maxKmh
                        "
                        :model-value="
                            fmt(
                                step[f] as number,
                                (step[f] as number) % 1 ? 1 : 0,
                            )
                        "
                        @change="
                            setNum(f, ($event.target as HTMLInputElement).value)
                        "
                    >
                        <template #trailing
                            ><span class="text-xs text-muted">{{
                                u
                            }}</span></template
                        >
                    </UInput>
                </UFormField>
            </div>
            <p v-if="tooFast(step)" class="text-xs text-error">
                Ton tapis est limité à {{ maxKmh }} km/h.
            </p>
        </div>
        <UButton
            icon="i-lucide-trash-2"
            color="neutral"
            variant="ghost"
            class="mt-5"
            aria-label="Supprimer la phase"
            @click="$emit('remove')"
        />
    </div>
</template>
