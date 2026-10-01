import { ref } from 'vue';

export interface Toast {
    id: number;
    message: string;
    tone: 'success' | 'danger';
}

const toasts = ref<Toast[]>([]);
let nextId = 1;

/** Short confirmations after an action ("Customer deactivated"), shown by ToastHost. */
export function useToast() {
    function dismiss(id: number): void {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    function show(message: string, tone: Toast['tone'] = 'success'): void {
        const id = nextId++;
        toasts.value.push({ id, message, tone });
        setTimeout(() => dismiss(id), 4000);
    }

    return { toasts, show, dismiss };
}
