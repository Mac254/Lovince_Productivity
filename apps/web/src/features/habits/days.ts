import type { Habit, HabitDayStatus } from '@focus/types';

export const dayTone: Record<HabitDayStatus, string> = {
    done: 'bg-highlight',
    short: 'bg-highlight-hover',
    skipped: 'bg-border-strong',
    missed: 'bg-danger',
    open: 'border border-dashed border-border-strong',
};

export const dayWord: Record<HabitDayStatus, string> = {
    done: 'Done',
    short: 'Done, short version',
    skipped: 'Skipped',
    missed: 'Missed',
    open: 'Due today',
};

/** Habits due today and how many of them are already done. */
export function todayTally(habits: Habit[]): { done: number; due: number } {
    const due = habits.filter((habit) => habit.today !== null);
    return {
        done: due.filter((habit) => habit.today === 'done' || habit.today === 'short').length,
        due: due.length,
    };
}

/** Days done over days planned, across every habit. */
export function overallRate(habits: Habit[]): number | null {
    const planned = habits.reduce((sum, habit) => sum + habit.planned, 0);
    if (!planned) return null;
    return Math.round((habits.reduce((sum, habit) => sum + habit.done, 0) / planned) * 100);
}
