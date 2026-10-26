import type { BlockerStatus } from '../generated/contract';
import type { IsoDateTime } from './task';

export interface Blocker {
    id: number;
    description: string;
    status: BlockerStatus;
    task_id: number | null;
    task?: { id: number; title: string } | null;
    resolved_at: IsoDateTime | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}
