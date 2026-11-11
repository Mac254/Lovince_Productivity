import type { HttpClient } from '../client/createHttpClient';
import type { Dashboard, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function dashboardEndpoints(http: HttpClient) {
    return {
        async get(options: RequestOptions = {}): Promise<Dashboard> {
            const { data } = await http.get<Resource<Dashboard>>('/dashboard', options);
            return data.data;
        },
    };
}
