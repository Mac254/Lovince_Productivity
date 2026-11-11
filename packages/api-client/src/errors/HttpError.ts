export interface HttpResponse<T> {
    data: T;
    status: number;
    headers: Headers;
}

/** A response outside 2xx, before it is normalised into an `ApiError`. */
export class HttpError extends Error {
    constructor(readonly response: HttpResponse<unknown>) {
        super(`Request failed with status ${response.status}`);
        this.name = 'HttpError';
    }
}
