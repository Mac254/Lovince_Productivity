import type { AxiosInstance } from 'axios';
import type { Resource, TaskType, TaskTypeInput } from '@focus/types';
import type { RequestOptions } from './types';

export function taskTypeEndpoints(http: AxiosInstance) {
    return {
        async list(options: RequestOptions = {}): Promise<TaskType[]> {
            const { data } = await http.get<Resource<TaskType[]>>('/task-types', options);
            return data.data;
        },
        async create(input: TaskTypeInput): Promise<TaskType> {
            const { data } = await http.post<Resource<TaskType>>('/task-types', input);
            return data.data;
        },
        async update(id: number, input: TaskTypeInput): Promise<TaskType> {
            const { data } = await http.patch<Resource<TaskType>>(`/task-types/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/task-types/${id}`);
        },
    };
}
