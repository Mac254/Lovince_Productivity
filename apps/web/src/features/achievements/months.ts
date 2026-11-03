import type { Achievement } from '@focus/types';
import { parseDate } from '@/lib/dates';

export interface AchievementMonth {
    key: string;
    label: string;
    items: Achievement[];
}

export function groupByMonth(achievements: Achievement[]): AchievementMonth[] {
    const months = new Map<string, AchievementMonth>();
    for (const achievement of achievements) {
        const key = achievement.achieved_on.slice(0, 7);
        let month = months.get(key);
        if (!month) {
            const label = parseDate(`${key}-01`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            month = { key, label, items: [] };
            months.set(key, month);
        }
        month.items.push(achievement);
    }
    return [...months.values()].sort((a, b) => b.key.localeCompare(a.key));
}
