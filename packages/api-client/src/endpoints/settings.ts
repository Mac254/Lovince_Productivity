import type { AxiosInstance } from 'axios';
import type { Resource, SettingsChanges, SettingsModuleKey, SettingsState } from '@focus/types';

export function settingsEndpoints(http: AxiosInstance) {
    return {
        async get(): Promise<SettingsState> {
            const { data } = await http.get<Resource<SettingsState>>('/settings');
            return data.data;
        },
        /** Changes only the settings sent; the rest keep their values. */
        async update(changes: SettingsChanges): Promise<SettingsState> {
            const { data } = await http.patch<Resource<SettingsState>>('/settings', changes);
            return data.data;
        },
        /** Puts one module, or every setting, back to its default. */
        async reset(module?: SettingsModuleKey): Promise<SettingsState> {
            const { data } = await http.delete<Resource<SettingsState>>('/settings', {
                params: module ? { module } : undefined,
            });
            return data.data;
        },
    };
}
