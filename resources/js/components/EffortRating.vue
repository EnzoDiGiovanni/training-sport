<script setup lang="ts">
const model = defineModel<number | null>();
const L: [number, string][] = [
    [1, 'Très facile'],
    [2, 'Facile'],
    [3, 'Modéré'],
    [4, 'Difficile'],
    [5, 'Très dur'],
];
</script>

<template>
    <div
        class="grid grid-cols-5 gap-1.5"
        role="group"
        aria-label="Ressenti de 1 à 5"
    >
        <UButton
            v-for="[n, l] in L"
            :key="n"
            :color="model === n ? 'primary' : 'neutral'"
            :variant="model === n ? 'soft' : 'outline'"
            :aria-pressed="model === n"
            class="flex-col justify-center gap-0.5 py-2 text-[11px]"
            @click="model = n"
        >
            <b class="text-base">{{ n }}</b
            >{{ l }}
        </UButton>
    </div>
</template>
