import type { AxiosInstance } from 'axios';
import type { Resource, TaskNote, TaskNoteInput } from '@focus/types';
import type { RequestOptions } from './types';

export function taskNoteEndpoints(http: AxiosInstance) {
    return {
        /** Newest day first. */
        async list(taskId: number, options: RequestOptions = {}): Promise<TaskNote[]> {
            const { data } = await http.get<Resource<TaskNote[]>>(`/tasks/${taskId}/notes`, options);
            return data.data;
        },
        async create(taskId: number, input: TaskNoteInput): Promise<TaskNote> {
            const { data } = await http.post<Resource<TaskNote>>(`/tasks/${taskId}/notes`, input);
            return data.data;
        },
        async update(id: number, input: TaskNoteInput): Promise<TaskNote> {
            const { data } = await http.patch<Resource<TaskNote>>(`/task-notes/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/task-notes/${id}`);
        },
    };
}
