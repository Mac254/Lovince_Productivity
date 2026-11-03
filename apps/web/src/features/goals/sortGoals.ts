import type { Goal } from '@focus/types';
import type { TableSort } from '@/components/SortableHead.vue';

export type GoalSortKey = 'title' | 'status' | 'timeframe' | 'progress' | 'target' | 'created';
export type GoalSort = TableSort<GoalSortKey>;

const value: Record<GoalSortKey, (goal: Goal) => string | number> = {
    title: (goal) => goal.title.toLowerCase(),
    status: (goal) => goal.status,
    timeframe: (goal) => goal.timeframe,
    progress: (goal) => goal.progress,
    target: (goal) => goal.target_date ?? '9999-12-31',
    created: (goal) => goal.created_at,
};

export function sortGoals(goals: Goal[], { key, direction }: GoalSort): Goal[] {
    const sign = direction === 'asc' ? 1 : -1;
    return [...goals].sort((a, b) => {
        const left = value[key](a);
        const right = value[key](b);
        if (left === right) return b.id - a.id;
        return (left < right ? -1 : 1) * sign;
    });
}
