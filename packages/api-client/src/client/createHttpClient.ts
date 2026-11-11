import { HttpError, type HttpResponse } from '../errors/HttpError';
import { normalizeError } from '../errors/normalizeError';

export type { HttpResponse };
import { readCookie } from './cookies';

export interface HttpClientOptions {
    baseURL?: string;
    timeout?: number;
    /** Swap the transport, e.g. in tests. */
    fetch?: typeof fetch;
    /** Called when a request comes back 401 (the session has ended). */
    onUnauthorized?: () => void;
}

export interface RequestConfig {
    params?: object;
    headers?: Record<string, string>;
    signal?: AbortSignal;
    timeout?: number;
    responseType?: 'json' | 'blob';
    baseURL?: string;
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface HttpRequest extends RequestConfig {
    method: Method;
    url: string;
    body?: unknown;
}

export interface HttpClient {
    get<T = unknown>(url: string, config?: RequestConfig): Promise<HttpResponse<T>>;
    delete<T = unknown>(url: string, config?: RequestConfig): Promise<HttpResponse<T>>;
    post<T = unknown>(url: string, body?: unknown, config?: RequestConfig): Promise<HttpResponse<T>>;
    put<T = unknown>(url: string, body?: unknown, config?: RequestConfig): Promise<HttpResponse<T>>;
    patch<T = unknown>(url: string, body?: unknown, config?: RequestConfig): Promise<HttpResponse<T>>;
}

const CSRF_COOKIE = 'XSRF-TOKEN';
const CSRF_HEADER = 'X-XSRF-TOKEN';

export const CSRF_COOKIE_PATH = '/sanctum/csrf-cookie';

const SERVER_RETRY_DELAY_MS = 400;
const TRANSIENT_STATUSES = new Set([500, 502, 503, 504]);

function query(params: object | undefined): string {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params ?? {})) {
        if (value === undefined || value === null || value === '') continue;
        if (Array.isArray(value)) value.forEach((item) => search.append(`${key}[]`, String(item)));
        else search.append(key, String(value));
    }
    const text = search.toString();
    return text ? `?${text}` : '';
}

function join(base: string, url: string): string {
    return `${base.replace(/\/$/, '')}/${url.replace(/^\//, '')}`;
}

async function read(response: Response, type: RequestConfig['responseType']): Promise<unknown> {
    if (response.ok && type === 'blob') return response.blob();
    const text = await response.text();
    if (!text) return null;
    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}

/**
 * Same-origin, cookie-session client for the Focus API. It attaches the CSRF token,
 * strips empty query params, retries once after refreshing an expired CSRF token,
 * retries a read once after a transient server error,
 * and rejects with a normalised `ApiError`.
 */
export function createHttpClient(options: HttpClientOptions = {}): HttpClient {
    const transport = options.fetch ?? ((input, init) => fetch(input, init));
    const baseURL = options.baseURL ?? '/api/v1';
    const defaultTimeout = options.timeout ?? 15_000;

    async function send<T>(request: HttpRequest): Promise<HttpResponse<T>> {
        const headers = new Headers({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
        const token = readCookie(CSRF_COOKIE);
        if (token) headers.set(CSRF_HEADER, token);
        for (const [name, value] of Object.entries(request.headers ?? {})) headers.set(name, value);

        let body: BodyInit | undefined;
        if (request.body instanceof FormData || request.body instanceof Blob) body = request.body;
        else if (request.body !== undefined) {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(request.body);
        }

        const timeout = AbortSignal.timeout(request.timeout ?? defaultTimeout);
        const response = await transport(join(request.baseURL ?? baseURL, request.url) + query(request.params), {
            method: request.method,
            headers,
            body,
            credentials: 'same-origin',
            signal: request.signal ? AbortSignal.any([request.signal, timeout]) : timeout,
        });

        const result = {
            data: (await read(response, request.responseType)) as T,
            status: response.status,
            headers: response.headers,
        };
        if (!response.ok) throw new HttpError(result);
        return result;
    }

    async function request<T>(config: HttpRequest): Promise<HttpResponse<T>> {
        let csrfRetried = false;
        let serverRetried = false;
        for (;;) {
            try {
                return await send<T>(config);
            } catch (error) {
                const status = error instanceof HttpError ? error.response.status : undefined;

                if (status === 419 && !csrfRetried) {
                    csrfRetried = true;
                    await refreshCsrfCookie(http);
                    continue;
                }
                if (status && TRANSIENT_STATUSES.has(status) && config.method === 'GET' && !serverRetried) {
                    serverRetried = true;
                    await new Promise((resolve) => setTimeout(resolve, SERVER_RETRY_DELAY_MS));
                    continue;
                }
                if (status === 401) options.onUnauthorized?.();

                throw normalizeError(error);
            }
        }
    }

    const http: HttpClient = {
        get: (url, config) => request({ ...config, method: 'GET', url }),
        delete: (url, config) => request({ ...config, method: 'DELETE', url }),
        post: (url, body, config) => request({ ...config, method: 'POST', url, body }),
        put: (url, body, config) => request({ ...config, method: 'PUT', url, body }),
        patch: (url, body, config) => request({ ...config, method: 'PATCH', url, body }),
    };
    return http;
}

export async function refreshCsrfCookie(http: HttpClient): Promise<void> {
    await http.get(CSRF_COOKIE_PATH, { baseURL: '/' });
}
