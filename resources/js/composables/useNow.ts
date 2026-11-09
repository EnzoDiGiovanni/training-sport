import { onScopeDispose, ref } from 'vue';

/** Horloge réactive (Date.now) rafraîchie tous les 250 ms tant que le composant est monté. */
export function useNow() {
    const now = ref(Date.now());
    const t = setInterval(() => (now.value = Date.now()), 250);
    onScopeDispose(() => clearInterval(t));
    return now;
}
