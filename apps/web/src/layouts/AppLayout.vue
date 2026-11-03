<script setup lang="ts">
import { formatLongDate, greeting, todayIso } from '@/lib/dates';

const auth = useAuthStore();
const dashboard = useDashboard();
const goals = useGoalsStore();
const router = useRouter();
const toast = useToast();
const timer = useTimerStore();
const preferences = usePreferencesStore();
const outbox = useTaskOutboxStore();
const nextTheme = computed(() => (preferences.resolved === 'dark' ? 'light' : 'dark'));
const themeOptions = [
    { value: 'dark' as const, label: 'Dark theme', icon: 'moon' as const },
    { value: 'light' as const, label: 'Light theme', icon: 'sun' as const },
    { value: 'system' as const, label: 'Match your device', icon: 'monitor' as const },
];
const streak = computed(() => dashboard.data?.streak ?? null);
const streakLabel = computed(() => {
    if (!streak.value) return '';
    const days = `${streak.value.current}-day streak`;
    if (!streak.value.current) return 'No streak yet. Finish a task, log work or focus to start one';
    return streak.value.active_today ? `${days}, active today` : `${days}. Do something today to keep it`;
});

const drawerOpen = ref(false);
const quickTaskOpen = ref(false);
const paletteOpen = ref(false);
const shortcut = typeof navigator !== 'undefined' && /mac/i.test(navigator.platform) ? '⌘K' : 'Ctrl+K';

function onShortcut(event: KeyboardEvent): void {
    if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'k') return;
    event.preventDefault();
    paletteOpen.value = !paletteOpen.value;
}

let dayCheck: number | undefined;
function refreshOnNewDay(): void {
    if (dashboard.data && dashboard.data.date !== todayIso() && !dashboard.loading) void dashboard.invalidate();
}

onMounted(() => {
    window.addEventListener('keydown', onShortcut);
    dayCheck = window.setInterval(refreshOnNewDay, 60_000);
    void syncOutbox().then((synced) => {
        if (synced) void dashboard.invalidate();
    });
});
onUnmounted(() => {
    window.removeEventListener('keydown', onShortcut);
    window.clearInterval(dayCheck);
});
useReminders();
async function syncOutbox(): Promise<number> {
    if (!outbox.pending.length) return 0;
    const synced = await outbox.flush();
    if (synced) toast.success(synced === 1 ? 'Synced 1 task change' : `Synced ${synced} task changes`);
    return synced;
}

const online = useOnline(() => {
    void timer.flush();
    void syncOutbox().finally(() => dashboard.invalidate());
    toast.success('Back online');
});

const firstName = computed(() => auth.user?.name.split(' ')[0] ?? '');

async function signOut(): Promise<void> {
    try {
        await auth.logout();
    } catch {
        toast.error('Sign-out could not reach the server; your local session was cleared.');
    }
    dashboard.reset();
    goals.reset();
    outbox.clear();
    await router.replace({ name: 'login' });
}

const accountMenu = [
    { label: 'Settings', icon: 'user' as const, onSelect: () => router.push({ name: 'account' }) },
    {
        label: 'Focus timer',
        icon: 'clock' as const,
        onSelect: () => router.push({ name: 'focus', query: { mode: 'timer' } }),
    },
    { label: 'Sign out', icon: 'logout' as const, onSelect: signOut },
];

function onQuickTaskSaved(): void {
    goals.reset();
    void dashboard.invalidate();
}
</script>

