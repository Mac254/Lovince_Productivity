const DB = 'focus-offline';
const STORE = 'files';

function request<T>(run: (store: IDBObjectStore) => IDBRequest<T>, mode: IDBTransactionMode): Promise<T> {
    return new Promise((resolve, reject) => {
        const opening = indexedDB.open(DB, 1);
        opening.onupgradeneeded = () => opening.result.createObjectStore(STORE);
        opening.onerror = () => reject(opening.error);
        opening.onsuccess = () => {
            const db = opening.result;
            const pending = run(db.transaction(STORE, mode).objectStore(STORE));
            pending.onsuccess = () => {
                db.close();
                resolve(pending.result);
            };
            pending.onerror = () => {
                db.close();
                reject(pending.error);
            };
        };
    });
}

/** Files picked while offline, kept in IndexedDB until the outbox uploads them. */
export async function saveFile(key: string, file: File): Promise<boolean> {
    try {
        await request((store) => store.put(file, key), 'readwrite');
        return true;
    } catch {
        return false;
    }
}

export async function loadFile(key: string): Promise<File | undefined> {
    return request<File | undefined>((store) => store.get(key), 'readonly').catch(() => undefined);
}

export async function dropFile(key: string): Promise<void> {
    await request((store) => store.delete(key), 'readwrite').catch(() => undefined);
}

export async function clearFiles(): Promise<void> {
    await request((store) => store.clear(), 'readwrite').catch(() => undefined);
}
