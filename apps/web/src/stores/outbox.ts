import type {
    BlockerInput,
    ChecklistItemInput,
    GoalInput,
    IsoDate,
    Task,
    TaskInput,
    TaskNoteInput,
    TaskUpdateInput,
} from '@focus/types';
import { api, isApiError } from '@focus/api-client';

export interface OutboxInputs {
    tasks: TaskUpdateInput;
    goals: GoalInput;
    blockers: BlockerInput;
    checklist: ChecklistItemInput;
    taskNotes: TaskNoteInput;
    taskCovers: Record<string, never>;
}
export type OutboxResource = keyof OutboxInputs;

export type QueuedChange =
    | {
          [R in OutboxResource]:
              | { kind: 'create'; resource: R; id: number; parent?: number; title: string; input: OutboxInputs[R] }
              | {
                    kind: 'update';
                    resource: R;
                    id: number;
                    title: string;
                    input: OutboxInputs[R];
                    base_updated_at?: string;
                }
              | { kind: 'remove'; resource: R; id: number; title: string };
      }[OutboxResource]
    | { kind: 'journal'; date: IsoDate; body: string; base_updated_at: string | null };

type TaskCreate = Extract<QueuedChange, { kind: 'create'; resource: 'tasks' }>;
type TaskChange = Extract<QueuedChange, { kind: 'update'; resource: 'tasks' }>;

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

type EarlierShape =
    | { kind: 'task-update'; id: number; title: string; input: TaskUpdateInput; base_updated_at?: string }
    | { kind: 'task-create'; key: string; title: string; input: TaskInput }
    | QueuedChange;

let lastLocalId = 0;

/** A temporary id for a record made offline: negative, so it never matches one from the server. */
function localId(): number {
    lastLocalId = Math.min(lastLocalId - 1, -Date.now());
    return lastLocalId;
}

function upgrade(change: EarlierShape): QueuedChange {
    if (change.kind === 'task-update') return { ...change, kind: 'update', resource: 'tasks' };
    if (change.kind === 'task-create') {
        return { kind: 'create', resource: 'tasks', id: localId(), title: change.title, input: change.input };
    }
    return change;
}

function parse(key: string): unknown[] {
    const saved = JSON.parse(localStorage.getItem(key) ?? '[]') as unknown;
    return Array.isArray(saved) ? saved : [];
}

function read(): QueuedChange[] {
    try {
        const legacy = (parse(LEGACY_KEY) as LegacyStatus[]).map(
            ({ id, title, status, completed_at, base_updated_at }): QueuedChange => ({
                kind: 'update',
                resource: 'tasks',
                id,
                title,
                input: { status, ...(completed_at && { completed_at }) },
                ...(base_updated_at && { base_updated_at }),
            }),
        );
        localStorage.removeItem(LEGACY_KEY);
        return [...legacy, ...(parse(STORAGE_KEY) as EarlierShape[]).map(upgrade)];
    } catch {
        return [];
    }
}

export function isOffline(error: unknown): boolean {
    return isApiError(error) && error.kind === 'network';
}

/** A record made offline that the server hasn't seen yet. */
export function isLocal(record: { id: number }): boolean {
    return record.id < 0;
}

function mergeInput<R extends OutboxResource>(
    resource: R,
    earlier: OutboxInputs[R],
    next: OutboxInputs[R],
): OutboxInputs[R] {
    if (resource !== 'tasks') return { ...earlier, ...next };
    const { completed_at, ...rest } = earlier as TaskUpdateInput;
    const kept = (next as TaskUpdateInput).status ? rest : { ...rest, ...(completed_at && { completed_at }) };
    return { ...kept, ...next };
}

function shownInput(input: TaskUpdateInput): Partial<Task> {
    const shown: TaskUpdateInput = { ...input };
    delete shown.completed_at;
    delete shown.base_updated_at;
    delete shown.cover;
    return shown as Partial<Task>;
}

function localTask(change: TaskCreate): Task {
    return {
        description: null,
        status: 'not_started',
        priority: 'medium',
        category: 'general',
        is_highlighted: false,
        estimate_minutes: null,
        recurrence: null,
        start_date: null,
        due_date: null,
        task_type_id: null,
        goal_id: null,
        ...shownInput(change.input),
        id: change.id,
        title: change.title,
        next_occurrence_id: null,
        cover: null,
        completed_at: null,
        is_overdue: false,
        created_at: new Date().toISOString(),
        updated_at: '',
    };
}

/**
 * Changes made while offline, kept on this device and replayed in order once the connection is back: creating,
 * editing and deleting tasks, goals, blockers, checklist items and work-log entries, and journal entries. A record
 * made offline gets a negative id until it syncs; edits to it fold into its creation and deleting it drops it.
 * Task edits carry the `updated_at` they were made against, so a change made elsewhere in the meantime wins: a
 * stale task edit is dropped and named in `conflicts`, and a stale journal entry is kept by adding the offline
 * text below what is on the server. `synced` counts the flushes that sent something, so lists can reload.
 */
