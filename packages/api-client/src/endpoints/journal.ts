import type { AxiosInstance } from 'axios';
import type { IsoDate, JournalEntry, Paginated, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function journalEndpoints(http: AxiosInstance) {
    return {
        async list(search?: string, options: RequestOptions = {}): Promise<Paginated<JournalEntry>> {
            const { data } = await http.get<Paginated<JournalEntry>>('/journal', { params: { search }, ...options });
            return data;
        },
        async get(date: IsoDate, options: RequestOptions = {}): Promise<JournalEntry | null> {
            const { data } = await http.get<Resource<JournalEntry | null>>(`/journal/${date}`, options);
            return data.data;
        },
        /** An empty body deletes the day's entry. */
        async save(date: IsoDate, body: string): Promise<JournalEntry | null> {
            const { data } = await http.put<Resource<JournalEntry | null>>(`/journal/${date}`, { body });
            return data.data;
        },
    };
}
