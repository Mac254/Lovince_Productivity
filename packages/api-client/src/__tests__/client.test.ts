import {
    AxiosError,
    AxiosHeaders,
    type AxiosAdapter,
    type AxiosResponse,
    type InternalAxiosRequestConfig,
} from 'axios';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createHttpClient } from '../client/createHttpClient';
import { createApi } from '../createApi';
import { ApiError } from '../errors/ApiError';

type Reply = { status: number; data?: unknown; headers?: Record<string, string> };

/** A scripted transport: each call takes the next reply and records the request. */
function scripted(...replies: (Reply | Error)[]) {
    const calls: InternalAxiosRequestConfig[] = [];
    const adapter: AxiosAdapter = async (config) => {
        calls.push(config);
        const reply = replies.shift() ?? { status: 200, data: {} };
        if (reply instanceof Error) throw reply;
        const response: AxiosResponse = {
            data: reply.data ?? null,
            status: reply.status,
            statusText: '',
            headers: reply.headers ?? {},
            config,
        };
        if (reply.status >= 400) {
            throw new AxiosError('failed', String(reply.status), config, null, response);
        }
        return response;
    };
    return { adapter, calls };
}

describe('api client', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=abc%3D123';
    });

    it('sends JSON with the CSRF token to the versioned API and drops empty filters', async () => {
        const { adapter, calls } = scripted({ status: 200, data: { data: [], meta: { total: 0 } } });
        const api = createApi(createHttpClient({ adapter }));

        await api.tasks.list({ search: 'x', priority: undefined, status: 'open' });

        const [config] = calls;
        expect(config!.baseURL).toBe('/api/v1');
        expect(config!.url).toBe('/tasks');
        expect(config!.params).toEqual({ search: 'x', status: 'open' });
        expect(config!.withCredentials).toBe(true);
        expect(AxiosHeaders.from(config!.headers).get('X-XSRF-TOKEN')).toBe('abc=123');
    });

    it('retries a read once after a transient server error', async () => {
        const { adapter, calls } = scripted({ status: 500 }, { status: 200, data: { data: [] } });
        const http = createHttpClient({ adapter });

        await expect(http.get('/reports')).resolves.toMatchObject({ status: 200 });
        expect(calls).toHaveLength(2);
    });

    it('does not retry writes or repeated server errors', async () => {
        const write = scripted({ status: 500 }, { status: 200 });
        await expect(createHttpClient({ adapter: write.adapter }).post('/reports')).rejects.toBeInstanceOf(ApiError);
        expect(write.calls).toHaveLength(1);

        const read = scripted({ status: 503 }, { status: 503 }, { status: 200 });
        await expect(createHttpClient({ adapter: read.adapter }).get('/reports')).rejects.toBeInstanceOf(ApiError);
        expect(read.calls).toHaveLength(2);
    });

    it('returns the user and session state from /auth/me', async () => {
        const session = {
            remembered: true,
            expires_at: '2026-11-27T10:00:00+00:00',
            idle_timeout_minutes: 120,
            remember_days: 30,
        };
        const { adapter, calls } = scripted({
            status: 200,
            data: { data: { id: 1, name: 'A', email: 'a@x.test', avatar_url: null }, meta: { session } },
        });
        const api = createApi(createHttpClient({ adapter }));

        await expect(api.auth.me()).resolves.toEqual({
            user: { id: 1, name: 'A', email: 'a@x.test', avatar_url: null },
            session,
        });
        expect(calls[0]!.url).toBe('/auth/me');
    });

    it('unwraps the data envelope', async () => {
        const { adapter } = scripted({ status: 201, data: { data: { id: 7, title: 'A' } } });
        const api = createApi(createHttpClient({ adapter }));

        await expect(api.tasks.create({ title: 'A' })).resolves.toEqual({ id: 7, title: 'A' });
    });

    it('turns validation failures into field errors', async () => {
        const { adapter } = scripted({
            status: 422,
            data: { message: 'Invalid', errors: { title: ['Give it a title.'] } },
        });
        const api = createApi(createHttpClient({ adapter }));

        const error = (await api.tasks.create({}).catch((e: unknown) => e)) as ApiError;

        expect(error).toBeInstanceOf(ApiError);
        expect(error.kind).toBe('validation');
        expect(error.message).toBe('Give it a title.');
        expect(error.fieldErrors()).toEqual({ title: 'Give it a title.' });
    });

    it('refreshes the CSRF cookie and retries once on 419', async () => {
        const { adapter, calls } = scripted(
            { status: 419 },
            { status: 204 },
            { status: 200, data: { data: { id: 1 } } },
        );
        const api = createApi(createHttpClient({ adapter }));

        await expect(api.tasks.update(1, { status: 'completed' })).resolves.toEqual({ id: 1 });
        expect(calls.map((c) => c.url)).toEqual(['/tasks/1', '/sanctum/csrf-cookie', '/tasks/1']);
    });

    it('reports an ended session once through onUnauthorized', async () => {
        const onUnauthorized = vi.fn();
        const { adapter } = scripted({ status: 401, data: { message: 'Unauthenticated.' } });
        const api = createApi(createHttpClient({ adapter, onUnauthorized }));

        await expect(api.dashboard.get()).rejects.toMatchObject({ kind: 'unauthorized' });
        expect(onUnauthorized).toHaveBeenCalledOnce();
    });

    it('never surfaces raw server messages for server errors', async () => {
        const failure = { status: 500, data: { message: 'SQLSTATE[HY000] secret detail' } };
        const { adapter } = scripted(failure, failure);
        const api = createApi(createHttpClient({ adapter }));

        const error = (await api.goals.list().catch((e: unknown) => e)) as ApiError;

        expect(error.kind).toBe('server');
        expect(error.message).not.toContain('SQLSTATE');
    });

    it('distinguishes network failures and cancellations', async () => {
        const network = new AxiosError('Network Error', AxiosError.ERR_NETWORK);
        const { adapter } = scripted(network);
        const api = createApi(createHttpClient({ adapter }));
        await expect(api.quotes.random()).rejects.toMatchObject({ kind: 'network' });

        const controller = new AbortController();
        controller.abort();
        const cancelling = createApi(createHttpClient({ adapter: scripted().adapter }));
        await expect(cancelling.tasks.list({}, { signal: controller.signal })).rejects.toMatchObject({
            kind: 'cancelled',
        });
    });
});
