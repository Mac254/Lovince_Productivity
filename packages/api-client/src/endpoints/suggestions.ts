import type { AxiosInstance } from 'axios';
import type { IsoDate, Resource, Suggestion, SuggestionInput, SuggestionStatus } from '@focus/types';
import type { RequestOptions } from './types';

export function suggestionEndpoints(http: AxiosInstance) {
    return {
        async list(status: SuggestionStatus | 'all' = 'new', options: RequestOptions = {}): Promise<Suggestion[]> {
            const { data } = await http.get<Resource<Suggestion[]>>('/suggestions', { ...options, params: { status } });
            return data.data;
        },
        async refresh(): Promise<number> {
            const { data } = await http.post<Resource<{ added: number }>>('/suggestions/refresh');
            return data.data.added;
        },
        async create(input: SuggestionInput): Promise<Suggestion> {
            const { data } = await http.post<Resource<Suggestion>>('/suggestions', input);
            return data.data;
        },
        async update(id: number, input: SuggestionInput): Promise<Suggestion> {
            const { data } = await http.patch<Resource<Suggestion>>(`/suggestions/${id}`, input);
            return data.data;
        },
        async accept(id: number, date?: IsoDate): Promise<Suggestion> {
            const { data } = await http.post<Resource<Suggestion>>(`/suggestions/${id}/accept`, date ? { date } : {});
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/suggestions/${id}`);
        },
    };
}
