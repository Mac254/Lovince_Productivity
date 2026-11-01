import type { AxiosInstance } from 'axios';
import type { IsoDate, Resource, WeeklyReview, WeeklyReviewInput, WeeklyReviewSummary } from '@focus/types';
import type { RequestOptions } from './types';

export function reviewEndpoints(http: AxiosInstance) {
    return {
        async list(options: RequestOptions = {}): Promise<WeeklyReviewSummary[]> {
            const { data } = await http.get<Resource<WeeklyReviewSummary[]>>('/reviews', options);
            return data.data;
        },
        async get(week: IsoDate, options: RequestOptions = {}): Promise<WeeklyReview> {
            const { data } = await http.get<Resource<WeeklyReview>>(`/reviews/${week}`, options);
            return data.data;
        },
        async save(week: IsoDate, input: WeeklyReviewInput): Promise<WeeklyReview> {
            const { data } = await http.put<Resource<WeeklyReview>>(`/reviews/${week}`, input);
            return data.data;
        },
    };
}
