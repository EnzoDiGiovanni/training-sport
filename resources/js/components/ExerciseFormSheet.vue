<script setup lang="ts">
// Création / modification d'un exercice personnalisé avec import d'image.
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExerciseImage from '@/components/ExerciseImage.vue';
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
const deleteError = computed(
    () => (page.props.errors as Record<string, string>)?.exercise,
);

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
        instructions: cues.value
            .split('\n')
            .map((x) => x.trim())
            .filter(Boolean),
        common_mistakes: mistakes.value
            .split('\n')
            .map((x) => x.trim())
            .filter(Boolean),
    })).post(e ? `/exercices/${e.id}` : '/exercices', {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: (p) =>
            emit(
                'saved',
                (p.props.flash as { created_exercise_id?: number })
                    ?.created_exercise_id ??
                    e?.id ??
                    null,
            ),
    });
}
const confirmDel = ref(false);
function destroy() {
    form.delete(`/exercices/${e!.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => !deleteError.value && emit('close'),
    });
}
const GROUPS = [
    { value: 'chest', label: 'Pectoraux' },
    { value: 'back', label: 'Dos' },
    { value: 'legs', label: 'Jambes' },
    { value: 'shoulders', label: 'Épaules' },
    { value: 'arms', label: 'Bras' },
    { value: 'core', label: 'Abdominaux' },
];
const LOADS = [
    { value: 'two_dumbbells', label: 'Deux haltères' },
    { value: 'one_dumbbell', label: 'Un haltère' },
    { value: 'bodyweight', label: 'Poids du corps' },
];
const MEASURES = [
    { value: 'reps', label: 'Répétitions' },
    { value: 'time', label: 'Durée (secondes)' },
];
</script>

<template>
    <Sheet
        :title="e ? 'Modifier l’exercice' : 'Nouvel exercice personnalisé'"
        @close="emit('close')"
    >
        <form class="space-y-5" @submit.prevent="save">
            <div class="flex flex-wrap items-start gap-4">
                <div class="illo w-48">
                    <ExerciseImage
                        :exercise="{
                            name: form.name,
                            image_url: preview,
                            illustration_key: null,
                        }"
                        :animated="false"
                    />
                </div>
                <div class="flex max-w-xs flex-col items-start gap-2">
                    <UButton
                        as="label"
                        for="cx-img"
                        icon="i-lucide-image"
                        color="neutral"
                        variant="outline"
                        class="cursor-pointer"
                        >{{
                            preview ? 'Changer l’image' : 'Importer une image'
                        }}</UButton
                    >
                    <input
                        id="cx-img"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="onFile"
                    />
                    <UButton
                        v-if="preview"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        @click="removeImage"
                        >Retirer l’image</UButton
                    >
                    <p class="text-xs text-muted">
                        Photo ou schéma du mouvement (JPG, PNG, WebP, 2 Mo max).
                        Sans image, une silhouette neutre s’affiche.
                    </p>
                    <p v-if="form.errors.image" class="text-xs text-error">
                        {{ form.errors.image }}
                    </p>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <UFormField label="Nom" :error="form.errors.name">
                    <UInput v-model="form.name" class="w-full" />
                </UFormField>
                <UFormField
                    label="Muscle principal"
                    :error="form.errors.primary_muscle"
                >
                    <UInput
                        v-model="form.primary_muscle"
                        placeholder="ex. Épaules"
                        class="w-full"
                    />
                </UFormField>
                <UFormField label="Groupe musculaire (filtres)">
                    <USelect
                        v-model="form.muscle_group"
                        :items="GROUPS"
                        class="w-full"
                    />
                </UFormField>
                <UFormField label="Muscles secondaires">
                    <UInput v-model="form.secondary_muscles" class="w-full" />
                </UFormField>
                <UFormField label="Matériel">
                    <UInput v-model="form.equipment" class="w-full" />
                </UFormField>
                <UFormField label="Charge">
                    <USelect
                        v-model="form.load_type"
                        :items="LOADS"
                        class="w-full"
                    />
                </UFormField>
                <UFormField label="Mesure">
                    <USelect
                        v-model="form.measurement"
                        :items="MEASURES"
                        class="w-full"
                    />
                </UFormField>
                <UFormField label="Unilatéral">
                    <USwitch
                        v-model="form.is_unilateral"
                        label="Oui, par côté"
                        class="pt-1.5"
                    />
                </UFormField>
            </div>
            <UFormField label="Description courte">
                <UTextarea
                    v-model="form.description"
                    :rows="2"
                    class="w-full"
                />
            </UFormField>
            <UFormField label="Consignes d’exécution" hint="une par ligne">
                <UTextarea v-model="cues" class="w-full" />
            </UFormField>
            <UFormField label="Erreurs fréquentes" hint="une par ligne">
                <UTextarea v-model="mistakes" class="w-full" />
            </UFormField>
            <UAlert
                v-if="deleteError"
                color="warning"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                :title="deleteError"
            />
        </form>
        <template #footer>
            <template v-if="confirmDel">
                <span class="mr-auto self-center text-sm text-muted"
                    >Supprimer cet exercice ?</span
                >
                <UButton
                    color="neutral"
                    variant="outline"
                    @click="confirmDel = false"
                    >Non</UButton
                >
                <UButton color="error" @click="destroy">Supprimer</UButton>
            </template>
            <template v-else>
                <UButton
                    v-if="e"
                    icon="i-lucide-trash-2"
                    color="error"
                    variant="ghost"
                    class="mr-auto"
                    @click="confirmDel = true"
                    >Supprimer</UButton
                >
                <UButton
                    color="neutral"
                    variant="outline"
                    @click="emit('close')"
                    >Annuler</UButton
                >
                <UButton :loading="form.processing" @click="save"
                    >Enregistrer</UButton
                >
            </template>
        </template>
    </Sheet>
</template>
