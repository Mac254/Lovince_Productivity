import type { GoalTone, ScheduleItem, TaskType } from '@focus/types';

export interface BalanceSlice {
    key: GoalTone | 'none';
    label: string;
    minutes: number;
    percent: number;
}

const order: (GoalTone | 'none')[] = ['rose', 'green', 'blue', 'violet', 'cyan', 'amber', 'none'];

/** A routine's time split by task type; types that share a tone count as one area, untyped time as its own. */
export function balanceOf(items: Pick<ScheduleItem, 'minutes' | 'task_type_id'>[], types: TaskType[]): BalanceSlice[] {
    const total = items.reduce((sum, item) => sum + item.minutes, 0);
    const slices = new Map<GoalTone | 'none', { minutes: number; names: Set<string> }>();
    for (const item of items) {
        const type = types.find((candidate) => candidate.id === item.task_type_id);
        const key = type?.tone ?? 'none';
        const slice = slices.get(key) ?? { minutes: 0, names: new Set<string>() };
        slice.minutes += item.minutes;
        if (type) slice.names.add(type.name);
        slices.set(key, slice);
    }
    return order
        .filter((key) => slices.get(key)?.minutes)
        .map((key) => {
            const slice = slices.get(key)!;
            return {
                key,
                label: key === 'none' ? 'No type' : [...slice.names].join(' · '),
                minutes: slice.minutes,
                percent: total ? Math.round((slice.minutes / total) * 100) : 0,
            };
        })
        .sort((a, b) => b.minutes - a.minutes);
}
