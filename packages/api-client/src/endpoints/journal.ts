import type { AxiosInstance } from 'axios';
import type { IsoDate, JournalContext, JournalEntry, Paginated, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function journalEndpoints(http: AxiosInstance) {
    return {
        /** Newest first. Pass `from` and `to` (at most 63 days apart) to get a calendar range in one page. */
        async list(
            filters: { search?: string; from?: IsoDate; to?: IsoDate } = {},
            options: RequestOptions = {},
        ): Promise<Paginated<JournalEntry>> {
            const { data } = await http.get<Paginated<JournalEntry>>('/journal', { params: filters, ...options });
            return data;
        },
        async get(date: IsoDate, options: RequestOptions = {}): Promise<JournalEntry | null> {
            const { data } = await http.get<Resource<JournalEntry | null>>(`/journal/${date}`, options);
            return data.data;
        },
        /** Tasks completed, work logged, blockers and still-open tasks for the day. */
        async context(date: IsoDate, options: RequestOptions = {}): Promise<JournalContext> {
            const { data } = await http.get<Resource<JournalContext>>(`/journal/${date}/context`, options);
            return data.data;
        },
        /** An empty body deletes the day's entry. */
        async save(date: IsoDate, body: string): Promise<JournalEntry | null> {
            const { data } = await http.put<Resource<JournalEntry | null>>(`/journal/${date}`, { body });
            return data.data;
        },
    };
}
