import type { AxiosInstance } from 'axios';
import type { Resource, SearchResult } from '@focus/types';
import type { RequestOptions } from './types';

export function searchEndpoints(http: AxiosInstance) {
    return {
        async query(q: string, options: RequestOptions = {}): Promise<SearchResult[]> {
            const { data } = await http.get<Resource<SearchResult[]>>('/search', { params: { q }, ...options });
            return data.data;
        },
    };
}
