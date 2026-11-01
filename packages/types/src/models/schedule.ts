export interface ScheduleItem {
    id?: number;
    title: string;
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
