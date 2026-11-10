import { isApiError } from '@focus/api-client';
import { createRouter, createWebHistory, START_LOCATION } from 'vue-router';
import UnavailablePage from '@/pages/UnavailablePage.vue';
import { clearReloadGuard, isChunkLoadError, reloadOnce } from '@/lib/chunkReload';

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
                    path: 'schedules',
                    name: 'schedules',
                    component: () => import('@/pages/SchedulesPage.vue'),
                    meta: { title: 'Schedules' },
                },
                {
                    path: 'suggestions',
                    name: 'suggestions',
                    component: () => import('@/pages/SuggestionsPage.vue'),
                    meta: { title: 'Suggestions' },
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
                    path: 'achievements',
                    name: 'achievements',
                    component: () => import('@/pages/AchievementsPage.vue'),
                    meta: { title: 'Achievements' },
                },
                {
                    path: 'books',
                    name: 'books',
                    component: () => import('@/pages/BooksPage.vue'),
                    meta: { title: 'Books' },
                },
                {
                    path: 'books/:id(\\d+)',
                    name: 'book-detail',
                    component: () => import('@/pages/BookDetailPage.vue'),
                    meta: { title: 'Book' },
                },
                {
                    path: 'roadmap',
                    name: 'roadmap',
                    component: () => import('@/pages/RoadmapPage.vue'),
                    meta: { title: 'Money roadmap' },
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
                    path: 'review/:week(\\d{4}-\\d{2}-\\d{2})?',
                    name: 'review',
                    component: () => import('@/pages/WeeklyReviewPage.vue'),
                    meta: { title: 'Weekly review' },
                },
                {
                    path: 'money',
                    name: 'accounting',
                    component: () => import('@/pages/MoneyOverviewPage.vue'),
                    meta: { title: 'Money' },
                },
                {
                    path: 'money/transactions',
                    name: 'transactions',
                    component: () => import('@/pages/MoneyTransactionsPage.vue'),
                    meta: { title: 'Transactions' },
                },
                {
                    path: 'money/accounts/:id(\\d+)',
                    name: 'financial-account-detail',
                    component: () => import('@/pages/MoneyAccountDetailPage.vue'),
                    meta: { title: 'Account' },
                },
                {
                    path: 'money/accounts',
                    name: 'financial-accounts',
                    component: () => import('@/pages/MoneyAccountsPage.vue'),
                    meta: { title: 'Accounts' },
                },
                {
                    path: 'money/budgets',
                    name: 'budgets',
                    component: () => import('@/pages/MoneyBudgetsPage.vue'),
                    meta: { title: 'Budgets' },
                },
                {
                    path: 'money/goals',
                    name: 'money-goals',
                    component: () => import('@/pages/MoneyGoalsPage.vue'),
                    meta: { title: 'Goals' },
                },
                {
                    path: 'money/rules',
                    name: 'money-rules',
                    component: () => import('@/pages/MoneyRulesPage.vue'),
                    meta: { title: 'Rules' },
                },
                {
                    path: 'money/recurring',
                    name: 'recurring',
                    component: () => import('@/pages/MoneyRecurringPage.vue'),
                    meta: { title: 'Recurring' },
                },
                {
                    path: 'money/reports',
                    name: 'financial-reports',
                    component: () => import('@/pages/MoneyReportsPage.vue'),
                    meta: { title: 'Statements' },
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
                    meta: { title: 'Settings' },
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
            meta: { title: 'Can’t reach LovinceTracker' },
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('@/pages/NotFoundPage.vue'),
            meta: { title: 'Not found' },
        },
    ],
    scrollBehavior: (to, from, saved) => saved ?? (to.path === from.path ? false : { top: 0 }),
});

const PUBLIC_ROUTES = new Set(['not-found', 'unavailable']);

router.beforeEach(async (to, from) => {
    if (to.name === 'unavailable') return true;

    const auth = useAuthStore();
    try {
        await auth.resolve();
    } catch (error) {
        // The API answered with an error or not at all: explain it instead of a blank page.
        return { name: 'unavailable', query: { reason: errorKind(error), redirect: to.fullPath } };
    }

    const startPage = usePreferencesStore().prefs.startPage;
    if (to.meta.guest) return auth.isAuthenticated ? { name: startPage } : true;
    if (!PUBLIC_ROUTES.has(String(to.name)) && !auth.isAuthenticated) {
        return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }
    // Opening LovinceTracker (or signing in) lands on the chosen start page; the Dashboard link still works.
    if (to.name === 'dashboard' && (from === START_LOCATION || from.name === 'login') && startPage !== 'dashboard') {
        return { name: startPage };
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
    const title = to.meta.title ? `${to.meta.title} · LovinceTracker` : 'LovinceTracker';
    // The timer owns the tab title while it runs; it falls back to this one.
    useTimerStore().baseTitle = title;
    document.title = title;
});
