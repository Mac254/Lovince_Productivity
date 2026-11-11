import type { HttpClient } from '../client/createHttpClient';
import type { IsoDate, Resource, Schedule, ScheduleInput, TimeBlock } from '@focus/types';
import type { RequestOptions } from './types';

export function scheduleEndpoints(http: HttpClient) {
    return {
        async list(options: RequestOptions = {}): Promise<Schedule[]> {
            const { data } = await http.get<Resource<Schedule[]>>('/schedules', options);
            return data.data;
        },
        async create(input: ScheduleInput): Promise<Schedule> {
            const { data } = await http.post<Resource<Schedule>>('/schedules', input);
            return data.data;
        },
        async update(id: number, input: ScheduleInput): Promise<Schedule> {
            const { data } = await http.patch<Resource<Schedule>>(`/schedules/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/schedules/${id}`);
        },
        async apply(id: number, date: IsoDate, replace = false): Promise<TimeBlock[]> {
            const { data } = await http.post<Resource<TimeBlock[]>>(`/schedules/${id}/apply`, { date, replace });
            return data.data;
        },
    };
}
