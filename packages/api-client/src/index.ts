import { createHttpClient } from './client/createHttpClient';
import { createApi } from './createApi';

let unauthorizedHandler: (() => void) | null = null;

/** The app-wide client. Use `createHttpClient` + `createApi` for isolated instances (tests). */
export const http = createHttpClient({ onUnauthorized: () => unauthorizedHandler?.() });
export const api = createApi(http);

export function onUnauthorized(handler: () => void): void {
    unauthorizedHandler = handler;
}

export { createApi, type FocusApi } from './createApi';
export { createHttpClient, refreshCsrfCookie, type HttpClientOptions } from './client/createHttpClient';
export { ApiError, errorMessage, isApiError, type ApiErrorKind } from './errors/ApiError';
export type { RequestOptions } from './endpoints/types';
