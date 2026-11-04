import { api, errorMessage } from '@focus/api-client';
import type { Task, TaskStatus } from '@focus/types';
import { todayIso } from '@/lib/dates';

export function useTaskActions(onChange: () => void) {
    const toast = useToast();
    const outbox = useOutboxStore();

    function queued(task: Pick<Task, 'title'>): void {
        toast.info(`You’re offline. “${task.title}” will sync when you’re back.`);
    }

    async function setStatus(
        task: Pick<Task, 'id' | 'title'> & Partial<Pick<Task, 'next_occurrence_id' | 'updated_at'>>,
        status: TaskStatus,
    ): Promise<void> {
        const input = { status, ...(status === 'completed' && { completed_at: todayIso() }) };
        if (isLocal(task)) {
            outbox.queueTaskUpdate(task, input);
            return queued(task);
        }
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
            if (!isOffline(e)) return void toast.error(errorMessage(e, 'Could not update the task.'));
            outbox.queueTaskUpdate(task, input);
            queued(task);
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
            if (isLocal(task)) {
                outbox.queueRemove('tasks', task);
                return void toast.success('Task deleted');
            }
            await api.tasks.remove(task.id);
            toast.success('Task deleted');
            onChange();
        } catch (e) {
            if (!isOffline(e)) return void toast.error(errorMessage(e, 'Could not delete the task.'));
            outbox.queueRemove('tasks', task);
            toast.info(`You’re offline. “${task.title}” will be deleted when you’re back.`);
        }
    }

    async function toggleHighlight(task: Task): Promise<void> {
        if (isLocal(task)) {
            outbox.queueTaskUpdate(task, { is_highlighted: !task.is_highlighted });
            return queued(task);
        }
        try {
            await api.tasks.update(task.id, { is_highlighted: !task.is_highlighted });
            toast.success(task.is_highlighted ? 'Removed from highlights' : 'Added to highlights');
            onChange();
        } catch (e) {
            if (!isOffline(e)) return void toast.error(errorMessage(e, 'Could not update the task.'));
            outbox.queueTaskUpdate(task, { is_highlighted: !task.is_highlighted });
            queued(task);
        }
    }

    const toggleComplete = (task: Task) => setStatus(task, task.status === 'completed' ? 'not_started' : 'completed');

    return { setStatus, toggleComplete, toggleHighlight, moveToToday, remove };
}
