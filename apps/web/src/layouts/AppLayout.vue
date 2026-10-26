<script setup lang="ts">
import { Avatar, Button, Dialog, DropdownMenu, Icon, IconButton, useToast } from '@focus/ui';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandMark from '@/components/BrandMark.vue';
import SidebarNav from '@/components/SidebarNav.vue';
import TaskFormDialog from '@/features/tasks/TaskFormDialog.vue';
import { formatLongDate, greeting, todayIso } from '@/lib/dates';
import { useAuthStore } from '@/stores/auth';
import { useDashboardStore } from '@/stores/dashboard';
import { useGoalsStore } from '@/stores/goals';
import { useTimerStore } from '@/stores/timer';

const auth = useAuthStore();
const dashboard = useDashboardStore();
const goals = useGoalsStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();
const timer = useTimerStore();

const drawerOpen = ref(false);
const quickTaskOpen = ref(false);
const firstName = computed(() => auth.user?.name.split(' ')[0] ?? '');

async function signOut(): Promise<void> {
    try {
        await auth.logout();
    } catch {
        toast.error('Sign-out could not reach the server; your local session was cleared.');
    }
    dashboard.reset();
    goals.reset();
    await router.replace({ name: 'login' });
}

const accountMenu = [
    { label: 'Account', icon: 'user' as const, onSelect: () => router.push({ name: 'account' }) },
    {
        label: 'Focus timer',
        icon: 'clock' as const,
        onSelect: () => router.push({ name: 'focus', query: { mode: 'timer' } }),
    },
    { label: 'Sign out', icon: 'logout' as const, onSelect: signOut },
];

function onQuickTaskSaved(): void {
    goals.reset();
    void dashboard.load();
}

watch(
    () => route.name,
    () => void dashboard.load(),
    { immediate: true },
);
</script>

<template>
    <a
        href="#main"
        class="fixed top-3 left-3 z-(--z-skip) -translate-y-24 rounded-full bg-highlight px-4 py-2 font-semibold text-highlight-ink focus:translate-y-0"
    >
        Skip to content
    </a>

    <aside
        class="fixed inset-y-0 left-0 z-(--z-nav) hidden w-(--sidebar-width) flex-col gap-8 overflow-y-auto bg-chrome px-4 py-6 lg:flex"
    >
        <RouterLink :to="{ name: 'dashboard' }" class="group flex items-center gap-3 rounded-md px-2 text-fg-inverse">
            <BrandMark />
            <span class="font-display text-title font-semibold tracking-tight">Focus</span>
        </RouterLink>
        <SidebarNav />
        <div class="mt-auto flex flex-col gap-3">
            <RouterLink
                :to="{ name: 'focus', query: { mode: 'timer' } }"
                class="group flex items-center gap-3 rounded-md border border-chrome-border px-3 py-2 text-label font-semibold text-fg-inverse transition-colors hover:bg-chrome-hover"
            >
                <Icon name="clock" size="md" motion="spin" />
                Focus timer
                <span v-if="timer.active" class="ml-auto text-highlight tabular">{{ timer.display }}</span>
            </RouterLink>
            <RouterLink
                :to="{ name: 'account' }"
                class="group flex items-center gap-3 rounded-md p-2 text-fg-inverse transition-colors hover:bg-chrome-hover"
            >
                <Avatar :name="auth.user?.name ?? ''" :src="auth.user?.avatar_url" />
                <span class="min-w-0">
                    <span class="block truncate text-label font-semibold">{{ auth.user?.name }}</span>
                    <span class="block truncate text-caption text-fg-inverse-muted">{{ auth.user?.email }}</span>
                </span>
            </RouterLink>
        </div>
    </aside>

    <div class="min-h-dvh lg:pl-(--sidebar-width)">
        <header class="sticky top-0 z-(--z-sticky) border-b border-border bg-canvas">
            <div class="mx-auto flex h-(--topbar-height) max-w-app items-center gap-3 px-4 sm:px-6 lg:px-8">
                <IconButton
                    icon="menu"
                    label="Open navigation"
                    variant="ghost"
                    class="lg:hidden"
                    @click="drawerOpen = true"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-label font-semibold">{{ greeting() }}, {{ firstName }}</p>
                    <p class="truncate text-caption text-fg-muted">
                        {{ formatLongDate(dashboard.data?.date ?? todayIso()) }}
                    </p>
                </div>
                <RouterLink
                    v-if="timer.active"
                    :to="{ name: 'focus', query: { mode: 'timer' } }"
                    class="group inline-flex h-(--control-sm) items-center gap-2 rounded-full bg-chrome px-3 text-label font-semibold text-fg-inverse"
                    :aria-label="`${timer.kind === 'focus' ? 'Focus' : 'Break'} timer: ${timer.display} left${timer.status === 'paused' ? ', paused' : ''}`"
                >
                    <span
                        :class="[
                            'size-2 rounded-full',
                            timer.status === 'running' ? 'animate-pulse bg-highlight' : 'bg-fg-inverse-muted',
                        ]"
                        aria-hidden="true"
                    />
                    <span class="tabular" aria-hidden="true">{{ timer.display }}</span>
                </RouterLink>
                <IconButton
                    v-else
                    icon="clock"
                    label="Open focus timer"
                    variant="ghost"
                    motion="spin"
                    :to="{ name: 'focus', query: { mode: 'timer' } }"
                />
                <Button icon-start="add" size="sm" class="hidden sm:inline-flex" @click="quickTaskOpen = true"
                    >New task</Button
                >
                <IconButton
                    icon="add"
                    label="New task"
                    variant="primary"
                    class="sm:hidden"
                    @click="quickTaskOpen = true"
                />
                <DropdownMenu
                    :label="`Account menu for ${auth.user?.name ?? 'you'}`"
                    :items="accountMenu"
                    trigger-variant="ghost"
                >
                    <template #trigger
                        ><Avatar :name="auth.user?.name ?? ''" :src="auth.user?.avatar_url" size="sm"
                    /></template>
                    <template #header>
                        <div class="mb-1 border-b border-border px-3 pt-2 pb-3">
                            <p class="truncate text-label font-semibold">{{ auth.user?.name }}</p>
                            <p class="truncate text-caption text-fg-muted">{{ auth.user?.email }}</p>
                        </div>
                    </template>
                </DropdownMenu>
            </div>
        </header>

        <main id="main" tabindex="-1" class="mx-auto max-w-app px-4 py-6 focus:outline-none sm:px-6 lg:px-8 lg:py-8">
            <RouterView />
        </main>
    </div>

    <Dialog v-model:open="drawerOpen" title="Focus" size="sm">
        <div class="-mx-2 rounded-md bg-chrome p-4">
            <SidebarNav @navigate="drawerOpen = false" />
        </div>
    </Dialog>

    <TaskFormDialog v-model:open="quickTaskOpen" :defaults="{ due_date: todayIso() }" @saved="onQuickTaskSaved" />
</template>
