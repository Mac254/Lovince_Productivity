import type { IsoDate, TaskStatus } from '@focus/types';
import { api, isApiError } from '@focus/api-client';

export interface QueuedStatus {
    id: number;
    title: string;
    status: TaskStatus;
    completed_at?: IsoDate;
    base_updated_at?: string;
}

const STORAGE_KEY = 'focus.task-outbox';
const DROPPED = new Set(['validation', 'not_found', 'forbidden', 'conflict']);

function read(): QueuedStatus[] {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]') as unknown;
        return Array.isArray(saved) ? (saved as QueuedStatus[]) : [];
    } catch {
        return [];
    }
}

/**
 * Task status changes made while offline, kept on this device and replayed once the connection is back. Each
 * carries the task's `updated_at` as last seen, so a change made elsewhere in the meantime wins and the stale
 * one is dropped and counted in `conflicts`.
 */
export const useTaskOutboxStore = defineStore('taskOutbox', () => {
    const pending = ref<QueuedStatus[]>(read());
    const conflicts = ref<string[]>([]);

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
                const { id, title, status, completed_at, base_updated_at } = pending.value[0]!;
                try {
                    await api.tasks.update(id, {
                        status,
                        ...(completed_at && { completed_at }),
                        ...(base_updated_at && { base_updated_at }),
                    });
                    sent++;
                } catch (error) {
                    if (!isApiError(error) || !DROPPED.has(error.kind)) break;
                    if (error.kind === 'conflict') conflicts.value = [...conflicts.value, title];
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

    function takeConflicts(): string[] {
        const taken = conflicts.value;
        conflicts.value = [];
        return taken;
    }

    return { pending, conflicts, queue, flush, clear, takeConflicts };
});
