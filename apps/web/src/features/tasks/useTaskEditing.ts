import { ref } from 'vue';
import type { Task, TaskInput } from '@focus/types';
import { todayIso } from '@/lib/dates';
import { useTaskActions } from './useTaskActions';

/** Shared create / edit / delete-confirmation state for pages that show tasks. */
export function useTaskEditing(onChange: () => unknown) {
    const actions = useTaskActions(() => void onChange());
    const dialogOpen = ref(false);
    const current = ref<Task | null>(null);
    const defaults = ref<TaskInput>({});
    const deleting = ref<Task | null>(null);
    const deletingBusy = ref(false);

    function create(preset: TaskInput = { due_date: todayIso() }): void {
        current.value = null;
        defaults.value = preset;
        dialogOpen.value = true;
    }

    function edit(task: Task): void {
        current.value = task;
        dialogOpen.value = true;
    }

    function askDelete(task: Task): void {
        deleting.value = task;
    }

    async function confirmDelete(): Promise<void> {
        if (!deleting.value) return;
        deletingBusy.value = true;
        await actions.remove(deleting.value);
        deletingBusy.value = false;
        deleting.value = null;
    }

    return { actions, dialogOpen, current, defaults, deleting, deletingBusy, create, edit, askDelete, confirmDelete };
}
