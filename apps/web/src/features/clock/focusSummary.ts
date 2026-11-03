import type { FocusToday } from '@focus/types';
import { formatMinutes } from '@/lib/dates';

export function goalProgress(minutes: number, goal: number): number | undefined {
    return goal > 0 ? Math.min(100, Math.round((minutes / goal) * 100)) : undefined;
}

export function focusSummary(focus: FocusToday | undefined) {
    const minutes = focus?.minutes ?? 0;
    const goal = focus?.goal_minutes ?? 0;
    const short = minutes < 120;
    const hint = goal ? `of ${formatMinutes(goal)} goal` : `${focus?.sessions ?? 0} sessions`;

    return {
        value: short ? minutes : Math.round(minutes / 60),
        suffix: short ? 'm' : 'h',
        hint,
        progress: goalProgress(minutes, goal),
    };
}
