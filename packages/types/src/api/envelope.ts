/** Every API payload is wrapped in `data`. */
export interface Resource<T> {
    data: T;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface Paginated<T> {
    data: T[];
    meta: PaginationMeta;
}

/** Laravel validation errors keyed by field. */
export type ValidationErrors = Record<string, string[]>;
