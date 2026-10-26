import { TASK_CATEGORIES, type Task, type TaskCategory, type TaskType } from '@focus/types';
import { taskCategoryLabels } from './labels';

/**
 * A task's "type" in the UI is either a built-in category (which drives development and
 * integration tracking) or one of the user's own types. One picker value encodes both.
 */
export type TaskKind = `category:${TaskCategory}` | `type:${number}`;

export function kindOf(task: Pick<Task, 'category' | 'task_type_id'>): TaskKind {
    return task.task_type_id ? `type:${task.task_type_id}` : `category:${task.category}`;
}

export function fromKind(kind: TaskKind): { category: TaskCategory; task_type_id: number | null } {
    if (kind.startsWith('type:')) return { category: 'general', task_type_id: Number(kind.slice(5)) };
    return { category: kind.slice(9) as TaskCategory, task_type_id: null };
}

export function kindOptions(types: TaskType[]): { value: TaskKind; label: string }[] {
    return [
        ...TASK_CATEGORIES.map((c) => ({ value: `category:${c}` as TaskKind, label: taskCategoryLabels[c] })),
        ...types.map((t) => ({ value: `type:${t.id}` as TaskKind, label: t.name })),
    ];
}
