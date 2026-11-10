export * from './generated/contract';

export type { AuthSession, Me, User } from './models/user';
export type { GoalRef, IsoDate, IsoDateTime, Task, TaskCover } from './models/task';
export type { Goal } from './models/goal';
export type { Quote } from './models/quote';
export type { Blocker } from './models/blocker';
export type { Achievement } from './models/achievement';
export type { Book, BookReading, BookTask } from './models/book';
export type { RoadmapItem, RoadmapItemInput, RoadmapMilestone, RoadmapMoneyGoal } from './models/roadmap';
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
export type { Schedule, ScheduleInput, ScheduleItem } from './models/schedule';
export type { Suggestion, SuggestionAction, SuggestionInput } from './models/suggestion';
export type { TaskType, TaskTypeRef } from './models/taskType';
export type { TaskNote } from './models/taskNote';
export type { ChecklistItem, ChecklistItemInput } from './models/checklist';
export type { SettingDescription, SettingsChanges, SettingsModuleDescription, SettingsState } from './models/settings';

export type {
    AccountingCategory,
    Budget,
    FinancialAccount,
    FinancialAccountRef,
    FinancialGoal,
    GoalContribution,
    MinorUnits,
    MoneyRule,
    MoneyRuleSuggestion,
    RecurringTransaction,
    Transaction,
} from './models/accounting';

export type { Paginated, PaginationMeta, Resource, ValidationErrors } from './api/envelope';
export type {
    AchievementInput,
    BookInput,
    BookReadingInput,
    BookTaskInput,
    BlockerInput,
    GoalInput,
    LoginInput,
    PasswordInput,
    TaskInput,
    TaskUpdateInput,
    TaskNoteInput,
    TaskTypeInput,
} from './api/inputs';
export type { AchievementFilters, BlockerFilters, BookFilters, GoalFilters, TaskFilters } from './api/filters';
export type { Dashboard, DashboardStats, FocusToday, IntegrationSummary } from './api/dashboard';
export type {
    DailyReport,
    DailyReportSummary,
    ReportAchievement,
    ReportBlocker,
    ReportReading,
    ReportGoal,
    ReportSuggestion,
    ReportSummary,
    ReportTask,
    ReportWorkLogEntry,
    TypeBreakdownRow,
} from './api/reports';
export type {
    AchievementMetrics,
    ActivityStreak,
    FocusMetrics,
    MetricInsight,
    MetricRange,
    Metrics,
    ReadingMetrics,
    SuggestionMetrics,
} from './api/metrics';
export type { SearchKind, SearchResult } from './api/search';
export type { BackupImportResult } from './api/backup';
export type {
    ReviewPrompt,
    WeeklyReflection,
    WeeklyReview,
    WeeklyReviewDay,
    WeeklyReviewGoal,
    WeeklyReviewInput,
    WeeklyReviewSummary,
} from './api/reviews';
export type {
    AccountInput,
    AccountingDashboard,
    AccountingInsight,
    AccountingPeriod,
    BudgetInput,
    BudgetMonth,
    BudgetSummary,
    CashFlow,
    CashFlowAccount,
    CategoryAmount,
    CategoryInput,
    FinancialGoalInput,
    GoalContributionInput,
    GoalSummary,
    IncomeStatement,
    MoneyRuleInput,
    MonthTotals,
    MoneyTrend,
    RecurringTransactionInput,
    TransactionFilters,
    TransactionInput,
} from './api/accounting';
