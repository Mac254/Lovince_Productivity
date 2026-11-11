import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createHttpClient } from '../client/createHttpClient';
import { createApi } from '../createApi';
import { ApiError } from '../errors/ApiError';

type Reply = { status: number; data?: unknown; headers?: Record<string, string> };
type Call = { url: string; path: string; params: Record<string, string>; init: RequestInit };

/** A scripted transport: each call takes the next reply and records the request. */
function scripted(...replies: (Reply | Error)[]) {
    const calls: Call[] = [];
    const fetch = (async (input: RequestInfo | URL, init: RequestInit = {}) => {
        const url = String(input);
        const parsed = new URL(url, 'http://app.test');
        calls.push({ url, path: parsed.pathname, params: Object.fromEntries(parsed.searchParams), init });
        if (init.signal?.aborted) throw init.signal.reason;
        const reply = replies.shift() ?? { status: 200, data: {} };
        if (reply instanceof Error) throw reply;
        const body = reply.status === 204 || reply.data === undefined ? null : JSON.stringify(reply.data);
        return new Response(body, { status: reply.status, headers: reply.headers });
    }) as typeof globalThis.fetch;
    return { fetch, calls };
}

describe('api client', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=abc%3D123';
    });

    it('sends JSON with the CSRF token to the versioned API and drops empty filters', async () => {
        const { fetch, calls } = scripted({ status: 200, data: { data: [], meta: { total: 0 } } });
        const api = createApi(createHttpClient({ fetch }));

        await api.tasks.list({ search: 'x', priority: undefined, status: 'open' });

        const [call] = calls;
        expect(call!.path).toBe('/api/v1/tasks');
        expect(call!.params).toEqual({ search: 'x', status: 'open' });
        expect(call!.init.credentials).toBe('same-origin');
        const headers = new Headers(call!.init.headers);
        expect(headers.get('X-XSRF-TOKEN')).toBe('abc=123');
        expect(headers.get('Accept')).toBe('application/json');
    });

    it('retries a read once after a transient server error', async () => {
        const { fetch, calls } = scripted({ status: 500 }, { status: 200, data: { data: [] } });
        const http = createHttpClient({ fetch });

        await expect(http.get('/reports')).resolves.toMatchObject({ status: 200 });
        expect(calls).toHaveLength(2);
    });

    it('does not retry writes or repeated server errors', async () => {
        const write = scripted({ status: 500 }, { status: 200 });
        await expect(createHttpClient({ fetch: write.fetch }).post('/reports')).rejects.toBeInstanceOf(ApiError);
        expect(write.calls).toHaveLength(1);

        const read = scripted({ status: 503 }, { status: 503 }, { status: 200 });
        await expect(createHttpClient({ fetch: read.fetch }).get('/reports')).rejects.toBeInstanceOf(ApiError);
        expect(read.calls).toHaveLength(2);
    });

    it('returns the user and session state from /auth/me', async () => {
        const session = {
            remembered: true,
            expires_at: '2026-11-27T10:00:00+00:00',
            idle_timeout_minutes: 120,
            remember_days: 30,
            timezone: 'Africa/Nairobi',
        };
        const { fetch, calls } = scripted({
            status: 200,
            data: {
                data: { id: 1, name: 'A', email: 'a@x.test', timezone: 'Africa/Nairobi', avatar_url: null },
                meta: { session },
            },
        });
        const api = createApi(createHttpClient({ fetch }));

        await expect(api.auth.me()).resolves.toEqual({
            user: { id: 1, name: 'A', email: 'a@x.test', timezone: 'Africa/Nairobi', avatar_url: null },
            session,
        });
        expect(calls[0]!.path).toBe('/api/v1/auth/me');
    });

    it('unwraps the data envelope', async () => {
        const { fetch } = scripted({ status: 201, data: { data: { id: 7, title: 'A' } } });
        const api = createApi(createHttpClient({ fetch }));

        await expect(api.tasks.create({ title: 'A' })).resolves.toEqual({ id: 7, title: 'A' });
    });

    it('turns validation failures into field errors', async () => {
        const { fetch } = scripted({
            status: 422,
            data: { message: 'Invalid', errors: { title: ['Give it a title.'] } },
        });
        const api = createApi(createHttpClient({ fetch }));

        const error = (await api.tasks.create({}).catch((e: unknown) => e)) as ApiError;

        expect(error).toBeInstanceOf(ApiError);
        expect(error.kind).toBe('validation');
        expect(error.message).toBe('Give it a title.');
        expect(error.fieldErrors()).toEqual({ title: 'Give it a title.' });
    });

    it('refreshes the CSRF cookie and retries once on 419', async () => {
        const { fetch, calls } = scripted({ status: 419 }, { status: 204 }, { status: 200, data: { data: { id: 1 } } });
        const api = createApi(createHttpClient({ fetch }));

        await expect(api.tasks.update(1, { status: 'completed' })).resolves.toEqual({ id: 1 });
        expect(calls.map((c) => c.path)).toEqual(['/api/v1/tasks/1', '/sanctum/csrf-cookie', '/api/v1/tasks/1']);
    });

    it('reports an ended session once through onUnauthorized', async () => {
        const onUnauthorized = vi.fn();
        const { fetch } = scripted({ status: 401, data: { message: 'Unauthenticated.' } });
        const api = createApi(createHttpClient({ fetch, onUnauthorized }));

        await expect(api.dashboard.get()).rejects.toMatchObject({ kind: 'unauthorized' });
        expect(onUnauthorized).toHaveBeenCalledOnce();
    });

    it('never surfaces raw server messages for server errors', async () => {
        const failure = { status: 500, data: { message: 'SQLSTATE[HY000] secret detail' } };
        const { fetch } = scripted(failure, failure);
        const api = createApi(createHttpClient({ fetch }));

        const error = (await api.goals.list().catch((e: unknown) => e)) as ApiError;

        expect(error.kind).toBe('server');
        expect(error.message).not.toContain('SQLSTATE');
    });

    it('distinguishes network failures and cancellations', async () => {
        const { fetch } = scripted(new TypeError('Failed to fetch'));
        const api = createApi(createHttpClient({ fetch }));
        await expect(api.quotes.random()).rejects.toMatchObject({ kind: 'network' });

        const controller = new AbortController();
        controller.abort();
        const cancelling = createApi(createHttpClient({ fetch: scripted().fetch }));
        await expect(cancelling.tasks.list({}, { signal: controller.signal })).rejects.toMatchObject({
            kind: 'cancelled',
        });
    });

    it('sends JSON bodies, form data untouched, and per-request headers', async () => {
        const { fetch, calls } = scripted({ status: 201, data: { data: {} } }, { status: 201, data: { data: {} } });
        const http = createHttpClient({ fetch });

        await http.post('/accounting/transactions', { amount: 5 }, { headers: { 'Idempotency-Key': 'k1' } });
        const form = new FormData();
        form.append('image', new Blob(['x']), 'a.png');
        await http.post('/tasks/1/cover', form);

        const json = new Headers(calls[0]!.init.headers);
        expect(calls[0]!.init.body).toBe('{"amount":5}');
        expect(json.get('Content-Type')).toBe('application/json');
        expect(json.get('Idempotency-Key')).toBe('k1');
        expect(calls[1]!.init.body).toBe(form);
        expect(new Headers(calls[1]!.init.headers).has('Content-Type')).toBe(false);
    });

    it('reads blobs, empty replies and Retry-After', async () => {
        const { fetch } = scripted(
            { status: 200, data: 'a,b' },
            { status: 204 },
            { status: 429, data: { message: 'Slow down.' }, headers: { 'Retry-After': '30' } },
        );
        const http = createHttpClient({ fetch });

        const blob = await http.get<Blob>('/backup', { responseType: 'blob' });
        expect(blob.data).toBeInstanceOf(Blob);
        await expect(http.delete('/task-notes/1')).resolves.toMatchObject({ status: 204, data: null });
        await expect(http.post('/auth/login')).rejects.toMatchObject({ kind: 'rate_limited', retryAfter: 30 });
    });

    it('reports a timeout separately from a cancellation', async () => {
        const slow = (async (_input: RequestInfo | URL, init: RequestInit = {}) =>
            new Promise<Response>((_resolve, reject) => {
                init.signal?.addEventListener('abort', () => reject(init.signal!.reason));
            })) as typeof globalThis.fetch;
        const http = createHttpClient({ fetch: slow, timeout: 10 });

        await expect(http.get('/reports')).rejects.toMatchObject({ kind: 'timeout' });
    });
});
