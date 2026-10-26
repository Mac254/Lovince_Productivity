import type { AxiosInstance } from 'axios';
import type { Blocker, BlockerFilters, BlockerInput, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function blockerEndpoints(http: AxiosInstance) {
    return {
        async list(filters: BlockerFilters = {}, options: RequestOptions = {}): Promise<Blocker[]> {
            const { data } = await http.get<Resource<Blocker[]>>('/blockers', { params: filters, ...options });
            return data.data;
        },
        async create(input: BlockerInput): Promise<Blocker> {
            const { data } = await http.post<Resource<Blocker>>('/blockers', input);
            return data.data;
        },
        async update(id: number, input: BlockerInput): Promise<Blocker> {
            const { data } = await http.patch<Resource<Blocker>>(`/blockers/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/blockers/${id}`);
        },
    };
}
