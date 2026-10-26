import { readonly, ref } from 'vue';

export interface Toast {
    id: number;
    message: string;
    tone: 'success' | 'error' | 'info';
}

const MAX_VISIBLE = 4;
const toasts = ref<Toast[]>([]);
let nextId = 1;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function push(message: string, tone: Toast['tone']): void {
    const id = nextId++;
    toasts.value = [...toasts.value.slice(-(MAX_VISIBLE - 1)), { id, message, tone }];
    window.setTimeout(() => dismiss(id), tone === 'error' ? 6000 : 3500);
}

export function useToast() {
    return {
        toasts: readonly(toasts),
        dismiss,
        success: (message: string) => push(message, 'success'),
        error: (message: string) => push(message, 'error'),
        info: (message: string) => push(message, 'info'),
    };
}
