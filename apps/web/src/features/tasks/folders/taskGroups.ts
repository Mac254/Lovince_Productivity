import type { Task } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { taskCategoryLabels } from '@/lib/labels';

export type TaskFolderBy = 'goal' | 'type';

export interface TaskFolderGroup {
    key: string;
    title: string;
    /** Folders of a goal or type hold mixed titles, which fold again inside. */
    nested: boolean;
    lead: Task;
    tasks: Task[];
    done: number;
    total: number;
    loggedMinutes: number | null;
    nextDue: string | null;
    missed: number;
    unlogged: number;
}

const isClosed = (task: Task) => task.status === 'completed' || task.status === 'cancelled';

export function normaliseTitle(title: string): string {
    return title
        .normalize('NFKC')
        .trim()
        .replace(/\s+/g, ' ')
        .replace(/[.!…]+$/u, '')
        .toLocaleLowerCase();
}

function byInstanceOrder(a: Task, b: Task): number {
    if (isClosed(a) !== isClosed(b)) return isClosed(a) ? 1 : -1;
    const dueA = a.due_date ?? '9999-12-31';
    const dueB = b.due_date ?? '9999-12-31';
    if (dueA === dueB) return isClosed(a) ? b.id - a.id : a.id - b.id;
    return isClosed(a) ? dueB.localeCompare(dueA) : dueA.localeCompare(dueB);
}

function summarise(key: string, title: string | null, tasks: Task[], today: string): TaskFolderGroup {
    const instances = [...tasks].sort(byInstanceOrder);
    const counted = instances.filter((task) => task.status !== 'cancelled');
    const known = instances.filter((task) => task.logged_minutes !== undefined);
    const open = instances.filter((task) => !isClosed(task));
    const lead = instances[0]!;
    return {
        key,
        title: title ?? lead.title,
        nested: title !== null,
        lead,
        tasks: instances,
        done: counted.filter((task) => task.status === 'completed').length,
        total: counted.length,
        loggedMinutes: known.length ? known.reduce((sum, task) => sum + (task.logged_minutes ?? 0), 0) : null,
        nextDue: open.find((task) => task.due_date)?.due_date ?? null,
        missed: open.filter((task) => task.due_date && task.due_date < today).length,
        unlogged: known.filter((task) => task.status === 'completed' && !task.logged_minutes).length,
    };
}

function bucket(tasks: Task[], keyOf: (task: Task) => string): Map<string, Task[]> {
    const buckets = new Map<string, Task[]>();
    for (const task of tasks) {
        const key = keyOf(task);
        const found = buckets.get(key);
        if (found) found.push(task);
        else buckets.set(key, [task]);
    }
    return buckets;
}

/** Folds tasks sharing a normalised title into one group each, in order of first appearance. */
export function groupByTitle(tasks: Task[], now = new Date()): TaskFolderGroup[] {
    const today = todayIso(now);
    return [...bucket(tasks, (task) => normaliseTitle(task.title))].map(([key, group]) =>
        summarise(key, null, group, today),
    );
}

const folderOf: Record<TaskFolderBy, (task: Task) => { key: string; title: string }> = {
    goal: (task) =>
        task.goal_id
            ? { key: `goal:${task.goal_id}`, title: task.goal?.title ?? 'Goal' }
            : { key: 'goal:none', title: 'No goal' },
    type: (task) =>
        task.task_type_id
            ? { key: `type:${task.task_type_id}`, title: task.type?.name ?? 'Type' }
            : { key: `category:${task.category}`, title: taskCategoryLabels[task.category] },
};

/** One folder per goal or type, in order of first appearance, with the unfiled folder last. */
export function groupInto(tasks: Task[], by: TaskFolderBy, now = new Date()): TaskFolderGroup[] {
    const today = todayIso(now);
    const titles = new Map<string, string>();
    const buckets = bucket(tasks, (task) => {
        const folder = folderOf[by](task);
        titles.set(folder.key, folder.title);
        return folder.key;
    });
    return [...buckets]
        .map(([key, group]) => summarise(key, titles.get(key)!, group, today))
        .sort((a, b) => Number(a.key === 'goal:none') - Number(b.key === 'goal:none'));
}
