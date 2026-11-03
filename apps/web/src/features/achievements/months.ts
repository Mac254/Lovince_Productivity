import type { Achievement } from '@focus/types';
import { parseDate } from '@/lib/dates';

export type AchievementOrder = 'newest' | 'oldest';

export interface AchievementDay {
    date: string;
    items: Achievement[];
}

export interface AchievementMonth {
    key: string;
    label: string;
    count: number;
    days: AchievementDay[];
}

export interface AchievementYear {
    year: string;
    count: number;
    months: AchievementMonth[];
}

function last<T>(list: T[]): T | undefined {
    return list[list.length - 1];
}

export function groupByDate(achievements: Achievement[], order: AchievementOrder = 'newest'): AchievementYear[] {
    const direction = order === 'newest' ? -1 : 1;
    const sorted = [...achievements].sort(
        (a, b) => direction * (a.achieved_on.localeCompare(b.achieved_on) || a.id - b.id),
    );
    const years: AchievementYear[] = [];
    for (const achievement of sorted) {
        const date = achievement.achieved_on;
        const year = date.slice(0, 4);
        const key = date.slice(0, 7);

        let yearGroup = last(years);
        if (yearGroup?.year !== year) {
            yearGroup = { year, count: 0, months: [] };
            years.push(yearGroup);
        }
        let month = last(yearGroup.months);
        if (month?.key !== key) {
            const label = parseDate(`${key}-01`).toLocaleDateString(undefined, { month: 'long' });
            month = { key, label, count: 0, days: [] };
            yearGroup.months.push(month);
        }
        let day = last(month.days);
        if (day?.date !== date) {
            day = { date, items: [] };
            month.days.push(day);
        }
        day.items.push(achievement);
        month.count++;
        yearGroup.count++;
    }
    return years;
}
