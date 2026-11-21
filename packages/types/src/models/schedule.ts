export interface ScheduleItem {
    id?: number;
    title: string;
    /** What to do instead on a late day, e.g. a 10-minute walk. */
    fallback?: string | null;
    task_type_id: number | null;
    goal_id: number | null;
    start_minute: number;
    minutes: number;
}

export interface Schedule {
    id: number;
    name: string;
    days: number[];
    items: ScheduleItem[];
    updated_at: string | null;
}

export interface ScheduleInput {
    name?: string;
    days?: number[];
    items?: ScheduleItem[];
}
