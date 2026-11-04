import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '@focus/api-client';
import { completionRange, suggestedCompletionDay } from '@/features/tasks/completionDay';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { useOutboxStore } from '@/stores/outbox';
import type { Task } from '@focus/types';

const {
    update,
    create,
    remove,
    removeGoal,
    createItem,
    uploadCover,
    getEntry,
    saveEntry,
    moveToToday,
    files,
    info,
    success,
    error,
} = vi.hoisted(() => ({
    update: vi.fn(),
    uploadCover: vi.fn(),
    files: new Map<string, File>(),
    remove: vi.fn(),
    removeGoal: vi.fn(),
    createItem: vi.fn(),
    create: vi.fn(),
    getEntry: vi.fn(),
    saveEntry: vi.fn(),
    moveToToday: vi.fn(),
    info: vi.fn(),
    success: vi.fn(),
    error: vi.fn(),
}));

vi.mock('@focus/api-client', async (original) => {
    const actual = await original<typeof import('@focus/api-client')>();
    return {
        ...actual,
        api: {
            ...actual.api,
            tasks: { ...actual.api.tasks, update, create, remove, moveToToday, uploadCover },
            goals: { ...actual.api.goals, remove: removeGoal },
            checklist: { ...actual.api.checklist, create: createItem },
            journal: { ...actual.api.journal, get: getEntry, save: saveEntry },
        },
    };
});

vi.mock('@/lib/offlineFiles', () => ({
    saveFile: async (key: string, file: File) => !!files.set(key, file),
    loadFile: async (key: string) => files.get(key),
    dropFile: async (key: string) => void files.delete(key),
    clearFiles: async () => files.clear(),
}));

vi.mock('@focus/ui', async (original) => ({
    ...(await original<typeof import('@focus/ui')>()),
    useToast: () => ({ info, success, error }),
}));

const task = (overrides: Partial<Task> = {}): Task =>
    ({
        id: 3,
        title: 'Write the brief',
        status: 'not_started',
        due_date: '2026-11-01',
        created_at: '2026-10-28T09:00:00',
        next_occurrence_id: null,
        ...overrides,
    }) as Task;

const offline = () => new ApiError({ kind: 'network', message: 'Could not reach the server.' });

describe('completion day', () => {
    it('ranges from the day the task was created to today', () => {
        expect(completionRange(task(), '2026-11-03')).toEqual({ min: '2026-10-28', max: '2026-11-03' });
    });

    it('suggests the due day, kept inside the range', () => {
        expect(suggestedCompletionDay(task(), '2026-11-03')).toBe('2026-11-01');
        expect(suggestedCompletionDay(task({ due_date: '2026-10-01' }), '2026-11-03')).toBe('2026-10-28');
        expect(suggestedCompletionDay(task({ due_date: '2026-12-01' }), '2026-11-03')).toBe('2026-11-03');
        expect(suggestedCompletionDay(task({ due_date: null }), '2026-11-03')).toBe('2026-11-03');
    });
});

