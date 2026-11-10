import { mount } from '@vue/test-utils';
import type { Task } from '@focus/types';
import { describe, expect, it } from 'vitest';
import TaskFolderList from '@/features/tasks/folders/TaskFolderList.vue';
import { groupByTitle, groupInto, normaliseTitle } from '@/features/tasks/folders/taskGroups';

const now = new Date('2026-11-10T09:00:00');

const make = (id: number, overrides: Partial<Task> = {}): Task => ({
    id,
    title: 'Move savings on payday',
    description: null,
    status: 'not_started',
    priority: 'medium',
    category: 'general',
    is_highlighted: false,
    cover: null,
    estimate_minutes: null,
    recurrence: 'monthly',
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

describe('normaliseTitle', () => {
    it('ignores case, spacing and trailing punctuation', () => {
        expect(normaliseTitle('  Plan   my DAY. ')).toBe('plan my day');
        expect(normaliseTitle('Read a book (evening)')).not.toBe(normaliseTitle('Read a book'));
    });
});

describe('groupByTitle', () => {
    const tasks = [
        make(1, { status: 'completed', due_date: '2026-08-25', logged_minutes: 15 }),
        make(2, { title: 'Cook dinner', due_date: '2026-11-10' }),
        make(3, { title: 'move savings on PAYDAY', status: 'completed', due_date: '2026-09-25', logged_minutes: 0 }),
        make(4, { due_date: '2026-10-25', is_overdue: true, logged_minutes: 0 }),
        make(5, { due_date: '2026-11-25', logged_minutes: 0 }),
        make(6, { status: 'cancelled', due_date: '2026-07-25' }),
    ];
    const groups = groupByTitle(tasks, now);
    const payday = groups[0]!;

    it('folds same-titled tasks in order of first appearance', () => {
        expect(groups.map((group) => group.tasks.length)).toEqual([5, 1]);
        expect(groups[1]!.lead.id).toBe(2);
    });

    it('leads with the earliest open instance and keeps every instance', () => {
        expect(payday.lead.id).toBe(4);
        expect(payday.tasks.map((task) => task.id)).toEqual([4, 5, 3, 1, 6]);
    });

    it('sums progress and logged time across instances, leaving cancelled out', () => {
        expect(payday.done).toBe(2);
        expect(payday.total).toBe(4);
        expect(payday.loggedMinutes).toBe(15);
        expect(payday.nextDue).toBe('2026-10-25');
    });

    it('keeps missed and unlogged instances visible', () => {
        expect(payday.missed).toBe(1);
        expect(payday.unlogged).toBe(1);
    });

    it('reports no logged time when the list carries none', () => {
        expect(groupByTitle([make(1), make(2)], now)[0]!.loggedMinutes).toBeNull();
    });
});

describe('groupInto', () => {
    const goal = { id: 25, title: 'BizSure version one' };
    const work = { id: 3, name: 'Work', tone: 'rose' as const };
    const tasks = [
        make(1, { title: 'Read a book', recurrence: null }),
        make(2, { title: 'Portal redesign', goal_id: 25, goal, task_type_id: 3, type: work, status: 'completed' }),
        make(3, { title: 'Accident flow', goal_id: 25, goal, task_type_id: 3, type: work }),
    ];

    it('files tasks under their goal, with unfiled tasks last', () => {
        const folders = groupInto(tasks, 'goal', now);
        expect(folders.map((folder) => [folder.title, folder.tasks.length])).toEqual([
            ['BizSure version one', 2],
            ['No goal', 1],
        ]);
        expect(folders[0]).toMatchObject({ nested: true, done: 1, total: 2 });
    });

    it('files tasks under their type, falling back to the category', () => {
        expect(groupInto(tasks, 'type', now).map((folder) => folder.title)).toEqual(['General', 'Work']);
    });
});

describe('TaskFolderList', () => {
    const mountRows = (tasks: Task[]) =>
        mount(TaskFolderList, { props: { tasks }, global: { stubs: { RouterLink: true } } });

    it('folds a repeated title into one folder that opens to its instances', async () => {
        const wrapper = mountRows([
            make(1, { status: 'completed', due_date: '2026-10-25' }),
            make(2, { due_date: '2099-11-25' }),
            make(3, { title: 'Cook dinner' }),
        ]);
        const trigger = wrapper.get('button[aria-expanded]');
        const folder = wrapper.get(`#${trigger.attributes('aria-controls')}`);
        expect(wrapper.findAll('button[aria-expanded]')).toHaveLength(1);
        expect(trigger.text()).toContain('Move savings on payday');
        expect(trigger.text()).toContain('2 tasks');
        expect(wrapper.text()).toContain('1/2 done');
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(folder.isVisible()).toBe(false);

        await trigger.trigger('click');
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(folder.findAll('li')).toHaveLength(2);
    });
});
