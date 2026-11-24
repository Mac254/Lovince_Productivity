import type { HabitSummary, HabitWindow, Resource } from '@focus/types';
import type { HttpClient } from '../client/createHttpClient';
import type { RequestOptions } from './types';

export function habitEndpoints(http: HttpClient) {
    return {
        async get(days: HabitWindow, options: RequestOptions = {}): Promise<HabitSummary> {
            const { data } = await http.get<Resource<HabitSummary>>('/habits', { ...options, params: { days } });
            return data.data;
        },
    };
}
