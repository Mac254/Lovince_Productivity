import type {
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
    highlighted?: boolean;
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
