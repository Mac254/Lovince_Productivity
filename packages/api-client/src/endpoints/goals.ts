import type { AxiosInstance } from 'axios';
import type { Goal, GoalFilters, GoalInput, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function goalEndpoints(http: AxiosInstance) {
    return {
        async list(filters: GoalFilters = {}, options: RequestOptions = {}): Promise<Goal[]> {
            const { data } = await http.get<Resource<Goal[]>>('/goals', { params: filters, ...options });
            return data.data;
        },
        async get(id: number, options: RequestOptions = {}): Promise<Goal> {
            const { data } = await http.get<Resource<Goal>>(`/goals/${id}`, options);
            return data.data;
        },
        async create(input: GoalInput): Promise<Goal> {
            const { data } = await http.post<Resource<Goal>>('/goals', input);
            return data.data;
        },
        async update(id: number, input: GoalInput): Promise<Goal> {
            const { data } = await http.patch<Resource<Goal>>(`/goals/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/goals/${id}`);
        },
    };
}
