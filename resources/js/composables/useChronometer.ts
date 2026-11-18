import { computed, reactive, watch } from 'vue';
import { useNow } from './useNow';

type State = { running: boolean; startTs: number; acc: number };

/** Chrono de course conservé dans localStorage pour survivre à un rechargement. */
export function useChronometer(sessionId: number) {
    const key = 'recompo-chrono-' + sessionId;
    let saved: State | null = null;
    try {
        saved = JSON.parse(localStorage.getItem(key) || 'null');
    } catch {
        saved = null;
    }
    const s = reactive<State>(saved ?? { running: false, startTs: 0, acc: 0 });
    watch(s, () => localStorage.setItem(key, JSON.stringify(s)), {
        deep: true,
    });

    const now = useNow();
    const elapsed = computed(
        () => (s.acc + (s.running ? now.value - s.startTs : 0)) / 1000,
    );

    return {
        state: s,
        elapsed,
        start() {
            s.running = true;
            s.startTs = Date.now();
        },
        pause() {
            if (s.running) s.acc += Date.now() - s.startTs;
            s.running = false;
        },
        reset() {
            s.acc = 0;
            s.running = false;
        },
        clear: () => localStorage.removeItem(key),
    };
}

/** Lecture seule (pastille « séance en cours ») sans démarrer d'état. */
export function chronoElapsed(sessionId: number, now: number): number | null {
    try {
        const s: State | null = JSON.parse(
            localStorage.getItem('recompo-chrono-' + sessionId) || 'null',
        );
        return s ? (s.acc + (s.running ? now - s.startTs : 0)) / 1000 : null;
    } catch {
        return null;
    }
}