describe('missed day actions', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-11-02T20:00:00'));
        localStorage.clear();
        files.clear();
        vi.clearAllMocks();
        setActivePinia(createPinia());
    });

    it('moves tasks to today in one request', async () => {
        moveToToday.mockResolvedValue(2);
        const onChange = vi.fn();

        await useTaskActions(onChange).moveToToday([task(), task({ id: 4 })]);

        expect(moveToToday).toHaveBeenCalledWith([3, 4]);
        expect(success).toHaveBeenCalledWith('Moved 2 tasks to today');
        expect(onChange).toHaveBeenCalled();
    });

    it('queues a completion made offline and replays it on the day it happened', async () => {
        update.mockRejectedValueOnce(offline());
        await useTaskActions(vi.fn()).toggleComplete(task());

        const outbox = useOutboxStore();
        expect(outbox.pending).toEqual([
            {
                kind: 'update',
                resource: 'tasks',
                id: 3,
                title: 'Write the brief',
                input: { status: 'completed', completed_at: '2026-11-02' },
            },
        ]);
        expect(error).not.toHaveBeenCalled();

        vi.setSystemTime(new Date('2026-11-03T08:00:00'));
        update.mockResolvedValue(task({ status: 'completed' }));

        expect(await outbox.flush()).toBe(1);
        expect(update).toHaveBeenLastCalledWith(3, { status: 'completed', completed_at: '2026-11-02' });
        expect(outbox.pending).toEqual([]);
    });

    it('keeps changes queued while still offline and drops ones the server rejects', async () => {
        const outbox = useOutboxStore();
        outbox.queueTaskUpdate(task({ title: 'A' }), { status: 'completed', completed_at: '2026-11-02' });
        outbox.queueTaskUpdate(task({ id: 4, title: 'B' }), { status: 'not_started' });

        update.mockRejectedValueOnce(offline());
        expect(await outbox.flush()).toBe(0);
        expect(outbox.pending).toHaveLength(2);

        update
            .mockRejectedValueOnce(new ApiError({ kind: 'validation', message: 'Invalid', status: 422 }))
            .mockResolvedValueOnce(task());
        expect(await outbox.flush()).toBe(1);
        expect(outbox.pending).toEqual([]);
    });

    it('drops a change the server says is stale and reports it once', async () => {
        const outbox = useOutboxStore();
        outbox.queueTaskUpdate(task({ title: 'A', updated_at: '2026-11-02T08:00:00+03:00' }), { status: 'completed' });

        update.mockRejectedValueOnce(new ApiError({ kind: 'conflict', message: 'Changed', status: 409 }));
        expect(await outbox.flush()).toBe(0);
        expect(update).toHaveBeenLastCalledWith(3, {
            status: 'completed',
            base_updated_at: '2026-11-02T08:00:00+03:00',
        });
        expect(outbox.pending).toEqual([]);
        expect(outbox.takeConflicts()).toEqual(['A']);
        expect(outbox.takeConflicts()).toEqual([]);
    });

    it('merges edits to the same task and keeps the copy they were based on', () => {
        const outbox = useOutboxStore();
        outbox.queueTaskUpdate(task({ title: 'A', updated_at: '2026-11-02T08:00:00' }), {
            status: 'completed',
            completed_at: '2026-11-02',
        });
        outbox.queueTaskUpdate(task({ title: 'A', updated_at: '2026-11-02T09:00:00' }), { due_date: '2026-11-05' });
        outbox.queueTaskUpdate(task({ title: 'A' }), { status: 'not_started', title: 'A renamed' });

        expect(outbox.pending).toEqual([
            {
                kind: 'update',
                resource: 'tasks',
                id: 3,
                title: 'A renamed',
                input: { due_date: '2026-11-05', status: 'not_started', title: 'A renamed' },
                base_updated_at: '2026-11-02T08:00:00',
            },
        ]);
    });

    it('queues a highlight toggled offline', async () => {
        update.mockRejectedValueOnce(offline());
        await useTaskActions(vi.fn()).toggleHighlight(task({ is_highlighted: false }));

        expect(useOutboxStore().pending).toEqual([
            { kind: 'update', resource: 'tasks', id: 3, title: 'Write the brief', input: { is_highlighted: true } },
        ]);
        expect(info).toHaveBeenCalled();
    });

    it('creates tasks added offline once back', async () => {
        const outbox = useOutboxStore();
        outbox.queueTaskCreate({ title: 'Call the bank', due_date: '2026-11-03' });
        expect(outbox.pending).toMatchObject([{ kind: 'create', resource: 'tasks', title: 'Call the bank' }]);

        create.mockResolvedValue(task({ title: 'Call the bank' }));
        expect(await outbox.flush()).toBe(1);
        expect(create).toHaveBeenCalledWith({ title: 'Call the bank', due_date: '2026-11-03' });
        expect(outbox.pending).toEqual([]);
    });

    it('saves the latest offline journal entry against the copy it was written from', async () => {
        const outbox = useOutboxStore();
        outbox.queueJournal('2026-11-02', 'First draft', '2026-11-02T08:00:00');
        outbox.queueJournal('2026-11-02', 'Second draft', '2026-11-02T08:00:00');
        expect(outbox.pending).toHaveLength(1);

        saveEntry.mockResolvedValue({ date: '2026-11-02', body: 'Second draft' });
        expect(await outbox.flush()).toBe(1);
        expect(saveEntry).toHaveBeenCalledWith('2026-11-02', 'Second draft', '2026-11-02T08:00:00');
    });

    it('keeps both texts when the journal entry changed elsewhere', async () => {
        const outbox = useOutboxStore();
        outbox.queueJournal('2026-11-02', 'Written offline', null);

        saveEntry
            .mockRejectedValueOnce(new ApiError({ kind: 'conflict', message: 'Changed', status: 409 }))
            .mockResolvedValueOnce({ date: '2026-11-02', body: '' });
        getEntry.mockResolvedValue({ date: '2026-11-02', body: 'Written on the laptop' });

        expect(await outbox.flush()).toBe(1);
        expect(saveEntry).toHaveBeenLastCalledWith('2026-11-02', 'Written on the laptop\n\nWritten offline');
        expect(outbox.pending).toEqual([]);
    });

    it('moves changes saved by the old task outbox into the new one', () => {
        localStorage.setItem(
            'focus.task-outbox',
            JSON.stringify([{ id: 3, title: 'A', status: 'completed', completed_at: '2026-11-02' }]),
        );
        setActivePinia(createPinia());

        expect(useOutboxStore().pending).toEqual([
            {
                kind: 'update',
                resource: 'tasks',
                id: 3,
                title: 'A',
                input: { status: 'completed', completed_at: '2026-11-02' },
            },
        ]);
        expect(localStorage.getItem('focus.task-outbox')).toBeNull();
    });

    it('shows tasks added offline and folds later edits into them', async () => {
        const outbox = useOutboxStore();
        const id = outbox.queueTaskCreate({ title: 'Call the bank', due_date: '2026-11-02' });
        const [local] = outbox.withPending([task()]);
        expect(local).toMatchObject({ id, title: 'Call the bank', status: 'not_started', due_date: '2026-11-02' });

        await useTaskActions(vi.fn()).toggleComplete(local!);
        await useTaskActions(vi.fn()).toggleHighlight(outbox.withPending([])[0]!);
        expect(update).not.toHaveBeenCalled();
        expect(outbox.pending).toEqual([
            {
                kind: 'create',
                resource: 'tasks',
                id,
                title: 'Call the bank',
                input: {
                    title: 'Call the bank',
                    due_date: '2026-11-02',
                    status: 'completed',
                    completed_at: '2026-11-02',
                    is_highlighted: true,
                },
            },
        ]);
        expect(outbox.withPending([])[0]).toMatchObject({ status: 'completed', is_highlighted: true });

        create.mockResolvedValue(task());
        expect(await outbox.flush()).toBe(1);
        expect(create).toHaveBeenCalledWith(expect.objectContaining({ status: 'completed', is_highlighted: true }));
        expect(outbox.synced).toBe(1);
    });

    it('drops a task deleted before it ever synced', async () => {
        const outbox = useOutboxStore();
        const id = outbox.queueTaskCreate({ title: 'Never mind' });

        await useTaskActions(vi.fn()).remove(outbox.withPending([])[0]!);
        expect(remove).not.toHaveBeenCalled();
        expect(outbox.pending).toEqual([]);
        expect(id).toBeLessThan(0);
    });

    it('shows queued edits and deletes on the tasks from the server', async () => {
        update.mockRejectedValueOnce(offline());
        remove.mockRejectedValueOnce(offline());
        const actions = useTaskActions(vi.fn());
        await actions.toggleComplete(task());
        await actions.remove(task({ id: 4, title: 'Gone' }));

        const outbox = useOutboxStore();
        expect(outbox.withPending([task(), task({ id: 4 })])).toEqual([task({ status: 'completed' })]);

        remove.mockResolvedValue(undefined);
        update.mockResolvedValue(task());
        expect(await outbox.flush()).toBe(2);
        expect(remove).toHaveBeenCalledWith(4);
    });

    it('replays other records made or removed offline', async () => {
        const outbox = useOutboxStore();
        const item = outbox.queueCreate('checklist', 'Draft', { title: 'Draft' }, 3);
        outbox.queueUpdate('checklist', { id: item, title: 'Draft' }, { done: true });
        outbox.queueRemove('goals', { id: 9, title: 'Ship v2' });
        expect(outbox.pending).toEqual([
            {
                kind: 'create',
                resource: 'checklist',
                id: item,
                parent: 3,
                title: 'Draft',
                input: { title: 'Draft', done: true },
            },
            { kind: 'remove', resource: 'goals', id: 9, title: 'Ship v2' },
        ]);

        createItem.mockResolvedValue({});
        removeGoal.mockResolvedValue(undefined);
        expect(await outbox.flush()).toBe(2);
        expect(createItem).toHaveBeenCalledWith(3, 'Draft');
        expect(removeGoal).toHaveBeenCalledWith(9);
    });

    it('uploads a photo picked offline to the task once the task exists', async () => {
        const outbox = useOutboxStore();
        const id = outbox.queueTaskCreate({ title: 'Plan the trip' });
        const photo = new File(['x'], 'beach.jpg', { type: 'image/jpeg' });
        expect(await outbox.queueCover({ id, title: 'Plan the trip' }, photo)).toBe(true);
        expect(files.size).toBe(1);

        create.mockResolvedValue(task({ id: 12 }));
        uploadCover.mockResolvedValue(task({ id: 12 }));
        expect(await outbox.flush()).toBe(2);
        expect(uploadCover).toHaveBeenCalledWith(12, photo);
        expect(files.size).toBe(0);
    });

    it('lets go of a queued photo when its task is deleted before syncing', async () => {
        const outbox = useOutboxStore();
        const id = outbox.queueTaskCreate({ title: 'Plan the trip' });
        await outbox.queueCover({ id, title: 'Plan the trip' }, new File(['x'], 'a.jpg'));
        await outbox.queueCover({ id, title: 'Plan the trip' }, new File(['y'], 'b.jpg'));
        expect(outbox.pending).toHaveLength(2);
        expect(files.size).toBe(1);

        outbox.queueRemove('tasks', { id, title: 'Plan the trip' });
        expect(outbox.pending).toEqual([]);
        expect(files.size).toBe(0);
    });

    it('upgrades task changes saved in the earlier outbox format', () => {
        localStorage.setItem(
            'focus.outbox',
            JSON.stringify([
                { kind: 'task-update', id: 3, title: 'A', input: { status: 'completed' } },
                { kind: 'task-create', key: 'k', title: 'B', input: { title: 'B' } },
            ]),
        );
        setActivePinia(createPinia());

        expect(useOutboxStore().pending).toMatchObject([
            { kind: 'update', resource: 'tasks', id: 3, input: { status: 'completed' } },
            { kind: 'create', resource: 'tasks', title: 'B', input: { title: 'B' } },
        ]);
    });
});
