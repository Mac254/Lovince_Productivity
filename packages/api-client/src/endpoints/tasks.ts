import type { HttpClient } from '../client/createHttpClient';
import type { Paginated, Resource, Task, TaskFilters, TaskInput, TaskUpdateInput } from '@focus/types';
import type { RequestOptions } from './types';

export function taskEndpoints(http: HttpClient) {
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
        async update(id: number, input: TaskUpdateInput): Promise<Task> {
            const { data } = await http.patch<Resource<Task>>(`/tasks/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/tasks/${id}`);
        },
        async moveToToday(ids: number[]): Promise<number> {
            const { data } = await http.post<{ moved: number }>('/tasks/move-to-today', { ids });
            return data.moved;
        },
        async uploadCover(id: number, file: File): Promise<Task> {
            const form = new FormData();
            form.append('image', file);
            const { data } = await http.post<Resource<Task>>(`/tasks/${id}/cover`, form, { timeout: 60_000 });
            return data.data;
        },
        async removeCover(id: number): Promise<Task> {
            const { data } = await http.delete<Resource<Task>>(`/tasks/${id}/cover`);
            return data.data;
        },
    };
}
