import { useToast } from '@nuxt/ui/composables';

export function toast(msg: string) {
    useToast().add({
        title: msg,
        icon: 'i-lucide-circle-check',
        color: 'success',
        duration: 2600,
    });
}
