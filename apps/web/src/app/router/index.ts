import { isApiError } from '@focus/api-client';
import { createRouter, createWebHistory } from 'vue-router';
import UnavailablePage from '@/pages/UnavailablePage.vue';
import { clearReloadGuard, isChunkLoadError, reloadOnce } from '@/lib/chunkReload';
import { useAuthStore } from '@/stores/auth';
import { useTimerStore } from '@/stores/timer';

function errorKind(error: unknown): string {
    if (isChunkLoadError(error)) return 'update';
    return isApiError(error) ? error.kind : 'unknown';
}

declare module 'vue-router' {
    interface RouteMeta {
        guest?: boolean;
        title?: string;
    }
}

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: () => import('@/pages/LoginPage.vue'),
            meta: { guest: true, title: 'Sign in' },
        },
        {
            path: '/',
            component: () => import('@/layouts/AppLayout.vue'),
            children: [
                {
                    path: '',
                    name: 'dashboard',
                    component: () => import('@/pages/DashboardPage.vue'),
                    meta: { title: 'Dashboard' },
                },
                {
                    path: 'today',
                    name: 'today',
                    component: () => import('@/pages/TodayPage.vue'),
                    meta: { title: 'Today' },
                },
                {
                    path: 'tasks',
                    name: 'tasks',
                    component: () => import('@/pages/TasksPage.vue'),
                    meta: { title: 'Tasks' },
                },
                {
                    path: 'goals',
                    name: 'goals',
                    component: () => import('@/pages/GoalsPage.vue'),
                    meta: { title: 'Goals' },
                },
                {
                    path: 'tasks/:id(\\d+)',
                    name: 'task-detail',
                    component: () => import('@/pages/TaskDetailPage.vue'),
                    meta: { title: 'Task' },
                },
                {
                    path: 'goals/:id(\\d+)',
                    name: 'goal-detail',
                    component: () => import('@/pages/GoalDetailPage.vue'),
                    meta: { title: 'Goal' },
                },
                {
                    path: 'types',
                    name: 'types',
                    component: () => import('@/pages/TaskTypesPage.vue'),
                    meta: { title: 'Types' },
                },
                {
                    path: 'blockers',
                    name: 'blockers',
                    component: () => import('@/pages/BlockersPage.vue'),
                    meta: { title: 'Blockers' },
                },
                {
                    path: 'metrics',
                    name: 'metrics',
                    component: () => import('@/pages/MetricsPage.vue'),
                    meta: { title: 'Metrics' },
                },
                {
                    path: 'reports',
                    name: 'reports',
                    component: () => import('@/pages/ReportsPage.vue'),
                    meta: { title: 'Reports' },
                },
                {
                    path: 'reports/:date(\\d{4}-\\d{2}-\\d{2})',
                    name: 'report-detail',
                    component: () => import('@/pages/ReportDetailPage.vue'),
                    meta: { title: 'Report' },
                },
                {
                    path: 'journal',
                    name: 'journal',
                    component: () => import('@/pages/JournalPage.vue'),
                    meta: { title: 'Journal' },
                },
                {
                    path: 'account',
                    name: 'account',
                    component: () => import('@/pages/AccountPage.vue'),
                    meta: { title: 'Account' },
                },
            ],
        },
        {
            path: '/focus',
            name: 'focus',
            component: () => import('@/pages/FocusClockPage.vue'),
            meta: { title: 'Focus clock' },
        },
        {
            path: '/unavailable',
            name: 'unavailable',
            // Bundled eagerly: the fallback must load even when lazy chunks cannot.
            component: UnavailablePage,
            meta: { title: 'Can’t reach Focus' },
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('@/pages/NotFoundPage.vue'),
            meta: { title: 'Not found' },
        },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

const PUBLIC_ROUTES = new Set(['not-found', 'unavailable']);

router.beforeEach(async (to) => {
    if (to.name === 'unavailable') return true;

    const auth = useAuthStore();
    try {
        await auth.resolve();
    } catch (error) {
        // The API answered with an error or not at all: explain it instead of a blank page.
        return { name: 'unavailable', query: { reason: errorKind(error), redirect: to.fullPath } };
    }

    if (to.meta.guest) return auth.isAuthenticated ? { name: 'dashboard' } : true;
    if (!PUBLIC_ROUTES.has(String(to.name)) && !auth.isAuthenticated) {
        return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }
    return true;
});

// Failures outside the guard (e.g. a lazy page chunk that fails to load) land here.
// Never redirect to the page that just failed, or a broken chunk would loop forever.
router.onError((error, to) => {
    if (import.meta.env.DEV) console.error('[router]', error);
    if (to.name === 'unavailable') return;
    // Stale code after a deploy or dev-server edit: a fresh page load fixes it, once.
    if (isChunkLoadError(error) && reloadOnce(to.fullPath)) return;
    void router.replace({ name: 'unavailable', query: { reason: errorKind(error), redirect: to.fullPath } });
});

router.afterEach((to) => {
    if (to.name !== 'unavailable') clearReloadGuard();
    const title = to.meta.title ? `${to.meta.title} · Focus` : 'Focus';
    // The timer owns the tab title while it runs; it falls back to this one.
    useTimerStore().baseTitle = title;
    document.title = title;
});
