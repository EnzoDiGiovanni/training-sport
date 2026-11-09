<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Icon from '@/components/Icon.vue';
import LiveSessionPill from '@/components/LiveSessionPill.vue';
import { toast, toastMsg } from '@/lib/toast';

const page = usePage();
const NAV = [
    { href: '/', label: 'Accueil', icon: 'home', match: ['/', '/seance'] },
    { href: '/entrainements', label: 'Entraînements', icon: 'list', match: ['/entrainements'] },
    { href: '/programme', label: 'Programme', icon: 'calendar', match: ['/programme', '/exercices'] },
    { href: '/progression', label: 'Progression', icon: 'chart', match: ['/progression'] },
    { href: '/parametres', label: 'Paramètres', icon: 'settings', match: ['/parametres'] },
];
const path = computed(() => page.url.split('?')[0]);
const isCurrent = (n: (typeof NAV)[number]) =>
    n.href === '/' ? path.value === '/' || path.value.startsWith('/seance') : n.match.some((m) => path.value.startsWith(m));
const onLive = computed(() => path.value.startsWith('/seance-en-cours'));

// Toasts envoyés par le serveur (session flash).
watch(
    () => page.props.flash?.toast,
    (m) => m && toast(m),
    { immediate: true },
);
</script>

<template>
    <div class="app">
        <aside class="side">
            <div class="brand">
                <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="#0B0F00" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16l5-5 4 4 7-8" /></svg></span>Recompo
            </div>
            <nav class="nav" aria-label="Navigation principale">
                <Link v-for="n in NAV" :key="n.href" :href="n.href" :aria-current="isCurrent(n) ? 'page' : undefined" class="nav-link"><Icon :name="n.icon" /><span>{{ n.label }}</span></Link>
            </nav>
            <LiveSessionPill v-if="!onLive" />
            <div class="side-foot">
                {{ page.props.auth.user?.name }}<template v-if="page.props.profile"> · {{ page.props.profile.height_cm }} cm</template><br />
                <button class="link" style="font-size: 12px" @click="router.post('/logout')">Se déconnecter</button>
            </div>
        </aside>
        <main>
            <div class="wrap view" :key="path"><slot /></div>
        </main>
    </div>
    <nav class="tabbar" aria-label="Navigation principale">
        <Link v-for="n in NAV" :key="n.href" :href="n.href" :aria-current="isCurrent(n) ? 'page' : undefined"><Icon :name="n.icon" /><span>{{ n.label }}</span></Link>
    </nav>
    <div v-if="!onLive" class="mobile-live"><LiveSessionPill /></div>
    <div v-if="toastMsg" class="toast" role="status"><Icon name="check" />{{ toastMsg }}</div>
</template>
