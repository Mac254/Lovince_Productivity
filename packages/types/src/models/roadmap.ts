import type { RoadmapStage, TaskRecurrence, TaskStatus } from '../generated/contract';
import type { GoalRef, IsoDate, IsoDateTime } from './task';

/** A task of the phase's goal, due within the phase's dates. */
export interface RoadmapMilestone {
    id: number;
    title: string;
    status: TaskStatus;
    due_date: IsoDate | null;
    recurrence: TaskRecurrence | null;
}

/** The money goal a phase saves towards, with what is saved so far. */
export interface RoadmapMoneyGoal {
    id: number;
    name: string;
    currency: string;
    saved: number;
    target_amount: number;
}

export interface RoadmapItem {
    id: number;
    title: string;
    details: string | null;
    area: string | null;
    stage: RoadmapStage;
    /** Order within its stage. */
    position: number;
    done_on: IsoDate | null;
    goal_id: number | null;
    goal?: GoalRef | null;
    money_goal_id: number | null;
    money_goal?: RoadmapMoneyGoal | null;
    /** Where the money goal should be by the end of the phase, in minor units; the goal's own target when null. */
    target_amount: number | null;
    starts_on: IsoDate | null;
    ends_on: IsoDate | null;
    milestones?: RoadmapMilestone[];
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export type RoadmapItemInput = Partial<
    Pick<RoadmapItem, 'title' | 'details' | 'area' | 'stage' | 'done_on' | 'goal_id' | 'money_goal_id' | 'target_amount' | 'starts_on' | 'ends_on'>
>;
