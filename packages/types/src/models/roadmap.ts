import type { RoadmapStage } from '../generated/contract';
import type { IsoDate, IsoDateTime } from './task';

export interface RoadmapItem {
    id: number;
    title: string;
    details: string | null;
    area: string | null;
    stage: RoadmapStage;
    /** Order within its stage. */
    position: number;
    shipped_on: IsoDate | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export type RoadmapItemInput = Partial<Pick<RoadmapItem, 'title' | 'details' | 'area' | 'stage' | 'shipped_on'>>;
