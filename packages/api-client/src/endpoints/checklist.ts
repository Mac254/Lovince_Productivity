import type { HttpClient } from '../client/createHttpClient';
import type { ChecklistItem, ChecklistItemInput, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function checklistEndpoints(http: HttpClient) {
    return {
        async list(taskId: number, options: RequestOptions = {}): Promise<ChecklistItem[]> {
            const { data } = await http.get<Resource<ChecklistItem[]>>(`/tasks/${taskId}/checklist`, options);
            return data.data;
        },
        async create(taskId: number, title: string): Promise<ChecklistItem> {
            const { data } = await http.post<Resource<ChecklistItem>>(`/tasks/${taskId}/checklist`, { title });
            return data.data;
        },
        async update(id: number, input: ChecklistItemInput): Promise<ChecklistItem> {
            const { data } = await http.patch<Resource<ChecklistItem>>(`/checklist-items/${id}`, input);
            return data.data;
        },
        async reorder(taskId: number, ids: number[]): Promise<ChecklistItem[]> {
            const { data } = await http.put<Resource<ChecklistItem[]>>(`/tasks/${taskId}/checklist/order`, { ids });
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/checklist-items/${id}`);
        },
    };
}
