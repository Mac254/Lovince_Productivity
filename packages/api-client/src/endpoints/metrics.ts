import type { AxiosInstance } from 'axios';
import type { MetricRange, Metrics, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function metricEndpoints(http: AxiosInstance) {
    return {
        async get(days: MetricRange, options: RequestOptions = {}): Promise<Metrics> {
            const { data } = await http.get<Resource<Metrics>>('/metrics', { params: { days }, ...options });
            return data.data;
        },
    };
}
