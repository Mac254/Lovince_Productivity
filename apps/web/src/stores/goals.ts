import { api } from '@focus/api-client';
import type { Goal } from '@focus/types';
import { defineStore } from 'pinia';
import { ref } from 'vue';

/** Goals are shared by the goals page and every task form's goal picker. */
export const useGoalsStore = defineStore('goals', () => {
    const goals = ref<Goal[]>([]);
    const loaded = ref(false);

    async function load(force = false): Promise<Goal[]> {
        if (loaded.value && !force) return goals.value;
        goals.value = await api.goals.list();
        loaded.value = true;
        return goals.value;
    }

    function reset(): void {
        goals.value = [];
        loaded.value = false;
    }

    return { goals, loaded, load, reset };
});
