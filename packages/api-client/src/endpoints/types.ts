/** Options every endpoint accepts, so callers can cancel superseded requests. */
export interface RequestOptions {
    signal?: AbortSignal;
}
