import type { AxiosInstance } from 'axios';
import type { AuthSession, LoginInput, Me, User } from '@focus/types';
import { refreshCsrfCookie } from '../client/createHttpClient';
import type { RequestOptions } from './types';

interface MeResponse {
    data: User;
    meta: { session: AuthSession };
}

const toMe = ({ data, meta }: MeResponse): Me => ({ user: data, session: meta.session });

export function authEndpoints(http: AxiosInstance) {
    return {
        /** Signs in with a session cookie; `remember` keeps this device signed in until expiry or sign-out. */
        async login(input: LoginInput): Promise<Me> {
            await refreshCsrfCookie(http);
            const { data } = await http.post<MeResponse>('/auth/login', input);
            return toMe(data);
        },
        async logout(): Promise<void> {
            await http.post('/auth/logout');
        },
        /** The signed-in user and this device's sign-in state. Rejects with `unauthorized` when signed out. */
        async me(options: RequestOptions = {}): Promise<Me> {
            const { data } = await http.get<MeResponse>('/auth/me', options);
            return toMe(data);
        },
    };
}
