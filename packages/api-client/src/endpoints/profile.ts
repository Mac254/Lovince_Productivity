import type { HttpClient } from '../client/createHttpClient';
import type { Notes, PasswordInput, Resource, User } from '@focus/types';

export function profileEndpoints(http: HttpClient) {
    return {
        async update(input: { name?: string; timezone?: string }): Promise<User> {
            const { data } = await http.patch<Resource<User>>('/profile', input);
            return data.data;
        },
        async notes(): Promise<Notes> {
            const { data } = await http.get<Resource<Notes>>('/profile/notes');
            return data.data;
        },
        async saveNotes(notes: string): Promise<Notes> {
            const { data } = await http.put<Resource<Notes>>('/profile/notes', { notes });
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
