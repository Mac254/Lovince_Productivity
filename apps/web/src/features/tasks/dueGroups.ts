import type { Task } from '@focus/types';
import type { IconName } from '@focus/ui';
import { daysUntil, endOfWeekIso, todayIso } from '@/lib/dates';

export type DueGroupKey = 'overdue' | 'today' | 'week' | 'later' | 'none' | 'closed';

export interface DueGroup {
    key: DueGroupKey;
    title: string;
    icon: IconName;
    tasks: Task[];
}

const groups: Omit<DueGroup, 'tasks'>[] = [
    { key: 'overdue', title: 'Overdue', icon: 'warning' },
    { key: 'today', title: 'Today', icon: 'calendar' },
    { key: 'week', title: 'This week', icon: 'calendar-clock' },
    { key: 'later', title: 'Later', icon: 'calendar' },
    { key: 'none', title: 'No due date', icon: 'tasks' },
    { key: 'closed', title: 'Done and cancelled', icon: 'check-circle' },
];

function groupOf(task: Task, today: string, weekEnd: string): DueGroupKey {
    if (task.status === 'completed' || task.status === 'cancelled') return 'closed';
    if (!task.due_date) return 'none';
    const diff = daysUntil(task.due_date, today);
    if (diff < 0) return 'overdue';
    if (diff === 0) return 'today';
    return task.due_date <= weekEnd ? 'week' : 'later';
}

export function groupByDue(tasks: Task[], now = new Date()): DueGroup[] {
    const today = todayIso(now);
    const weekEnd = endOfWeekIso(now);
    return groups
        .map((group) => ({ ...group, tasks: tasks.filter((task) => groupOf(task, today, weekEnd) === group.key) }))
        .filter((group) => group.tasks.length);
}
