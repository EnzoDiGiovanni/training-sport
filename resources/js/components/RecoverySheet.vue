<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Sheet from '@/components/Sheet.vue';

const props = defineProps<{ date: string; workoutId: number | null }>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({
    activity: 'Marche',
    duration_min: '30',
    comment: '',
    performed_on: props.date,
    workout_id: props.workoutId,
});
const save = () =>
    form.post('/recuperation', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
</script>

<template>
    <Sheet
        title="Activité de récupération"
        description="Facultatif. Une activité douce aide à récupérer sans fatiguer."
        @close="emit('close')"
    >
        <UFormField label="Activité">
            <div class="flex flex-wrap gap-2">
                <UButton
                    v-for="a in [
                        'Marche',
                        'Mobilité',
                        'Étirements',
                        'Vélo tranquille',
                    ]"
                    :key="a"
                    size="sm"
                    :color="form.activity === a ? 'primary' : 'neutral'"
                    :variant="form.activity === a ? 'soft' : 'outline'"
                    :aria-pressed="form.activity === a"
                    @click="form.activity = a"
                >
                    {{ a }}
                </UButton>
            </div>
        </UFormField>
        <UFormField
            label="Durée"
            :error="form.errors.duration_min"
            class="max-w-48"
        >
            <UInput
                v-model="form.duration_min"
                inputmode="numeric"
                class="w-full"
            >
                <template #trailing
                    ><span class="text-xs text-muted">min</span></template
                >
            </UInput>
        </UFormField>
        <UFormField label="Commentaire">
            <UTextarea v-model="form.comment" class="w-full" />
        </UFormField>
        <template #footer>
            <UButton color="neutral" variant="outline" @click="emit('close')"
                >Annuler</UButton
            >
            <UButton :loading="form.processing" @click="save"
                >Enregistrer</UButton
            >
        </template>
    </Sheet>
</template>
