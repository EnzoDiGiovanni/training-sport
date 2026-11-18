import { computed, ref, watch } from 'vue';
import { useNow } from './useNow';

type Rest = { end: number; total: number; label: string };

/** Chrono de récupération (conservé en localStorage pour survivre à un rechargement). */
export function useRestTimer(sessionId: number) {
    const key = 'recompo-rest-' + sessionId;
    const rest = ref<Rest | null>(
        JSON.parse(localStorage.getItem(key) || 'null'),
    );
    watch(
        rest,
        (r) =>
            r
                ? localStorage.setItem(key, JSON.stringify(r))
                : localStorage.removeItem(key),
        { deep: true },
    );
    const now = useNow();
    const left = computed(() =>
        rest.value ? (rest.value.end - now.value) / 1000 : 0,
    );
    return {
        rest,
        left,
        start: (sec: number, label: string) =>
            (rest.value = { end: Date.now() + sec * 1000, total: sec, label }),
        adjust(v: number) {
            if (!rest.value) return;
            rest.value.end += v * 1000;
            if (v > 0) rest.value.total += v;
        },
        stop: () => (rest.value = null),
    };
}
