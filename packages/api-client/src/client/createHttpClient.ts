import axios, { type AxiosAdapter, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';
import { normalizeError } from '../errors/normalizeError';
import { readCookie } from './cookies';

export interface HttpClientOptions {
    baseURL?: string;
    timeout?: number;
    /** Swap the transport, e.g. in tests. */
    adapter?: AxiosAdapter;
    /** Called when a request comes back 401 (the session has ended). */
    onUnauthorized?: () => void;
}

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean };

const CSRF_COOKIE = 'XSRF-TOKEN';
const CSRF_HEADER = 'X-XSRF-TOKEN';

export const CSRF_COOKIE_PATH = '/sanctum/csrf-cookie';

function withoutEmptyParams(params: unknown): unknown {
    if (!params || typeof params !== 'object') return params;
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== ''),
    );
}

/**
 * Same-origin, cookie-session client for the Focus API. It attaches the CSRF token,
 * strips empty query params, retries once after refreshing an expired CSRF token,
 * and rejects with a normalised `ApiError`.
 */
export function createHttpClient(options: HttpClientOptions = {}): AxiosInstance {
    const http = axios.create({
        baseURL: options.baseURL ?? '/api/v1',
        timeout: options.timeout ?? 15_000,
        withCredentials: true,
        adapter: options.adapter,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    http.interceptors.request.use((config) => {
        const token = readCookie(CSRF_COOKIE);
        if (token) config.headers.set(CSRF_HEADER, token);
        config.params = withoutEmptyParams(config.params);
        return config;
    });

    http.interceptors.response.use(undefined, async (error) => {
        const config = error?.config as RetriableConfig | undefined;
        const status: number | undefined = error?.response?.status;

        if (status === 419 && config && !config._csrfRetried) {
            config._csrfRetried = true;
            await refreshCsrfCookie(http);
            return http.request(config);
        }

        if (status === 401) options.onUnauthorized?.();

        throw normalizeError(error);
    });

    return http;
}

export async function refreshCsrfCookie(http: AxiosInstance): Promise<void> {
    await http.get(CSRF_COOKIE_PATH, { baseURL: '/' });
}
