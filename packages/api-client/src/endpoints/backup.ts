import type { HttpClient } from '../client/createHttpClient';
import type { BackupImportResult, Resource } from '@focus/types';

export function backupEndpoints(http: HttpClient) {
    return {
        async export(): Promise<Blob> {
            const { data } = await http.get<Blob>('/backup', { responseType: 'blob', timeout: 60_000 });
            return data;
        },
        async import(file: File): Promise<BackupImportResult> {
            const form = new FormData();
            form.append('file', file);
            const { data } = await http.post<Resource<BackupImportResult>>('/backup', form, { timeout: 60_000 });
            return data.data;
        },
    };
}
