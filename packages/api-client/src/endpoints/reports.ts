import type { AxiosInstance } from 'axios';
import type { DailyReport, DailyReportSummary, IsoDate, Paginated, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function reportEndpoints(http: AxiosInstance) {
    return {
        async list(page = 1, options: RequestOptions = {}): Promise<Paginated<DailyReportSummary>> {
            const { data } = await http.get<Paginated<DailyReportSummary>>('/reports', {
                params: { page },
                ...options,
            });
            return data;
        },
        async get(date: IsoDate, options: RequestOptions = {}): Promise<DailyReport> {
            const { data } = await http.get<Resource<DailyReport>>(`/reports/${date}`, options);
            return data.data;
        },
        /** (Re)generates today's report. */
        async generateToday(): Promise<DailyReport> {
            const { data } = await http.post<Resource<DailyReport>>('/reports');
            return data.data;
        },
    };
}
