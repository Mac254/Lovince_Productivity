import type { HttpClient } from '../client/createHttpClient';
import type { IsoDate, Resource, TimeRange, TimeSummary } from '@focus/types';
import type { RequestOptions } from './types';

export function timeEndpoints(http: HttpClient) {
    return {
        async get(range: TimeRange, date: IsoDate, options: RequestOptions = {}): Promise<TimeSummary> {
            const { data } = await http.get<Resource<TimeSummary>>('/time', { ...options, params: { range, date } });
            return data.data;
        },
    };
}
