<script setup lang="ts">
import { api } from '@focus/api-client';
import type { Task } from '@focus/types';
import type { IconName } from '@focus/ui';
import type { RouteLocationRaw } from 'vue-router';
import { todayIso } from '@/lib/dates';

const route = useRoute();
const settings = useSettingsStore();
const open = ref(false);
const planOpen = ref(false);
const planId = useId();
const today = ref(todayIso());
const tasks = ref<Task[]>([]);
const showPlan = computed(() => settings.values.planner.enabled && route.name !== 'today');
const root = ref<HTMLElement | null>(null);
const menuId = useId();

const actions: { label: string; icon: IconName; to: RouteLocationRaw }[] = [
    { label: 'Suggestions', icon: 'lightbulb', to: { name: 'suggestions' } },
    { label: 'New schedule', icon: 'calendar-clock', to: { name: 'schedules', query: { new: '1' } } },
    { label: "Today's journal", icon: 'journal', to: { name: 'journal' } },
];

function onOutside(event: PointerEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) open.value = false;
}

async function loadTasks(): Promise<void> {
    tasks.value = (
        await api.tasks.list({ due: 'today', status: 'open', per_page: 100 }).catch(() => ({ data: [] as Task[] }))
    ).data;
}

function togglePlan(): void {
    planOpen.value = !planOpen.value;
    if (!planOpen.value) return;
    open.value = false;
    today.value = todayIso();
    void loadTasks();
}

watch(
    () => route.fullPath,
    () => (open.value = false),
);
onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>

<template>
    <div
        ref="root"
        class="fixed right-4 bottom-6 z-(--z-sticky) flex flex-col items-end gap-3 sm:right-6"
        @keydown.esc="open = false"
    >
        <section
            v-if="planOpen && showPlan"
            :id="planId"
            aria-label="Today’s day plan"
            class="bubble max-h-(--floating-panel-max-height) w-(--floating-panel-width) overflow-y-auto rounded-lg rounded-br-sm shadow-floating"
        >
            <DayPlanner :date="today" :tasks="tasks" focus-now @changed="loadTasks" />
        </section>
        <ul v-show="open" :id="menuId" class="flex flex-col items-end gap-2">
            <li
                v-for="(action, index) in actions"
                :key="action.label"
                class="bubble"
                :style="{ animationDelay: `calc(var(--duration-instant) * ${actions.length - 1 - index})` }"
            >
                <RouterLink
                    :to="action.to"
                    class="group flex items-center gap-3 rounded-full rounded-br-sm border border-border bg-surface-raised py-2 pr-2 pl-4 text-label font-semibold text-fg shadow-floating transition-colors duration-(--duration-fast) hover:bg-surface-overlay focus-visible:outline-2 focus-visible:outline-focus-ring"
                >
                    {{ action.label }}
                    <span
                        class="inline-flex size-(--control-sm) items-center justify-center rounded-full bg-highlight text-highlight-ink"
                    >
                        <Icon :name="action.icon" motion="pop" />
                    </span>
                </RouterLink>
            </li>
        </ul>
        <button
            v-if="showPlan"
            type="button"
            :aria-expanded="planOpen"
            :aria-controls="planId"
            :aria-label="planOpen ? 'Close the day plan' : 'Open today’s day plan'"
            class="group inline-flex size-(--control-md) items-center justify-center rounded-full border border-border bg-surface-raised text-fg shadow-floating transition-transform duration-(--duration-fast) hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring"
            @click="togglePlan"
        >
            <Icon :name="planOpen ? 'close' : 'calendar-clock'" motion="pop" />
        </button>
        <button
            type="button"
            :aria-expanded="open"
            :aria-controls="menuId"
            :aria-label="open ? 'Close quick actions' : 'Open quick actions'"
            class="group inline-flex size-(--control-lg) items-center justify-center rounded-full bg-highlight text-highlight-ink shadow-floating transition-transform duration-(--duration-fast) hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring"
            @click="open = !open"
        >
            <AnimatedIcon kind="add" :active="open" size="md" />
        </button>
    </div>
</template>

<style scoped>
.bubble {
    animation: bubble-in var(--duration-normal) var(--ease-out) both;
    transform-origin: bottom right;
}

@keyframes bubble-in {
    from {
        opacity: 0;
        translate: 0 var(--spacing);
        scale: 0.9;
    }
}
</style>
