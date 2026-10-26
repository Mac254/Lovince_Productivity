import type { AxiosInstance } from 'axios';
import type { Paginated, Resource, Task, TaskFilters, TaskInput } from '@focus/types';
import type { RequestOptions } from './types';

export function taskEndpoints(http: AxiosInstance) {
    return {
        async list(filters: TaskFilters = {}, options: RequestOptions = {}): Promise<Paginated<Task>> {
            const { data } = await http.get<Paginated<Task>>('/tasks', { params: filters, ...options });
            return data;
        },
        async get(id: number, options: RequestOptions = {}): Promise<Task> {
            const { data } = await http.get<Resource<Task>>(`/tasks/${id}`, options);
            return data.data;
        },
        async create(input: TaskInput): Promise<Task> {
            const { data } = await http.post<Resource<Task>>('/tasks', input);
            return data.data;
        },
        async update(id: number, input: TaskInput): Promise<Task> {
            const { data } = await http.patch<Resource<Task>>(`/tasks/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/tasks/${id}`);
        },
    };
}
