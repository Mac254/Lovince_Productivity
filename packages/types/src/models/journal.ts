import type { IsoDate, IsoDateTime } from './task';

export interface JournalEntry {
    id: number;
    date: IsoDate;
    body: string;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}
