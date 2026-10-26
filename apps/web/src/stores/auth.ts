import { api, isApiError } from '@focus/api-client';
import type { AuthSession, LoginInput, Me, User } from '@focus/types';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const session = ref<AuthSession | null>(null);
    const resolved = ref(false);

    const isAuthenticated = computed(() => user.value !== null);

    function apply(me: Me): void {
        user.value = me.user;
        session.value = me.session;
        resolved.value = true;
    }

    /**
     * Learns whether a session exists. Only a definite answer (a user, or 401) is cached;
     * server or network failures are rethrown so the next navigation tries again.
     */
    async function resolve(): Promise<void> {
        if (resolved.value) return;
        try {
            apply(await api.auth.me());
        } catch (error) {
            if (!isApiError(error) || error.kind !== 'unauthorized') throw error;
            clear();
        }
    }

    /** Re-reads the session details (e.g. to show a renewed expiry). */
    async function refresh(): Promise<void> {
        apply(await api.auth.me());
    }

    async function login(input: LoginInput): Promise<void> {
        apply(await api.auth.login(input));
    }

    async function logout(): Promise<void> {
        try {
            await api.auth.logout();
        } finally {
            clear();
        }
    }

    function clear(): void {
        user.value = null;
        session.value = null;
        resolved.value = true;
    }

    return { user, session, isAuthenticated, resolve, refresh, login, logout, clear };
});
