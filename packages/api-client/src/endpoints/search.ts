import type { HttpClient } from '../client/createHttpClient';
import type { Resource, SearchResult } from '@focus/types';
import type { RequestOptions } from './types';

export function searchEndpoints(http: HttpClient) {
    return {
        async query(q: string, options: RequestOptions = {}): Promise<SearchResult[]> {
            const { data } = await http.get<Resource<SearchResult[]>>('/search', { params: { q }, ...options });
            return data.data;
        },
    };
}
