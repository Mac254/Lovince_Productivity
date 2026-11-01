import type { IsoDate } from '../models/task';

export type SearchKind = 'task' | 'goal' | 'blocker' | 'journal';

export interface SearchResult {
    kind: SearchKind;
    id: number;
    title: string;
    excerpt: string | null;
    date: IsoDate | null;
    task_id: number | null;
}
