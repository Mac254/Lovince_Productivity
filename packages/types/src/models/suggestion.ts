import type { SuggestionCategory, SuggestionSource, SuggestionStatus } from '../generated/contract';
import type { IsoDate } from './task';

export type SuggestionAction =
    | { type: 'task'; goal_id?: number; minutes?: number }
    | { type: 'apply_schedule'; schedule_id: number; date: IsoDate }
    | { type: 'open'; route: string };

export interface Suggestion {
    id: number;
    source: SuggestionSource;
    title: string;
    body: string | null;
    category: SuggestionCategory;
    status: SuggestionStatus;
    action: SuggestionAction;
    task: { id: number; title: string } | null;
    created_at: string | null;
}

export interface SuggestionInput {
    title?: string;
    body?: string | null;
    category?: SuggestionCategory;
    status?: SuggestionStatus;
}
