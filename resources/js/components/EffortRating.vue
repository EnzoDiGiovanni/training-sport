<script setup lang="ts">
const model = defineModel<number | null>();
const L: [number, string][] = [[1, 'Très facile'], [2, 'Facile'], [3, 'Modéré'], [4, 'Difficile'], [5, 'Très dur']];
</script>

<template>
    <div class="rating" role="group" aria-label="Ressenti de 1 à 5">
        <button v-for="[n, l] in L" :key="n" type="button" :aria-pressed="model === n" @click="model = n"><b>{{ n }}</b>{{ l }}</button>
    </div>
</template>
