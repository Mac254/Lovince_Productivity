import type { HttpClient } from '../client/createHttpClient';
import type { IsoDate, Resource, Review, ReviewInput, ReviewPeriod, ReviewSummary } from '@focus/types';
import type { RequestOptions } from './types';

export function reviewEndpoints(http: HttpClient) {
    return {
        /** Finished reviews, newest first; all periods unless one is given. */
        async list(period?: ReviewPeriod, options: RequestOptions = {}): Promise<ReviewSummary[]> {
            const { data } = await http.get<Resource<ReviewSummary[]>>('/reviews', {
                ...options,
                params: period ? { period } : undefined,
            });
            return data.data;
        },
        /** The review of the week, month or year that contains `date`. */
        async get(period: ReviewPeriod, date: IsoDate, options: RequestOptions = {}): Promise<Review> {
            const { data } = await http.get<Resource<Review>>(`/reviews/${period}/${date}`, options);
            return data.data;
        },
        async save(period: ReviewPeriod, date: IsoDate, input: ReviewInput): Promise<Review> {
            const { data } = await http.put<Resource<Review>>(`/reviews/${period}/${date}`, input);
            return data.data;
        },
    };
}
