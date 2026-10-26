import { AxiosError, isAxiosError, isCancel } from 'axios';
import type { ValidationErrors } from '@focus/types';
import { ApiError, type ApiErrorKind } from './ApiError';

interface LaravelErrorBody {
    message?: string;
    errors?: ValidationErrors;
}

const MESSAGES: Record<ApiErrorKind, string> = {
    network: 'Could not reach the server. Check your connection and try again.',
    timeout: 'The server took too long to respond. Please try again.',
    cancelled: 'The request was cancelled.',
    validation: 'Please check the highlighted fields.',
    unauthorized: 'Your session has ended. Please sign in again.',
    forbidden: 'You don’t have permission to do that.',
    not_found: 'That item no longer exists.',
    csrf: 'Your session expired. Please try again.',
    rate_limited: 'Too many attempts. Please wait a moment and try again.',
    server: 'Something went wrong on the server. Please try again.',
    unknown: 'The request could not be completed.',
};

function kindForStatus(status: number): ApiErrorKind {
    if (status === 401) return 'unauthorized';
    if (status === 403) return 'forbidden';
    if (status === 404) return 'not_found';
    if (status === 419) return 'csrf';
    if (status === 422) return 'validation';
    if (status === 429) return 'rate_limited';
    if (status >= 500) return 'server';
    return 'unknown';
}

/** Server messages are only surfaced where Laravel writes them for people (validation, throttling). */
export function normalizeError(error: unknown): ApiError {
    if (error instanceof ApiError) return error;
    if (isCancel(error)) return new ApiError({ kind: 'cancelled', message: MESSAGES.cancelled });
    if (!isAxiosError(error)) return new ApiError({ kind: 'unknown', message: MESSAGES.unknown });

    if (!error.response) {
        const kind =
            error.code === AxiosError.ECONNABORTED || error.code === AxiosError.ETIMEDOUT ? 'timeout' : 'network';
        return new ApiError({ kind, message: MESSAGES[kind] });
    }

    const { status, data, headers } = error.response;
    const body = (data ?? {}) as LaravelErrorBody;
    const kind = kindForStatus(status);
    const retryAfter = Number(headers?.['retry-after']) || undefined;
    const errors = body.errors ?? {};
    const firstFieldMessage = Object.values(errors)[0]?.[0];

    const message =
        kind === 'validation' || kind === 'rate_limited'
            ? (firstFieldMessage ?? body.message ?? MESSAGES[kind])
            : MESSAGES[kind];

    return new ApiError({ kind, status, message, errors, retryAfter });
}
