import type { TimeCategory } from '@focus/types';
import { toneDot } from '@/lib/tones';

/** A category's swatch: its type's tone, or the neutral for time with no type. */
export function categoryClass(category: Pick<TimeCategory, 'tone'>): string {
    return category.tone ? toneDot[category.tone] : 'bg-chart-2';
}

/** The CSS variable behind that swatch, for the chart. */
export function categoryColor(category: Pick<TimeCategory, 'tone'>, css: CSSStyleDeclaration): string {
    return css.getPropertyValue(category.tone ? `--tone-${category.tone}` : '--chart-2').trim();
}

/** `+12%`-style change between two averages, or null when there is nothing to compare. */
export function change(current: number, previous: number): number | null {
    if (!previous) return null;
    return Math.round(((current - previous) / previous) * 100);
}
