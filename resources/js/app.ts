import { createInertiaApp } from '@inertiajs/vue3';
import AppShell from '@/components/AppShell.vue';

void createInertiaApp({
    title: (title) => (title ? `${title} · Recompo` : 'Recompo'),
    layout: (name) => (name.startsWith('auth/') ? null : AppShell),
    progress: { color: '#C5F23D' },
});
