import type { ValidationErrors } from '@focus/types';
import { HttpError } from './HttpError';
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
    conflict: 'This was changed somewhere else in the meantime.',
    csrf: 'Your session expired. Please try again.',
    rate_limited: 'Too many attempts. Please wait a moment and try again.',
    server: 'Something went wrong on the server. Please try again.',
    unknown: 'The request could not be completed.',
};

function kindForStatus(status: number): ApiErrorKind {
    if (status === 401) return 'unauthorized';
    if (status === 403) return 'forbidden';
    if (status === 404) return 'not_found';
    if (status === 409) return 'conflict';
    if (status === 419) return 'csrf';
    if (status === 422) return 'validation';
    if (status === 429) return 'rate_limited';
    if (status >= 500) return 'server';
    return 'unknown';
}

/** Server messages are only surfaced where Laravel writes them for people (validation, throttling). */
export function normalizeError(error: unknown): ApiError {
    if (error instanceof ApiError) return error;
    const name = error instanceof Error || error instanceof DOMException ? error.name : '';
    if (name === 'TimeoutError') {
        return new ApiError({ kind: 'timeout', message: MESSAGES.timeout });
    }
    if (name === 'AbortError') {
        return new ApiError({ kind: 'cancelled', message: MESSAGES.cancelled });
    }
    if (error instanceof TypeError) return new ApiError({ kind: 'network', message: MESSAGES.network });
    if (!(error instanceof HttpError)) return new ApiError({ kind: 'unknown', message: MESSAGES.unknown });

    const { status, data, headers } = error.response;
    const body = (data ?? {}) as LaravelErrorBody;
    const kind = kindForStatus(status);
    const retryAfter = Number(headers.get('retry-after')) || undefined;
    const errors = body.errors ?? {};
    const firstFieldMessage = Object.values(errors)[0]?.[0];

    const message =
        kind === 'validation' || kind === 'rate_limited'
            ? (firstFieldMessage ?? body.message ?? MESSAGES[kind])
            : MESSAGES[kind];

    return new ApiError({ kind, status, message, errors, retryAfter });
}