export const useOutboxStore = defineStore('outbox', () => {
    const pending = ref<QueuedChange[]>(read());
    const conflicts = ref<string[]>([]);
    const synced = ref(0);

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

    const targets = (change: QueuedChange, resource: OutboxResource, id: number) =>
        change.kind !== 'journal' && change.resource === resource && change.id === id;

    function queueCreate<R extends OutboxResource>(
        resource: R,
        title: string,
        input: OutboxInputs[R],
        parent?: number,
    ): number {
        const id = localId();
        pending.value = [
            ...pending.value,
            { kind: 'create', resource, id, title, input, ...(parent !== undefined && { parent }) } as QueuedChange,
        ];
        return id;
    }

    function queueUpdate<R extends OutboxResource>(
        resource: R,
        record: { id: number; title: string; updated_at?: string },
        input: OutboxInputs[R],
    ): void {
        const title = (input as { title?: string }).title ?? record.title;
        const earlier = pending.value.find(
            (change) => targets(change, resource, record.id) && change.kind !== 'remove',
        ) as Extract<QueuedChange, { kind: 'create' | 'update' }> | undefined;
        const merged = earlier ? mergeInput(resource, earlier.input as OutboxInputs[R], input) : input;

        if (earlier?.kind === 'create') {
            pending.value = pending.value.map((change) =>
                change === earlier ? ({ ...earlier, title, input: merged } as QueuedChange) : change,
            );
            return;
        }
        const base = earlier ? earlier.base_updated_at : record.updated_at || undefined;
        const change = {
            kind: 'update',
            resource,
            id: record.id,
            title,
            input: merged,
            ...(resource === 'tasks' && base && { base_updated_at: base }),
        } as QueuedChange;
        pending.value = [...pending.value.filter((entry) => entry !== earlier), change];
    }

    function queueRemove(resource: OutboxResource, record: { id: number; title: string }): void {
        const rest = pending.value.filter(
            (change) =>
                !targets(change, resource, record.id) &&
                !(resource === 'tasks' && change.kind === 'create' && change.parent === record.id),
        );
        pending.value = isLocal(record)
            ? rest
            : [...rest, { kind: 'remove', resource, id: record.id, title: record.title } as QueuedChange];
    }

    function queueTaskUpdate(
        task: Pick<Task, 'id' | 'title'> & Partial<Pick<Task, 'updated_at'>>,
        input: TaskUpdateInput,
    ): void {
        queueUpdate('tasks', task, input);
    }

    function queueTaskCreate(input: TaskInput): number {
        return queueCreate('tasks', input.title ?? 'New task', input);
    }

    function queueJournal(date: IsoDate, body: string, base: string | null): void {
        const earlier = pending.value.find((change) => change.kind === 'journal' && change.date === date);
        const kept = earlier?.kind === 'journal' ? earlier.base_updated_at : base;
        pending.value = [
            ...pending.value.filter((change) => change !== earlier),
            { kind: 'journal', date, body, base_updated_at: kept },
        ];
    }

    /** Tasks as this device has them: queued edits applied, queued deletes left out, tasks made offline first. */
    function withPending(tasks: Task[], added: (task: Task) => boolean = () => true): Task[] {
        const local = pending.value
            .filter((change): change is TaskCreate => change.kind === 'create' && change.resource === 'tasks')
            .map(localTask)
            .filter(added);
        const shown = tasks
            .filter(
                (task) =>
                    !pending.value.some((change) => change.kind === 'remove' && targets(change, 'tasks', task.id)),
            )
            .map((task) => {
                const change = pending.value.find(
                    (entry): entry is TaskChange => entry.kind === 'update' && targets(entry, 'tasks', task.id),
                );
                return change ? { ...task, ...shownInput(change.input) } : task;
            });
        return [...local, ...shown];
    }

    async function send(change: QueuedChange): Promise<void> {
        if (change.kind === 'journal') {
            try {
                await api.journal.save(change.date, change.body, change.base_updated_at);
            } catch (error) {
                if (!isApiError(error) || error.kind !== 'conflict') throw error;
                const current = (await api.journal.get(change.date))?.body ?? '';
                await api.journal.save(change.date, current ? `${current}\n\n${change.body}` : change.body);
            }
            return;
        }
        if (change.kind === 'create') {
            if (change.resource === 'tasks') await api.tasks.create(change.input);
            else if (change.resource === 'goals') await api.goals.create(change.input);
            else if (change.resource === 'blockers') await api.blockers.create(change.input);
            else if (change.resource === 'checklist')
                await api.checklist.create(change.parent!, change.input.title ?? '');
            else if (change.resource === 'taskNotes') await api.taskNotes.create(change.parent!, change.input);
            return;
        }
        if (change.kind === 'update') {
            if (change.resource === 'tasks') {
                await api.tasks.update(change.id, {
                    ...change.input,
                    ...(change.base_updated_at && { base_updated_at: change.base_updated_at }),
                });
            } else if (change.resource === 'goals') await api.goals.update(change.id, change.input);
            else if (change.resource === 'blockers') await api.blockers.update(change.id, change.input);
            else if (change.resource === 'checklist') await api.checklist.update(change.id, change.input);
            else if (change.resource === 'taskNotes') await api.taskNotes.update(change.id, change.input);
            return;
        }
        if (change.resource === 'tasks') await api.tasks.remove(change.id);
        else if (change.resource === 'goals') await api.goals.remove(change.id);
        else if (change.resource === 'blockers') await api.blockers.remove(change.id);
        else if (change.resource === 'checklist') await api.checklist.remove(change.id);
        else if (change.resource === 'taskNotes') await api.taskNotes.remove(change.id);
        else await api.tasks.removeCover(change.id);
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
                    if (error.kind === 'conflict' && change.kind === 'update') {
                        conflicts.value = [...conflicts.value, change.title];
                    }
                }
                pending.value = pending.value.slice(1);
            }
        } finally {
            flushing = false;
        }
        if (sent) synced.value++;
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

    return {
        pending,
        conflicts,
        synced,
        queueCreate,
        queueUpdate,
        queueRemove,
        queueTaskUpdate,
        queueTaskCreate,
        queueJournal,
        withPending,
        flush,
        clear,
        takeConflicts,
    };
});
