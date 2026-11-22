import { createInertiaApp } from '@inertiajs/vue3';
import { fr } from '@nuxt/ui/locale';
import ui from '@nuxt/ui/vue-plugin';
import UApp from '@nuxt/ui/components/App.vue';
import { createApp, h } from 'vue';
import AppShell from '@/components/AppShell.vue';

void createInertiaApp({
    title: (title) => (title ? `${title} · Recompo` : 'Recompo'),
    layout: (name) => (name.startsWith('auth/') ? null : AppShell),
    progress: { color: '#10b981' },
    setup({ el, App, props, plugin }) {
        createApp({
            render: () =>
                h(
                    UApp,
                    { locale: fr, toaster: { position: 'top-center' } },
                    () => h(App, props),
                ),
        })
            .use(plugin)
            .use(ui)
            .mount(el!);
    },
});
