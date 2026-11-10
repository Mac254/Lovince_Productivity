import type { Task } from '@focus/types';
import { todayIso } from '@/lib/dates';

export interface TaskTitleGroup {
    key: string;
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

function summarise(key: string, tasks: Task[], today: string): TaskTitleGroup {
    const instances = [...tasks].sort(byInstanceOrder);
    const counted = instances.filter((task) => task.status !== 'cancelled');
    const known = instances.filter((task) => task.logged_minutes !== undefined);
    const open = instances.filter((task) => !isClosed(task));
    return {
        key,
        lead: instances[0]!,
        tasks: instances,
        done: counted.filter((task) => task.status === 'completed').length,
        total: counted.length,
        loggedMinutes: known.length ? known.reduce((sum, task) => sum + (task.logged_minutes ?? 0), 0) : null,
        nextDue: open.find((task) => task.due_date)?.due_date ?? null,
        missed: open.filter((task) => task.due_date && task.due_date < today).length,
        unlogged: known.filter((task) => task.status === 'completed' && !task.logged_minutes).length,
    };
}

/** Folds tasks sharing a normalised title into one group each, in order of first appearance. */
export function groupByTitle(tasks: Task[], now = new Date()): TaskTitleGroup[] {
    const buckets = new Map<string, Task[]>();
    for (const task of tasks) {
        const key = normaliseTitle(task.title);
        const bucket = buckets.get(key);
        if (bucket) bucket.push(task);
        else buckets.set(key, [task]);
    }
    const today = todayIso(now);
    return [...buckets].map(([key, bucket]) => summarise(key, bucket, today));
}
