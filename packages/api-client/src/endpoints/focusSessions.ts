import type { HttpClient } from '../client/createHttpClient';
import type {
    FocusHistory,
    FocusSession,
    FocusSessionInput,
    FocusTimerState,
    FocusTimerSync,
    IsoDate,
    Resource,
} from '@focus/types';
import type { RequestOptions } from './types';

export function focusSessionEndpoints(http: HttpClient) {
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

export function focusTimerEndpoints(http: HttpClient) {
    return {
        async get(options: RequestOptions = {}): Promise<FocusTimerSync> {
            const { data } = await http.get<Resource<FocusTimerSync>>('/focus-timer', options);
            return data.data;
        },
        async save(revision: number, state: FocusTimerState): Promise<FocusTimerSync> {
            const { data } = await http.put<Resource<FocusTimerSync>>('/focus-timer', { revision, state });
            return data.data;
        },
    };
}
