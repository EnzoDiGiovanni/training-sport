import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

const MSG = 'Tu as des modifications non enregistrées. Quitter quand même ?';

/** Demande confirmation avant de quitter la page (lien Inertia ou fermeture d'onglet) si dirty() est vrai. */
export function useUnsavedGuard(dirty: () => boolean) {
    let off: (() => void) | undefined;
    const onUnload = (e: BeforeUnloadEvent) => {
        if (dirty()) e.preventDefault();
    };
    onMounted(() => {
        // Seules les navigations GET sont des sorties de page ; un enregistrement (POST/PUT) passe.
        off = router.on('before', (e) => {
            if (
                e.detail.visit.method === 'get' &&
                dirty() &&
                !window.confirm(MSG)
            )
                e.preventDefault();
        });
        window.addEventListener('beforeunload', onUnload);
    });
    onBeforeUnmount(() => {
        off?.();
        window.removeEventListener('beforeunload', onUnload);
    });
}
