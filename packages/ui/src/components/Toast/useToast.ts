import { toast, useToasts } from '@lovince/ui-vue';

/** A toast as Lovince's store holds it (the type itself is not exported by `@lovince/ui-vue`). */
export type Toast = ReturnType<typeof useToasts>['value'][number];

/** Durations long enough to read; errors stay twice as long. Hovering or focusing the region pauses them. */
const SHORT = 3500;
const LONG = 6000;

/** Focus's toast API over Lovince's shared store, so any Lovince toast lands in the same region. */
export function useToast() {
    return {
        toasts: useToasts(),
        dismiss: (id: string) => toast.dismiss(id),
        success: (message: string) => void toast.success(message, { duration: SHORT }),
        error: (message: string) => void toast.error(message, { duration: LONG }),
        info: (message: string) => void toast(message, { duration: SHORT }),
    };
}
