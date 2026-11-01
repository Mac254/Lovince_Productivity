import type { AxiosInstance } from 'axios';
import type { FocusHistory, FocusSession, FocusSessionInput, IsoDate, Resource } from '@focus/types';
import type { RequestOptions } from './types';

export function focusSessionEndpoints(http: AxiosInstance) {
    return {
        async list(date?: IsoDate, options: RequestOptions = {}): Promise<FocusHistory> {
            const { data } = await http.get<FocusHistory>('/focus-sessions', { ...options, params: { date } });
            return data;
        },
        async create(input: FocusSessionInput): Promise<FocusSession> {
            const { data } = await http.post<Resource<FocusSession>>('/focus-sessions', input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/focus-sessions/${id}`);
        },
    };
}
