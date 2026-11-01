import type { FocusToday } from '@focus/types';
import { formatMinutes } from '@/lib/dates';

export function goalProgress(minutes: number, goal: number): number | undefined {
    return goal > 0 ? Math.min(100, Math.round((minutes / goal) * 100)) : undefined;
}

export function streakLabel(days: number): string {
    return days ? `${days}-day streak` : 'No streak yet';
}

export function focusSummary(focus: FocusToday | undefined) {
    const minutes = focus?.minutes ?? 0;
    const goal = focus?.goal_minutes ?? 0;
    const short = minutes < 120;
    const parts = [
        goal ? `of ${formatMinutes(goal)} goal` : `${focus?.sessions ?? 0} sessions`,
        streakLabel(focus?.streak ?? 0),
    ];

    return {
        value: short ? minutes : Math.round(minutes / 60),
        suffix: short ? 'm' : 'h',
        hint: parts.join(' · '),
        progress: goalProgress(minutes, goal),
    };
}
