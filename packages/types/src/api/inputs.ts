import type { Blocker } from '../models/blocker';
import type { Goal } from '../models/goal';
import type { Task } from '../models/task';

export type TaskInput = Partial<
    Pick<
        Task,
        | 'title'
        | 'description'
        | 'status'
        | 'priority'
        | 'category'
        | 'is_highlighted'
        | 'start_date'
        | 'due_date'
        | 'goal_id'
    >
>;

export type GoalInput = Partial<
    Pick<Goal, 'title' | 'description' | 'status' | 'timeframe' | 'tone' | 'start_date' | 'target_date'>
>;

export type BlockerInput = Partial<Pick<Blocker, 'description' | 'status' | 'task_id'>>;

export interface PasswordInput {
    current_password: string;
    password: string;
    password_confirmation: string;
}

export interface LoginInput {
    email: string;
    password: string;
    remember: boolean;
}
