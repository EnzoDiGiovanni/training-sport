import { ref } from 'vue';
import { json } from '@/lib/http';
import type { SessionItem } from '@/types';

/** Sauvegarde automatique (debounce ≈ 400 ms) de l'état complet de la séance de musculation. */
export function useLiveSession(items: SessionItem[]) {
    const status = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
    let t: ReturnType<typeof setTimeout> | undefined;
    let pending: Promise<unknown> = Promise.resolve();

    const payload = () => ({
        exercises: items.map((e) => ({ id: e.id, note: e.note || null, sets: e.sets.map((s) => ({ weight_kg: s.weight_kg || 0, reps: s.reps || 0, is_completed: s.is_completed })) })),
    });
    async function flush() {
        clearTimeout(t);
        status.value = 'saving';
        pending = pending.then(() => json('/api/live', 'PUT', payload())).then(
            () => (status.value = 'saved'),
            () => (status.value = 'error'),
        );
        return pending;
    }
    function save() {
        clearTimeout(t);
        t = setTimeout(flush, 400);
    }
    return { save, flush, status };
}
