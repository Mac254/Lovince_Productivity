import { api } from '@focus/api-client';
import type { TaskType, TaskTypeInput } from '@focus/types';
import { defineStore } from 'pinia';
import { ref } from 'vue';

/** The user's task types, shared by the task form, the Tasks filters and the types manager. */
export const useTaskTypesStore = defineStore('taskTypes', () => {
    const types = ref<TaskType[]>([]);
    const loaded = ref(false);

    async function load(force = false): Promise<TaskType[]> {
        if (loaded.value && !force) return types.value;
        types.value = await api.taskTypes.list();
        loaded.value = true;
        return types.value;
    }

    async function create(input: TaskTypeInput): Promise<TaskType> {
        const type = await api.taskTypes.create(input);
        types.value = [...types.value, type].sort((a, b) => a.name.localeCompare(b.name));
        return type;
    }

    async function update(id: number, input: TaskTypeInput): Promise<TaskType> {
        const type = await api.taskTypes.update(id, input);
        types.value = types.value.map((t) => (t.id === id ? type : t)).sort((a, b) => a.name.localeCompare(b.name));
        return type;
    }

    async function remove(id: number): Promise<void> {
        await api.taskTypes.remove(id);
        types.value = types.value.filter((t) => t.id !== id);
    }

    return { types, loaded, load, create, update, remove };
});
