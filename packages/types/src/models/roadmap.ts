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
    starts_on: IsoDate | null;
    ends_on: IsoDate | null;
    milestones?: RoadmapMilestone[];
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export type RoadmapItemInput = Partial<
    Pick<RoadmapItem, 'title' | 'details' | 'area' | 'stage' | 'done_on' | 'goal_id' | 'starts_on' | 'ends_on'>
>;
