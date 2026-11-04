import { api, errorMessage } from '@focus/api-client';
import type { Task, TaskStatus } from '@focus/types';
import { todayIso } from '@/lib/dates';

export function useTaskActions(onChange: () => void) {
    const toast = useToast();
    const outbox = useOutboxStore();

    async function setStatus(
        task: Pick<Task, 'id' | 'title'> & Partial<Pick<Task, 'next_occurrence_id' | 'updated_at'>>,
        status: TaskStatus,
    ): Promise<void> {
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
            if (isOffline(e)) {
                outbox.queueTaskUpdate(task, { status, ...(status === 'completed' && { completed_at: todayIso() }) });
                toast.info(`You’re offline. “${task.title}” will sync when you’re back.`);
                return;
            }
            toast.error(errorMessage(e, 'Could not update the task.'));
        }
    }

    async function moveToToday(tasks: Task[]): Promise<void> {
        if (!tasks.length) return;
        try {
            const moved = await api.tasks.moveToToday(tasks.map((task) => task.id));
            toast.success(tasks.length === 1 ? `Moved “${tasks[0]!.title}” to today` : `Moved ${moved} tasks to today`);
            onChange();
        } catch (e) {
            toast.error(errorMessage(e, 'Could not move the tasks.'));
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
            if (isOffline(e)) {
                outbox.queueTaskUpdate(task, { is_highlighted: !task.is_highlighted });
                toast.info(`You’re offline. “${task.title}” will sync when you’re back.`);
                return;
            }
            toast.error(errorMessage(e, 'Could not update the task.'));
        }
    }

    const toggleComplete = (task: Task) => setStatus(task, task.status === 'completed' ? 'not_started' : 'completed');

    return { setStatus, toggleComplete, toggleHighlight, moveToToday, remove };
}
