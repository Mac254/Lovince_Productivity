import type { HttpClient } from '../client/createHttpClient';
import type { IsoDate, Resource, TimeBlock, TimeBlockInput } from '@focus/types';
import type { RequestOptions } from './types';

export function timeBlockEndpoints(http: HttpClient) {
    return {
        async list(date: IsoDate, options: RequestOptions = {}): Promise<TimeBlock[]> {
            const { data } = await http.get<Resource<TimeBlock[]>>('/time-blocks', { ...options, params: { date } });
            return data.data;
        },
        async create(input: TimeBlockInput): Promise<TimeBlock> {
            const { data } = await http.post<Resource<TimeBlock>>('/time-blocks', input);
            return data.data;
        },
        async update(id: number, input: TimeBlockInput): Promise<TimeBlock> {
            const { data } = await http.patch<Resource<TimeBlock>>(`/time-blocks/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/time-blocks/${id}`);
        },
    };
}
