import { describe, expect, it } from 'vitest';
import { parseQuickAdd } from '@/lib/quickAdd';

const today = new Date(2026, 10, 4);
const context = {
    today,
    types: [{ id: 7, name: 'Deep Work' }],
    goals: [
        { id: 3, title: 'Launch the app', status: 'active' as const },
        { id: 4, title: 'Learn piano', status: 'completed' as const },
    ],
};

describe('parseQuickAdd', () => {
    it('keeps plain text as the title', () => {
        expect(parseQuickAdd('Call the bank', context).input).toEqual({ title: 'Call the bank' });
    });

    it('reads dates', () => {
        expect(parseQuickAdd('Pay rent tomorrow', context).input).toMatchObject({
            title: 'Pay rent',
            due_date: '2026-11-05',
        });
        expect(parseQuickAdd('Ship due fri', context).input.due_date).toBe('2026-11-06');
        expect(parseQuickAdd('Plan wed', context).input.due_date).toBe('2026-11-11');
        expect(parseQuickAdd('Plan next week', context).input.due_date).toBe('2026-11-09');
        expect(parseQuickAdd('Renew in 3 days', context).input.due_date).toBe('2026-11-07');
        expect(parseQuickAdd('File 2026-12-01', context).input.due_date).toBe('2026-12-01');
    });

    it('reads priority, estimate, star, repeat, type and goal', () => {
        const { input, parts } = parseQuickAdd('Write post !high ~1h30m * every week #deep-work +launch', context);
        expect(input).toEqual({
            title: 'Write post',
            priority: 'high',
            estimate_minutes: 90,
            is_highlighted: true,
            recurrence: 'weekly',
            due_date: '2026-11-04',
            task_type_id: 7,
            category: 'general',
            goal_id: 3,
        });
        expect(parts).toHaveLength(6);
    });

    it('leaves unknown tags, closed goals and lone words in the title', () => {
        expect(parseQuickAdd('Fix #unknown +learn on call', context).input).toEqual({
            title: 'Fix #unknown +learn on call',
        });
        expect(parseQuickAdd('Fix bug #dev', context).input).toMatchObject({
            title: 'Fix bug',
            category: 'development',
        });
    });
});
