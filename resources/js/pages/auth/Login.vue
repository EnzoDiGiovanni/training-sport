<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();
const form = useForm({ email: '', password: '', remember: true });
const submit = () =>
    form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Connexion" />
    <main class="grid min-h-dvh place-items-center bg-muted p-6">
        <UCard class="w-full max-w-sm">
            <form class="space-y-5" @submit.prevent="submit">
                <div class="flex items-center gap-2.5 font-semibold">
                    <span
                        class="grid size-7 place-items-center rounded-md bg-primary text-inverted"
                    >
                        <UIcon name="i-lucide-activity" class="size-4" />
                    </span>
                    Recompo
                </div>
                <h1 class="text-xl font-semibold">Connexion</h1>
                <UAlert v-if="status" variant="subtle" :title="status" />
                <UFormField label="Email" :error="form.errors.email">
                    <UInput
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        autofocus
                        class="w-full"
                    />
                </UFormField>
                <UFormField label="Mot de passe">
                    <UInput
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full"
                    />
                </UFormField>
                <UCheckbox v-model="form.remember" label="Rester connecté" />
                <UButton
                    type="submit"
                    size="lg"
                    block
                    :loading="form.processing"
                    >Se connecter</UButton
                >
            </form>
        </UCard>
    </main>
</template>
