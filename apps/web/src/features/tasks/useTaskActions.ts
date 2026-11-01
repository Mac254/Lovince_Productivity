import { api, errorMessage } from '@focus/api-client';
import type { Task, TaskStatus } from '@focus/types';

export function useTaskActions(onChange: () => void) {
    const toast = useToast();

    async function setStatus(task: Task, status: TaskStatus): Promise<void> {
        try {
            const saved = await api.tasks.update(task.id, { status });
            const repeats = status === 'completed' && saved.next_occurrence_id && !task.next_occurrence_id;
            toast.success(
                status === 'completed'
                    ? `Completed “${task.title}”${repeats ? '. The next one is scheduled.' : ''}`
                    : status === 'cancelled'
                      ? `Cancelled “${task.title}”`
                      : `Reopened “${task.title}”`,
            );
            onChange();
        } catch (e) {
            toast.error(errorMessage(e, 'Could not update the task.'));
        }
    }

    async function remove(task: Task): Promise<void> {
        try {
            await api.tasks.remove(task.id);
            toast.success('Task deleted');
            onChange();
        } catch (e) {
            toast.error(errorMessage(e, 'Could not delete the task.'));
        }
    }

    async function toggleHighlight(task: Task): Promise<void> {
        try {
            await api.tasks.update(task.id, { is_highlighted: !task.is_highlighted });
            toast.success(task.is_highlighted ? 'Removed from highlights' : 'Added to highlights');
            onChange();
        } catch (e) {
            toast.error(errorMessage(e, 'Could not update the task.'));
        }
    }

    const toggleComplete = (task: Task) => setStatus(task, task.status === 'completed' ? 'not_started' : 'completed');

    return { setStatus, toggleComplete, toggleHighlight, remove };
}
