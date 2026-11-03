import type {
    AchievementCategory,
    BlockerStatus,
    GoalStatus,
    GoalTimeframe,
    TaskCategory,
    TaskDueFilter,
    TaskPriority,
    TaskSort,
    TaskStatus,
} from '../generated/contract';

export interface TaskFilters {
    search?: string;
    status?: TaskStatus | 'open';
    priority?: TaskPriority;
    category?: TaskCategory;
    due?: TaskDueFilter;
    goal_id?: number;
    task_type_id?: number;
    highlighted?: boolean;
    hide_planned_ahead?: boolean;
    sort?: TaskSort;
    direction?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
}

export interface BlockerFilters {
    status?: BlockerStatus;
    task_id?: number;
}

export interface GoalFilters {
    status?: GoalStatus;
    timeframe?: GoalTimeframe;
}

export interface AchievementFilters {
    goal_id?: number;
    task_id?: number;
    category?: AchievementCategory;
    year?: number;
}
