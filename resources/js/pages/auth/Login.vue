<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string }>();
const form = useForm({ email: '', password: '', remember: true });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Connexion" />
    <main style="min-height: 100vh; display: grid; place-items: center; padding: 24px">
        <form class="card card-pad stack gap-16" style="width: min(380px, 100%)" @submit.prevent="submit">
            <div class="brand" style="padding: 0">
                <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="#0B0F00" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16l5-5 4 4 7-8" /></svg></span>Recompo
            </div>
            <h1 class="title-l">Connexion</h1>
            <p v-if="status" class="notice">{{ status }}</p>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" v-model="form.email" type="email" class="input" :class="{ 'is-error': form.errors.email }" autocomplete="email" required autofocus />
                <span v-if="form.errors.email" class="err">{{ form.errors.email }}</span>
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input id="password" v-model="form.password" type="password" class="input" autocomplete="current-password" required />
            </div>
            <label class="row" style="font-size: 13.5px; cursor: pointer"><input v-model="form.remember" type="checkbox" style="width: 18px; height: 18px; accent-color: var(--accent)" />Rester connecté</label>
            <button class="btn btn-primary btn-lg btn-block" :disabled="form.processing">Se connecter</button>
        </form>
    </main>
</template>
