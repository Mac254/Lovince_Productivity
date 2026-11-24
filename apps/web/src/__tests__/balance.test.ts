import { describe, expect, it } from 'vitest';
import type { TaskType } from '@focus/types';
import { balanceOf } from '@/features/planner/balance';

const type = (id: number, name: string, tone: TaskType['tone']): TaskType => ({ id, name, tone, created_at: '' });
const types = [type(1, 'Dev', 'green'), type(3, 'Work', 'rose'), type(5, 'Study', 'green')];

describe('balanceOf', () => {
    it('groups time by type tone, largest first, with untyped time apart', () => {
        const slices = balanceOf(
            [
                { minutes: 450, task_type_id: 3 },
                { minutes: 60, task_type_id: 1 },
                { minutes: 30, task_type_id: 5 },
                { minutes: 60, task_type_id: null },
            ],
            types,
        );

        expect(slices).toEqual([
            { key: 'rose', label: 'Work', minutes: 450, percent: 75 },
            { key: 'green', label: 'Dev · Study', minutes: 90, percent: 15 },
            { key: 'none', label: 'No type', minutes: 60, percent: 10 },
        ]);
    });

    it('is empty for an empty routine', () => {
        expect(balanceOf([], types)).toEqual([]);
    });
});
