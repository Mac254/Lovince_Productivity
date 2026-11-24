import type { Task } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { normaliseTitle } from './folders/taskGroups';

export type RoutineDay = 'done' | 'missed' | 'open' | 'none';

export interface Routine {
    key: string;
    /** Today's instance: the earliest open one when it is due, otherwise the one finished today. */
    task: Task;
    done: boolean;
    /** The last seven days, oldest first, ending today. */
    days: RoutineDay[];
}

const isClosed = (task: Task) => task.status === 'completed' || task.status === 'cancelled';

function shiftIso(iso: string, days: number): string {
    const date = new Date(`${iso}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
}

/** A schedule's habit keeps one key whichever activity its slot rotated to; other tasks go by title. */
export const routineKey = (task: Task) => normaliseTitle(task.habit ?? task.title);

function byRoutine(tasks: Task[]): Map<string, Task[]> {
    const groups = new Map<string, Task[]>();
    for (const task of tasks) groups.set(routineKey(task), [...(groups.get(routineKey(task)) ?? []), task]);
    return groups;
}

/** What repeats: a schedule habit, a recurring task, or the same title filed more than once. */
export function routineKeys(open: Task[]): Set<string> {
    return new Set(
        [...byRoutine(open)]
            .filter(([, tasks]) => tasks.length > 1 || tasks.some((task) => task.recurrence || task.habit))
            .map(([key]) => key),
    );
}

/**
 * One entry per routine that is due by today or was finished today, with a week of history built from
 * the open tasks and the recently closed ones.
 */
export function buildRoutines(open: Task[], closed: Task[], now = new Date()): Routine[] {
    const today = todayIso(now);
    const keys = routineKeys(open);
    const week = Array.from({ length: 7 }, (_, index) => shiftIso(today, index - 6));
    const all = [...open, ...closed.filter(isClosed)];

    return [...byRoutine(open)]
        .filter(([key]) => keys.has(key))
        .flatMap(([key, tasks]): Routine[] => {
            const instances = all.filter((task) => routineKey(task) === key);
            const finishedToday = instances.find(
                (task) =>
                    task.status === 'completed' &&
                    (task.due_date === today || (!task.due_date && task.completed_at?.slice(0, 10) === today)),
            );
            const due = tasks.filter((task) => !task.due_date || task.due_date <= today);
            const pending = due.find((task) => task.due_date === today) ?? due[0] ?? null;
            const task = pending ?? finishedToday;
            if (!task) return [];

            const days = week.map((day): RoutineDay => {
                const onDay = instances.filter((instance) => instance.due_date === day);
                if (onDay.some((instance) => instance.status === 'completed')) return 'done';
                if (!onDay.some((instance) => !isClosed(instance))) return 'none';
                return day < today ? 'missed' : 'open';
            });
            return [{ key, task, done: task.status === 'completed', days }];
        });
}
