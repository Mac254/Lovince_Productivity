export * from './generated/contract';

export type { AuthSession, Me, User } from './models/user';
export type { GoalRef, IsoDate, IsoDateTime, Task } from './models/task';
export type { Goal } from './models/goal';
export type { Quote } from './models/quote';
export type { Blocker } from './models/blocker';
export type { JournalContext, JournalEntry } from './models/journal';
export type {
    FocusDay,
    FocusHistory,
    FocusSession,
    FocusSessionInput,
    FocusTimerState,
    FocusTimerSync,
} from './models/focusSession';
export type { Wallpaper } from './models/wallpaper';
export type { TimeBlock, TimeBlockInput } from './models/timeBlock';
export type { TaskType, TaskTypeRef } from './models/taskType';
export type { TaskNote } from './models/taskNote';
export type { ChecklistItem, ChecklistItemInput } from './models/checklist';
export type { SettingDescription, SettingsChanges, SettingsModuleDescription, SettingsState } from './models/settings';

export type { Paginated, PaginationMeta, Resource, ValidationErrors } from './api/envelope';
export type {
    BlockerInput,
    GoalInput,
    LoginInput,
    PasswordInput,
    TaskInput,
    TaskNoteInput,
    TaskTypeInput,
} from './api/inputs';
export type { BlockerFilters, GoalFilters, TaskFilters } from './api/filters';
export type { Dashboard, DashboardStats, FocusToday, IntegrationSummary } from './api/dashboard';
export type {
    DailyReport,
    DailyReportSummary,
    ReportBlocker,
    ReportGoal,
    ReportSummary,
    ReportTask,
    ReportWorkLogEntry,
    TypeBreakdownRow,
} from './api/reports';
export type { FocusMetrics, MetricRange, Metrics } from './api/metrics';
export type { SearchKind, SearchResult } from './api/search';
export type {
    ReviewPrompt,
    WeeklyReflection,
    WeeklyReview,
    WeeklyReviewDay,
    WeeklyReviewGoal,
    WeeklyReviewInput,
    WeeklyReviewSummary,
} from './api/reviews';
