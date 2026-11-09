import { ref } from 'vue';

export const toastMsg = ref<string | null>(null);
let t: ReturnType<typeof setTimeout> | undefined;

export function toast(msg: string) {
    toastMsg.value = msg;
    clearTimeout(t);
    t = setTimeout(() => (toastMsg.value = null), 2600);
}
