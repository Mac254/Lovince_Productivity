import type { IsoDateTime } from './task';

export interface Wallpaper {
    id: number;
    name: string;
    /** Same-origin, owner-only image URL. */
    url: string;
    created_at: IsoDateTime;
}
