import type { AxiosInstance } from 'axios';
import type { Quote, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function quoteEndpoints(http: AxiosInstance) {
    return {
        /** Returns `null` when no quotes exist; `exclude` avoids repeating the current one. */
        async random(exclude?: number, options: RequestOptions = {}): Promise<Quote | null> {
            const { data } = await http.get<Resource<Quote | null>>('/quotes/random', {
                params: { exclude },
                ...options,
            });
            return data.data;
        },
    };
}
