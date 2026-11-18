import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Workout } from '@/types';

/** Démarrage d'une séance avec gestion de la séance déjà en cours (règle 13). */
export function useStartWorkout() {
    const page = usePage();
    const conflict = ref<Workout | null>(null);
    function start(w: Workout) {
        const live = page.props.live;
        if (live && live.workout_id === w.id)
            return router.visit('/seance-en-cours');
        if (live) return (conflict.value = w);
        router.post(`/seances/${w.id}/demarrer`);
    }
    const abandonAndStart = () =>
        router.post(
            `/seances/${conflict.value!.id}/demarrer`,
            { abandon_current: true },
            { onFinish: () => (conflict.value = null) },
        );
    return { start, conflict, abandonAndStart };
}
