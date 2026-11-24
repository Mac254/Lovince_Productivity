import type { Task } from '@focus/types';
import { describe, expect, it } from 'vitest';
import { buildRoutines, routineKeys } from '@/features/tasks/routines';

const now = new Date('2026-11-20T09:00:00');

const make = (id: number, overrides: Partial<Task> = {}): Task => ({
    id,
    title: 'Have a shower',
    description: null,
    status: 'not_started',
    cancel_reason: null,
    fallback: null,
    habit: null,
    fallback_done: false,
    priority: 'low',
    category: 'general',
    is_highlighted: false,
    cover: null,
    estimate_minutes: null,
    recurrence: null,
    next_occurrence_id: null,
    start_date: null,
    due_date: null,
    completed_at: null,
    is_overdue: false,
    task_type_id: null,
    type: null,
    goal_id: null,
    goal: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
});

describe('routineKeys', () => {
    it('counts repeated titles and recurring tasks, not one-offs', () => {
        const keys = routineKeys([
            make(1, { due_date: '2026-11-20' }),
            make(2, { due_date: '2026-11-21' }),
            make(3, { title: 'Pay rent', recurrence: 'monthly' }),
            make(4, { title: 'Fix payroll rounding' }),
        ]);
        expect([...keys].sort()).toEqual(['have a shower', 'pay rent']);
    });
});

describe('buildRoutines', () => {
    it('picks today’s instance over an older missed one and charts the week', () => {
        const open = [
            make(1, { title: 'Read a book (evening)', due_date: '2026-11-17' }),
            make(2, { title: 'Read a book (evening)', due_date: '2026-11-20' }),
            make(3, { title: 'Read a book (evening)', due_date: '2026-11-21' }),
        ];
        const closed = [make(4, { title: 'Read a book (evening)', due_date: '2026-11-19', status: 'completed' })];
        const [routine] = buildRoutines(open, closed, now);
        expect(routine!.task.id).toBe(2);
        expect(routine!.done).toBe(false);
        expect(routine!.days).toEqual(['none', 'none', 'none', 'missed', 'none', 'done', 'open']);
    });

    it('shows a routine finished today as done and skips ones not due yet', () => {
        const open = [
            make(1, { due_date: '2026-11-21' }),
            make(2, { due_date: '2026-11-22' }),
            make(3, { title: 'Weekly review', due_date: '2026-11-23' }),
            make(4, { title: 'Weekly review', due_date: '2026-11-30' }),
        ];
        const closed = [make(5, { due_date: '2026-11-20', status: 'completed' })];
        const routines = buildRoutines(open, closed, now);
        expect(routines.map((routine) => [routine.task.id, routine.done])).toEqual([[5, true]]);
    });
});
