import type { AxiosInstance } from 'axios';
import type { PasswordInput, Resource, User } from '@focus/types';

export function profileEndpoints(http: AxiosInstance) {
    return {
        async update(input: { name: string }): Promise<User> {
            const { data } = await http.patch<Resource<User>>('/profile', input);
            return data.data;
        },
        async changePassword(input: PasswordInput): Promise<void> {
            await http.put('/profile/password', input);
        },
        async uploadAvatar(file: File): Promise<User> {
            const form = new FormData();
            form.append('avatar', file);
            const { data } = await http.post<Resource<User>>('/profile/avatar', form);
            return data.data;
        },
        async removeAvatar(): Promise<User> {
            const { data } = await http.delete<Resource<User>>('/profile/avatar');
            return data.data;
        },
    };
}
