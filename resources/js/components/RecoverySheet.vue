<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Icon from '@/components/Icon.vue';
import Sheet from '@/components/Sheet.vue';

const props = defineProps<{ date: string; workoutId: number | null }>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({ activity: 'Marche', duration_min: '30', comment: '', performed_on: props.date, workout_id: props.workoutId });
const save = () => form.post('/recuperation', { preserveScroll: true, onSuccess: () => emit('close') });
</script>

<template>
    <Sheet label="Activité de récupération" @close="emit('close')">
        <div class="sheet-head"><h3 class="grow title-m">Activité de récupération</h3><button class="btn btn-ghost btn-icon" aria-label="Fermer" @click="emit('close')"><Icon name="x" /></button></div>
        <div class="sheet-body">
            <p class="muted">Facultatif. Une activité douce aide à récupérer sans fatiguer.</p>
            <div class="field"><span class="label">Activité</span><div class="filters"><button v-for="a in ['Marche', 'Mobilité', 'Étirements', 'Vélo tranquille']" :key="a" type="button" class="fchip" :aria-pressed="form.activity === a" @click="form.activity = a">{{ a }}</button></div></div>
            <div class="field" style="max-width: 200px"><label for="rec-min">Durée</label><div class="input-unit"><input id="rec-min" v-model="form.duration_min" class="input num" :class="{ 'is-error': form.errors.duration_min }" inputmode="numeric" /><span>min</span></div><span v-if="form.errors.duration_min" class="err">{{ form.errors.duration_min }}</span></div>
            <div class="field"><label for="rec-com">Commentaire</label><textarea id="rec-com" v-model="form.comment" class="input"></textarea></div>
        </div>
        <div class="sheet-foot"><button class="btn" @click="emit('close')">Annuler</button><button class="btn btn-primary" :disabled="form.processing" @click="save">Enregistrer</button></div>
    </Sheet>
</template>
