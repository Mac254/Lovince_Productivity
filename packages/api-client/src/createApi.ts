import type { AxiosInstance } from 'axios';
import { accountingEndpoints } from './endpoints/accounting';
import { achievementEndpoints } from './endpoints/achievements';
import { bookEndpoints } from './endpoints/books';
import { roadmapEndpoints } from './endpoints/roadmap';
import { authEndpoints } from './endpoints/auth';
import { backupEndpoints } from './endpoints/backup';
import { blockerEndpoints } from './endpoints/blockers';
import { checklistEndpoints } from './endpoints/checklist';
import { dashboardEndpoints } from './endpoints/dashboard';
import { focusSessionEndpoints, focusTimerEndpoints } from './endpoints/focusSessions';
import { goalEndpoints } from './endpoints/goals';
import { journalEndpoints } from './endpoints/journal';
import { metricEndpoints } from './endpoints/metrics';
import { profileEndpoints } from './endpoints/profile';
import { quoteEndpoints } from './endpoints/quotes';
import { reportEndpoints } from './endpoints/reports';
import { reviewEndpoints } from './endpoints/reviews';
import { searchEndpoints } from './endpoints/search';
import { settingsEndpoints } from './endpoints/settings';
import { wallpaperEndpoints } from './endpoints/wallpapers';
import { taskEndpoints } from './endpoints/tasks';
import { taskNoteEndpoints } from './endpoints/taskNotes';
import { taskTypeEndpoints } from './endpoints/taskTypes';
import { scheduleEndpoints } from './endpoints/schedules';
import { suggestionEndpoints } from './endpoints/suggestions';
import { timeBlockEndpoints } from './endpoints/timeBlocks';

export function createApi(http: AxiosInstance) {
    return {
        auth: authEndpoints(http),
        dashboard: dashboardEndpoints(http),
        tasks: taskEndpoints(http),
        goals: goalEndpoints(http),
        quotes: quoteEndpoints(http),
        blockers: blockerEndpoints(http),
        achievements: achievementEndpoints(http),
        books: bookEndpoints(http),
        roadmap: roadmapEndpoints(http),
        journal: journalEndpoints(http),
        reports: reportEndpoints(http),
        reviews: reviewEndpoints(http),
        metrics: metricEndpoints(http),
        search: searchEndpoints(http),
        backup: backupEndpoints(http),
        profile: profileEndpoints(http),
        settings: settingsEndpoints(http),
        wallpapers: wallpaperEndpoints(http),
        focusSessions: focusSessionEndpoints(http),
        focusTimer: focusTimerEndpoints(http),
        taskTypes: taskTypeEndpoints(http),
        taskNotes: taskNoteEndpoints(http),
        checklist: checklistEndpoints(http),
        timeBlocks: timeBlockEndpoints(http),
        schedules: scheduleEndpoints(http),
        suggestions: suggestionEndpoints(http),
        accounting: accountingEndpoints(http),
    };
}

export type FocusApi = ReturnType<typeof createApi>;