<template>
    <a
        href="#main"
        class="fixed top-3 left-3 z-(--z-skip) -translate-y-24 rounded-full bg-highlight px-4 py-2 font-semibold text-highlight-ink focus:translate-y-0"
    >
        Skip to content
    </a>

    <aside
        class="fixed inset-y-0 left-0 z-(--z-nav) hidden w-(--sidebar-width) flex-col gap-8 overflow-y-auto border-r border-chrome-border bg-chrome px-4 py-6 lg:flex"
    >
        <RouterLink :to="{ name: 'dashboard' }" class="group flex items-center gap-3 rounded-md px-2 text-chrome-fg">
            <BrandMark />
            <span class="font-display text-title font-semibold tracking-tight">LovinceTracker</span>
        </RouterLink>
        <SidebarNav />
        <div class="mt-auto flex flex-col gap-3">
            <RouterLink
                :to="{ name: 'focus', query: { mode: 'timer' } }"
                class="group flex items-center gap-3 rounded-md border border-chrome-border px-3 py-2 text-label font-semibold text-chrome-fg transition-colors hover:bg-chrome-hover"
            >
                <Icon name="clock" size="md" motion="spin" />
                Focus timer
                <span v-if="timer.active" class="ml-auto text-highlight tabular">{{ timer.display }}</span>
            </RouterLink>
        </div>
    </aside>

    <div class="min-h-dvh lg:pl-(--sidebar-width)">
        <header class="sticky top-0 z-(--z-sticky) border-b border-border bg-canvas">
            <div class="flex h-(--topbar-height) w-full items-center gap-3 px-4 sm:px-6 lg:px-8">
                <IconButton
                    icon="menu"
                    label="Open navigation"
                    variant="ghost"
                    class="lg:hidden"
                    @click="drawerOpen = true"
                    ><AnimatedIcon kind="menu" :active="drawerOpen"
                /></IconButton>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-label font-semibold">{{ greeting() }}, {{ firstName }}</p>
                    <p class="truncate text-caption text-fg-muted">
                        {{ formatLongDate(dashboard.data?.date ?? todayIso()) }}
                    </p>
                </div>
                <IconButton
                    icon="search"
                    label="Quick add and commands"
                    :shortcut="shortcut"
                    variant="ghost"
                    motion="pop"
                    @click="paletteOpen = true"
                />
                <Tooltip v-if="streak" :label="streakLabel">
                    <RouterLink
                        :to="{ name: 'metrics', query: { tab: 'focus' } }"
                        :aria-label="streakLabel"
                        :class="[
                            'group inline-flex h-(--control-sm) items-center gap-1 rounded-full px-3 text-label font-bold tabular ring-1 transition-colors duration-(--duration-fast)',
                            streak.active_today
                                ? 'bg-highlight text-highlight-ink ring-transparent'
                                : 'text-fg ring-border hover:ring-border-hover',
                        ]"
                    >
                        <Icon
                            name="flame"
                            size="sm"
                            motion="pop"
                            :class="!streak.active_today && streak.current && 'text-highlight-fg'"
                        />
                        <span aria-hidden="true">{{ streak.current }}</span>
                    </RouterLink>
                </Tooltip>
                <SegmentedControl
                    v-model="preferences.prefs.theme"
                    label="Theme"
                    :options="themeOptions"
                    icon-only
                    size="sm"
                    class="max-md:hidden"
                />
                <IconButton
                    :icon="nextTheme === 'light' ? 'sun' : 'moon'"
                    :label="`Switch to ${nextTheme} theme`"
                    variant="ghost"
                    motion="spin"
                    class="md:hidden"
                    @click="preferences.prefs.theme = nextTheme"
                    ><MorphIcon :name="preferences.resolved === 'dark' ? 'moon' : 'sun'"
                /></IconButton>
                <RouterLink
                    v-if="timer.active"
                    :to="{ name: 'focus', query: { mode: 'timer' } }"
                    class="group inline-flex h-(--control-sm) items-center gap-2 rounded-full bg-chrome px-3 text-label font-semibold text-chrome-fg"
                    :aria-label="`${timer.kind === 'focus' ? 'Focus' : 'Break'} timer: ${timer.display} left${timer.status === 'paused' ? ', paused' : ''}`"
                >
                    <span
                        :class="[
                            'size-2 rounded-full',
                            timer.status === 'running' ? 'animate-pulse bg-highlight' : 'bg-chrome-fg-muted',
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
                    class="max-sm:hidden"
                    :to="{ name: 'focus', query: { mode: 'timer' } }"
                />
                <Button icon-start="add" size="sm" class="max-sm:hidden" @click="quickTaskOpen = true">New task</Button>
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

        <Alert v-if="!online" tone="warning" icon="offline" banner>
            You’re offline. Focus sessions are kept and saved when you reconnect.
        </Alert>

        <main id="main" tabindex="-1" class="w-full px-4 pt-6 pb-32 focus:outline-none sm:px-6 lg:px-8 lg:pt-8">
            <RouterView />
        </main>
    </div>

    <Dialog v-model:open="drawerOpen" title="LovinceTracker" size="sm">
        <div class="-mx-2 rounded-md bg-chrome p-4">
            <SidebarNav @navigate="drawerOpen = false" />
        </div>
    </Dialog>

    <FloatingActions />
    <CommandPalette v-model:open="paletteOpen" @new-task="quickTaskOpen = true" @created="onQuickTaskSaved" />
    <TaskFormDialog v-model:open="quickTaskOpen" :defaults="{ due_date: todayIso() }" @saved="onQuickTaskSaved" />
</template>
