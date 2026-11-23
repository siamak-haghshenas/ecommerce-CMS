import { ref } from 'vue';

export interface Toast {
    id: number;
    text: string;
    tone: 'success' | 'error';
}

const toasts = ref<Toast[]>([]);
let nextId = 1;

/** Short messages at the bottom of the screen (shown by StoreToasts in the layout). */
export function useToast() {
    function show(text: string, tone: Toast['tone'] = 'success'): void {
        const id = nextId++;
        toasts.value = [...toasts.value, { id, text, tone }];
        setTimeout(() => {
            toasts.value = toasts.value.filter((toast) => toast.id !== id);
        }, 4000);
    }

    return { toasts, show };
}
