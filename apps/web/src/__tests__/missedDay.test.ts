import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '@focus/api-client';
import { completionRange, suggestedCompletionDay } from '@/features/tasks/completionDay';
import { useTaskActions } from '@/features/tasks/useTaskActions';
import { useOutboxStore } from '@/stores/outbox';
import type { Task } from '@focus/types';

const { update, create, getEntry, saveEntry, moveToToday, info, success, error } = vi.hoisted(() => ({
    update: vi.fn(),
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
            tasks: { ...actual.api.tasks, update, create, moveToToday },
            journal: { ...actual.api.journal, get: getEntry, save: saveEntry },
        },
    };
});

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
            { kind: 'task-update', id: 3, title: 'Write the brief', input: { status: 'completed', completed_at: '2026-11-02' } },
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
        expect(update).toHaveBeenLastCalledWith(3, { status: 'completed', base_updated_at: '2026-11-02T08:00:00+03:00' });
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
                kind: 'task-update',
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
            { kind: 'task-update', id: 3, title: 'Write the brief', input: { is_highlighted: true } },
        ]);
        expect(info).toHaveBeenCalled();
    });

    it('creates tasks added offline once back', async () => {
        const outbox = useOutboxStore();
        outbox.queueTaskCreate({ title: 'Call the bank', due_date: '2026-11-03' });
        expect(outbox.pending).toMatchObject([{ kind: 'task-create', title: 'Call the bank' }]);

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
            { kind: 'task-update', id: 3, title: 'A', input: { status: 'completed', completed_at: '2026-11-02' } },
        ]);
        expect(localStorage.getItem('focus.task-outbox')).toBeNull();
    });
});
