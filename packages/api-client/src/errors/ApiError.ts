import type { ValidationErrors } from '@focus/types';

export type ApiErrorKind =
    | 'network'
    | 'timeout'
    | 'cancelled'
    | 'validation'
    | 'unauthorized'
    | 'forbidden'
    | 'not_found'
    | 'csrf'
    | 'rate_limited'
    | 'server'
    | 'unknown';

interface ApiErrorInit {
    kind: ApiErrorKind;
    message: string;
    status?: number;
    errors?: ValidationErrors;
    retryAfter?: number;
}

/** Every failed request becomes one of these, so UI code never inspects raw responses. */
export class ApiError extends Error {
    readonly kind: ApiErrorKind;
    readonly status: number;
    readonly errors: ValidationErrors;
    readonly retryAfter?: number;

    constructor({ kind, message, status = 0, errors = {}, retryAfter }: ApiErrorInit) {
        super(message);
        this.name = 'ApiError';
        this.kind = kind;
        this.status = status;
        this.errors = errors;
        this.retryAfter = retryAfter;
    }

    get isValidation(): boolean {
        return this.kind === 'validation';
    }

    get isCancelled(): boolean {
        return this.kind === 'cancelled';
    }

    /** First message per field, ready to bind to form fields. */
    fieldErrors(): Record<string, string> {
        return Object.fromEntries(Object.entries(this.errors).map(([field, messages]) => [field, messages[0] ?? '']));
    }
}

export function isApiError(error: unknown): error is ApiError {
    return error instanceof ApiError;
}

/** A message that is always safe to show in a toast or error state. */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
    return isApiError(error) ? error.message : fallback;
}
