import type { AchievementCategory } from '../generated/contract';
import type { GoalRef, IsoDate, IsoDateTime } from './task';

export interface Achievement {
    id: number;
    title: string;
    description: string | null;
    achieved_on: IsoDate;
    category: AchievementCategory | null;
    goal_id: number | null;
    goal?: GoalRef | null;
    task_id: number | null;
    task?: { id: number; title: string } | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}
