<script setup lang="ts">
// Création / modification d'un exercice personnalisé avec import d'image.
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
import Icon from '@/components/Icon.vue';
import Sheet from '@/components/Sheet.vue';
import type { Exercise } from '@/types';

const props = defineProps<{ exercise?: Exercise | null }>();
const emit = defineEmits<{ close: []; saved: [id: number | null] }>();
const e = props.exercise;
const form = useForm({
    name: e?.name ?? '',
    primary_muscle: e?.primary_muscle ?? '',
    secondary_muscles: e?.secondary_muscles ?? '',
    muscle_group: e?.muscle_group ?? 'chest',
    equipment: e?.equipment ?? '',
    description: e?.description ?? '',
    instructions: e?.instructions ?? [],
    common_mistakes: e?.common_mistakes ?? [],
    measurement: e?.measurement ?? 'reps',
    load_type: e?.load_type ?? 'two_dumbbells',
    is_unilateral: e?.is_unilateral ?? false,
    image: null as File | null,
    remove_image: false,
});
const cues = ref((e?.instructions ?? []).join('\n'));
const mistakes = ref((e?.common_mistakes ?? []).join('\n'));
const preview = ref<string | null>(e?.image_url ?? null);
const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string>)?.exercise);

function onFile(ev: Event) {
    const f = (ev.target as HTMLInputElement).files?.[0];
    if (!f) return;
    form.image = f;
    form.remove_image = false;
    preview.value = URL.createObjectURL(f);
}
function removeImage() {
    form.image = null;
    form.remove_image = true;
    preview.value = null;
}
function save() {
    form.transform((d) => ({
        ...d,
        is_unilateral: d.is_unilateral ? 1 : 0,
        remove_image: d.remove_image ? 1 : 0,
        instructions: cues.value.split('\n').map((x) => x.trim()).filter(Boolean),
        common_mistakes: mistakes.value.split('\n').map((x) => x.trim()).filter(Boolean),
    })).post(e ? `/exercices/${e.id}` : '/exercices', {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: (p) => emit('saved', (p.props.flash as { created_exercise_id?: number })?.created_exercise_id ?? e?.id ?? null),
    });
}
const confirmDel = ref(false);
function destroy() {
    form.delete(`/exercices/${e!.id}`, { preserveScroll: true, preserveState: true, onSuccess: () => !deleteError.value && emit('close') });
}
const GROUPS = { chest: 'Pectoraux', back: 'Dos', legs: 'Jambes', shoulders: 'Épaules', arms: 'Bras', core: 'Abdominaux' };
</script>

<template>
    <Sheet :label="e ? 'Modifier l’exercice' : 'Nouvel exercice personnalisé'" @close="emit('close')">
        <div class="sheet-head">
            <h3 class="grow title-m">{{ e ? 'Modifier l’exercice' : 'Nouvel exercice personnalisé' }}</h3>
            <button class="btn btn-ghost btn-icon" aria-label="Fermer" @click="emit('close')"><Icon name="x" /></button>
        </div>
        <form class="sheet-body" @submit.prevent="save">
            <div class="row gap-16 wrapflex">
                <div class="illo" style="width: 200px"><ExerciseImage :exercise="{ name: form.name, image_url: preview, illustration_key: null }" :animated="false" /></div>
                <div class="stack gap-8">
                    <label class="btn" for="cx-img"><Icon name="image" />{{ preview ? 'Changer l’image' : 'Importer une image' }}</label>
                    <input id="cx-img" type="file" accept="image/jpeg,image/png,image/webp" class="sr" @change="onFile" />
                    <button v-if="preview" type="button" class="btn btn-ghost btn-sm" @click="removeImage">Retirer l’image</button>
                    <span class="hint" style="max-width: 30ch">Photo ou schéma du mouvement (JPG, PNG, WebP, 2 Mo max). Sans image, une silhouette neutre s’affiche.</span>
                    <span v-if="form.errors.image" class="err">{{ form.errors.image }}</span>
                </div>
            </div>
            <div class="grid-2">
                <div class="field"><label for="cx-name">Nom</label><input id="cx-name" v-model="form.name" class="input" :class="{ 'is-error': form.errors.name }" /><span v-if="form.errors.name" class="err">{{ form.errors.name }}</span></div>
                <div class="field"><label for="cx-muscle">Muscle principal</label><input id="cx-muscle" v-model="form.primary_muscle" class="input" :class="{ 'is-error': form.errors.primary_muscle }" placeholder="ex. Épaules" /><span v-if="form.errors.primary_muscle" class="err">{{ form.errors.primary_muscle }}</span></div>
            </div>
            <div class="grid-2">
                <div class="field"><label for="cx-group">Groupe musculaire (filtres)</label><select id="cx-group" v-model="form.muscle_group" class="input"><option v-for="(l, k) in GROUPS" :key="k" :value="k">{{ l }}</option></select></div>
                <div class="field"><label for="cx-sec">Muscles secondaires</label><input id="cx-sec" v-model="form.secondary_muscles" class="input" /></div>
            </div>
            <div class="grid-2">
                <div class="field"><label for="cx-eq">Matériel</label><input id="cx-eq" v-model="form.equipment" class="input" /></div>
                <div class="field"><label for="cx-load">Charge</label><select id="cx-load" v-model="form.load_type" class="input"><option value="two_dumbbells">Deux haltères</option><option value="one_dumbbell">Un haltère</option><option value="bodyweight">Poids du corps</option></select></div>
            </div>
            <div class="grid-2">
                <div class="field"><label for="cx-mode">Mesure</label><select id="cx-mode" v-model="form.measurement" class="input"><option value="reps">Répétitions</option><option value="time">Durée (secondes)</option></select></div>
                <div class="field"><label for="cx-side">Unilatéral</label><select id="cx-side" v-model="form.is_unilateral" class="input"><option :value="false">Non</option><option :value="true">Oui, par côté</option></select></div>
            </div>
            <div class="field"><label for="cx-desc">Description courte</label><textarea id="cx-desc" v-model="form.description" class="input" style="min-height: 56px"></textarea></div>
            <div class="field"><label for="cx-cues">Consignes d’exécution (une par ligne)</label><textarea id="cx-cues" v-model="cues" class="input"></textarea></div>
            <div class="field"><label for="cx-mist">Erreurs fréquentes (une par ligne)</label><textarea id="cx-mist" v-model="mistakes" class="input"></textarea></div>
            <p v-if="deleteError" class="notice warn"><Icon name="warn" /><span>{{ deleteError }}</span></p>
        </form>
        <div class="sheet-foot">
            <template v-if="confirmDel">
                <span class="grow muted" style="font-size: 13px; align-self: center">Supprimer cet exercice ?</span>
                <button class="btn" @click="confirmDel = false">Non</button><button class="btn btn-danger" @click="destroy">Supprimer</button>
            </template>
            <template v-else>
                <button v-if="e" class="btn btn-danger" style="margin-right: auto" @click="confirmDel = true"><Icon name="trash" />Supprimer</button>
                <button class="btn" @click="emit('close')">Annuler</button>
                <button class="btn btn-primary" :disabled="form.processing" @click="save">Enregistrer</button>
            </template>
        </div>
    </Sheet>
</template>
