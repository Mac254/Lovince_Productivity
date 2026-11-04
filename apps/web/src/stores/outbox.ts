import type { IsoDate, Task, TaskInput, TaskUpdateInput } from '@focus/types';
import { api, isApiError } from '@focus/api-client';

export type QueuedChange =
    | { kind: 'task-update'; id: number; title: string; input: TaskUpdateInput; base_updated_at?: string }
    | { kind: 'task-create'; key: string; title: string; input: TaskInput }
    | { kind: 'journal'; date: IsoDate; body: string; base_updated_at: string | null };

const STORAGE_KEY = 'focus.outbox';
const LEGACY_KEY = 'focus.task-outbox';
const DROPPED = new Set(['validation', 'not_found', 'forbidden', 'conflict']);

interface LegacyStatus {
    id: number;
    title: string;
    status: TaskUpdateInput['status'];
    completed_at?: IsoDate;
    base_updated_at?: string;
}

function parse(key: string): unknown[] {
    const saved = JSON.parse(localStorage.getItem(key) ?? '[]') as unknown;
    return Array.isArray(saved) ? saved : [];
}

function read(): QueuedChange[] {
    try {
        const legacy = (parse(LEGACY_KEY) as LegacyStatus[]).map(
            ({ id, title, status, completed_at, base_updated_at }): QueuedChange => ({
                kind: 'task-update',
                id,
                title,
                input: { status, ...(completed_at && { completed_at }) },
                ...(base_updated_at && { base_updated_at }),
            }),
        );
        localStorage.removeItem(LEGACY_KEY);
        return [...legacy, ...(parse(STORAGE_KEY) as QueuedChange[])];
    } catch {
        return [];
    }
}

export function isOffline(error: unknown): boolean {
    return isApiError(error) && error.kind === 'network';
}

function sameTarget(a: QueuedChange, b: QueuedChange): boolean {
    if (a.kind === 'task-update' && b.kind === 'task-update') return a.id === b.id;
    if (a.kind === 'journal' && b.kind === 'journal') return a.date === b.date;
    return false;
}

/**
 * Changes made while offline, kept on this device and replayed in order once the connection is back: task edits
 * (merged per task), new tasks and journal entries. Edits carry the `updated_at` they were made against, so a
 * change made elsewhere in the meantime wins: a stale task edit is dropped and named in `conflicts`, and a stale
 * journal entry is kept by adding the offline text below what is on the server.
 */
export const useOutboxStore = defineStore('outbox', () => {
    const pending = ref<QueuedChange[]>(read());
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

    function queue(change: QueuedChange): void {
        const earlier = pending.value.find((entry) => sameTarget(entry, change));
        if (earlier?.kind === 'task-update' && change.kind === 'task-update') {
            const { completed_at, ...rest } = earlier.input;
            change = {
                ...change,
                input: {
                    ...(change.input.status ? rest : { ...rest, ...(completed_at && { completed_at }) }),
                    ...change.input,
                },
                ...(earlier.base_updated_at && { base_updated_at: earlier.base_updated_at }),
            };
        }
        if (earlier?.kind === 'journal' && change.kind === 'journal') {
            change = { ...change, base_updated_at: earlier.base_updated_at };
        }
        pending.value = [...pending.value.filter((entry) => !sameTarget(entry, change)), change];
    }

    function queueTaskUpdate(
        task: Pick<Task, 'id' | 'title'> & Partial<Pick<Task, 'updated_at'>>,
        input: TaskUpdateInput,
    ): void {
        queue({
            kind: 'task-update',
            id: task.id,
            title: input.title ?? task.title,
            input,
            ...(task.updated_at && { base_updated_at: task.updated_at }),
        });
    }

    function queueTaskCreate(input: TaskInput): void {
        queue({ kind: 'task-create', key: crypto.randomUUID(), title: input.title ?? 'New task', input });
    }

    function queueJournal(date: IsoDate, body: string, base: string | null): void {
        queue({ kind: 'journal', date, body, base_updated_at: base });
    }

    async function send(change: QueuedChange): Promise<void> {
        if (change.kind === 'task-create') {
            await api.tasks.create(change.input);
            return;
        }
        if (change.kind === 'task-update') {
            await api.tasks.update(change.id, {
                ...change.input,
                ...(change.base_updated_at && { base_updated_at: change.base_updated_at }),
            });
            return;
        }
        try {
            await api.journal.save(change.date, change.body, change.base_updated_at);
        } catch (error) {
            if (!isApiError(error) || error.kind !== 'conflict') throw error;
            const current = (await api.journal.get(change.date))?.body ?? '';
            await api.journal.save(change.date, current ? `${current}\n\n${change.body}` : change.body);
        }
    }

    let flushing = false;

    async function flush(): Promise<number> {
        if (flushing) return 0;
        flushing = true;
        let sent = 0;
        try {
            while (pending.value.length) {
                const change = pending.value[0]!;
                try {
                    await send(change);
                    sent++;
                } catch (error) {
                    if (!isApiError(error) || !DROPPED.has(error.kind)) break;
                    if (error.kind === 'conflict' && change.kind === 'task-update') {
                        conflicts.value = [...conflicts.value, change.title];
                    }
                }
                pending.value = pending.value.slice(1);
            }
        } finally {
            flushing = false;
        }
        return sent;
    }

    function takeConflicts(): string[] {
        const taken = conflicts.value;
        conflicts.value = [];
        return taken;
    }

    function clear(): void {
        pending.value = [];
    }

    return { pending, conflicts, queueTaskUpdate, queueTaskCreate, queueJournal, flush, clear, takeConflicts };
});
