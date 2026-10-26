import type { AxiosInstance } from 'axios';
import { authEndpoints } from './endpoints/auth';
import { blockerEndpoints } from './endpoints/blockers';
import { dashboardEndpoints } from './endpoints/dashboard';
import { goalEndpoints } from './endpoints/goals';
import { journalEndpoints } from './endpoints/journal';
import { metricEndpoints } from './endpoints/metrics';
import { profileEndpoints } from './endpoints/profile';
import { quoteEndpoints } from './endpoints/quotes';
import { reportEndpoints } from './endpoints/reports';
import { taskEndpoints } from './endpoints/tasks';

export function createApi(http: AxiosInstance) {
    return {
        auth: authEndpoints(http),
        dashboard: dashboardEndpoints(http),
        tasks: taskEndpoints(http),
        goals: goalEndpoints(http),
        quotes: quoteEndpoints(http),
        blockers: blockerEndpoints(http),
        journal: journalEndpoints(http),
        reports: reportEndpoints(http),
        metrics: metricEndpoints(http),
        profile: profileEndpoints(http),
    };
}

export type FocusApi = ReturnType<typeof createApi>;
