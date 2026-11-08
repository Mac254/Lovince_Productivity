import type { AxiosInstance } from 'axios';
import type { Resource, RoadmapItem, RoadmapItemInput, RoadmapStage } from '@focus/types';
import type { RequestOptions } from './types';

export function roadmapEndpoints(http: AxiosInstance) {
    return {
        async list(options: RequestOptions = {}): Promise<RoadmapItem[]> {
            const { data } = await http.get<Resource<RoadmapItem[]>>('/roadmap-items', options);
            return data.data;
        },
        async create(input: RoadmapItemInput): Promise<RoadmapItem> {
            const { data } = await http.post<Resource<RoadmapItem>>('/roadmap-items', input);
            return data.data;
        },
        async update(id: number, input: RoadmapItemInput): Promise<RoadmapItem> {
            const { data } = await http.patch<Resource<RoadmapItem>>(`/roadmap-items/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/roadmap-items/${id}`);
        },
        /** Puts these items into `stage` in this order. */
        async reorder(stage: RoadmapStage, ids: number[]): Promise<RoadmapItem[]> {
            const { data } = await http.post<Resource<RoadmapItem[]>>('/roadmap-items/reorder', { stage, ids });
            return data.data;
        },
    };
}
