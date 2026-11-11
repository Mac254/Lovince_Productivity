import type { HttpClient } from '../client/createHttpClient';
import type { Achievement, AchievementFilters, AchievementInput, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function achievementEndpoints(http: HttpClient) {
    return {
        async list(filters: AchievementFilters = {}, options: RequestOptions = {}): Promise<Achievement[]> {
            const { data } = await http.get<Resource<Achievement[]>>('/achievements', { params: filters, ...options });
            return data.data;
        },
        async create(input: AchievementInput): Promise<Achievement> {
            const { data } = await http.post<Resource<Achievement>>('/achievements', input);
            return data.data;
        },
        async update(id: number, input: AchievementInput): Promise<Achievement> {
            const { data } = await http.patch<Resource<Achievement>>(`/achievements/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/achievements/${id}`);
        },
    };
}
