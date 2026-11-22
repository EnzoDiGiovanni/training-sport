<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import LiveSessionPill from '@/components/LiveSessionPill.vue';
import { toast } from '@/lib/toast';

const page = usePage();
const NAV = [
    { to: '/', label: 'Accueil', icon: 'i-lucide-house', match: ['/seance'] },
    {
        to: '/entrainements',
        label: 'Entraînements',
        icon: 'i-lucide-list',
        match: ['/entrainements'],
    },
    {
        to: '/programme',
        label: 'Programme',
        icon: 'i-lucide-calendar',
        match: ['/programme', '/exercices'],
    },
    {
        to: '/progression',
        label: 'Progression',
        icon: 'i-lucide-chart-line',
        match: ['/progression'],
    },
    {
        to: '/parametres',
        label: 'Paramètres',
        icon: 'i-lucide-settings',
        match: ['/parametres'],
    },
];
const path = computed(() => page.url.split('?')[0]);
const items = computed(() =>
    NAV.map(({ match, ...n }) => ({
        ...n,
        active:
            (n.to === '/' && path.value === '/') ||
            match.some((m) => path.value.startsWith(m)),
    })),
);
const onLive = computed(() => path.value.startsWith('/seance-en-cours'));

// Toasts envoyés par le serveur (session flash).
watch(
    () => page.props.flash?.toast,
    (m) => m && toast(m),
    { immediate: true },
);
</script>

<template>
    <div class="min-h-dvh bg-default lg:grid lg:grid-cols-[240px_1fr]">
        <aside
            class="sticky top-0 hidden h-dvh flex-col gap-6 border-r border-default bg-muted/40 px-3 py-5 lg:flex"
        >
            <Link
                href="/"
                class="flex items-center gap-2.5 px-2.5 font-semibold"
            >
                <span
                    class="grid size-7 place-items-center rounded-md bg-primary text-inverted"
                >
                    <UIcon name="i-lucide-activity" class="size-4" />
                </span>
                Recompo
            </Link>
            <UNavigationMenu
                :items="items"
                orientation="vertical"
                aria-label="Navigation principale"
            />
            <LiveSessionPill v-if="!onLive" />
            <div class="mt-auto flex items-center gap-2 px-2.5">
                <div class="min-w-0 flex-1 text-sm">
                    <p class="truncate font-medium">
                        {{ page.props.auth.user?.name }}
                    </p>
                    <p v-if="page.props.profile" class="text-xs text-muted">
                        {{ page.props.profile.height_cm }} cm
                    </p>
                </div>
                <UTooltip text="Se déconnecter">
                    <UButton
                        icon="i-lucide-log-out"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        aria-label="Se déconnecter"
                        @click="router.post('/logout')"
                    />
                </UTooltip>
            </div>
        </aside>
        <main class="min-w-0 pb-24 lg:pb-0">
            <div
                :key="path"
                class="mx-auto w-full max-w-5xl space-y-8 px-4 py-6 sm:px-6 lg:py-10"
            >
                <LiveSessionPill v-if="!onLive" class="lg:hidden" />
                <slot />
            </div>
        </main>
    </div>

    <nav
        class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-default bg-default/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
        aria-label="Navigation principale"
    >
        <Link
            v-for="n in items"
            :key="n.to"
            :href="n.to"
            :aria-current="n.active ? 'page' : undefined"
            class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium"
            :class="n.active ? 'text-primary' : 'text-muted'"
        >
            <UIcon :name="n.icon" class="size-5" />
            <span class="max-w-full truncate px-1">{{ n.label }}</span>
        </Link>
    </nav>
</template>
