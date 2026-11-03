import type { IsoDate, TaskStatus } from '@focus/types';
import { api, isApiError } from '@focus/api-client';

export interface QueuedStatus {
    id: number;
    title: string;
    status: TaskStatus;
    completed_at?: IsoDate;
}

const STORAGE_KEY = 'focus.task-outbox';
const DROPPED = new Set(['validation', 'not_found', 'forbidden']);

function read(): QueuedStatus[] {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]') as unknown;
        return Array.isArray(saved) ? (saved as QueuedStatus[]) : [];
    } catch {
        return [];
    }
}

/** Task status changes made while offline, kept on this device and replayed once the connection is back. */
export const useTaskOutboxStore = defineStore('taskOutbox', () => {
    const pending = ref<QueuedStatus[]>(read());

    watch(
        pending,
        (value) => {
            try {
                if (value.length) localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
                else localStorage.removeItem(STORAGE_KEY);
            } catch {
                return;
            }
        },
        { deep: true },
    );

    function queue(change: QueuedStatus): void {
        pending.value = [...pending.value.filter((entry) => entry.id !== change.id), change];
    }

    let flushing = false;

    async function flush(): Promise<number> {
        if (flushing) return 0;
        flushing = true;
        let sent = 0;
        try {
            while (pending.value.length) {
                const { id, status, completed_at } = pending.value[0]!;
                try {
                    await api.tasks.update(id, completed_at ? { status, completed_at } : { status });
                    sent++;
                } catch (error) {
                    if (!isApiError(error) || !DROPPED.has(error.kind)) break;
                }
                pending.value = pending.value.slice(1);
            }
        } finally {
            flushing = false;
        }
        return sent;
    }

    function clear(): void {
        pending.value = [];
    }

    return { pending, queue, flush, clear };
});
