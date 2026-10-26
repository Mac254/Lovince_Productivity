import type { AxiosInstance } from 'axios';
import type { Resource, Wallpaper } from '@focus/types';
import type { RequestOptions } from './types';

export function wallpaperEndpoints(http: AxiosInstance) {
    return {
        async list(options: RequestOptions = {}): Promise<Wallpaper[]> {
            const { data } = await http.get<Resource<Wallpaper[]>>('/wallpapers', options);
            return data.data;
        },
        async upload(file: File): Promise<Wallpaper> {
            const form = new FormData();
            form.append('image', file);
            const { data } = await http.post<Resource<Wallpaper>>('/wallpapers', form, { timeout: 60_000 });
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/wallpapers/${id}`);
        },
    };
}
