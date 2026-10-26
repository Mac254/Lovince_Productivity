<script setup lang="ts">
import { Icon, IconButton } from '@focus/ui';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FocusTimer from '@/features/clock/FocusTimer.vue';
import { loadPrefs, savePrefs, wallpapers } from '@/features/clock/wallpapers';
import { useDashboardStore } from '@/stores/dashboard';

const router = useRouter();
const route = useRoute();
const dashboard = useDashboardStore();

const modes = [
    { id: 'clock', label: 'Clock' },
    { id: 'timer', label: 'Timer' },
] as const;
const mode = computed<'clock' | 'timer'>({
    get: () => (route.query.mode === 'timer' ? 'timer' : 'clock'),
    set: (value) => void router.replace({ query: value === 'timer' ? { mode: 'timer' } : {} }),
});

const prefs = reactive(loadPrefs());
const now = ref(new Date());
const controlsVisible = ref(true);
const fullscreen = ref(false);
const root = ref<HTMLElement | null>(null);
let tick: number | undefined;
let idle: number | undefined;

const wallpaper = computed(() => wallpapers.find((w) => w.id === prefs.wallpaper) ?? wallpapers[0]!);

const time = computed(() =>
    new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', hour12: prefs.hour12 }).format(now.value),
);
const seconds = computed(() => String(now.value.getSeconds()).padStart(2, '0'));
const date = computed(() =>
    new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' }).format(now.value),
);
const next = computed(() => dashboard.data?.today_tasks[0] ?? dashboard.data?.overdue_tasks[0] ?? null);
const progress = computed(() => {
    const stats = dashboard.data?.stats;
    if (!stats) return null;
    const total = stats.completed_today + stats.due_today;
    return total ? `${stats.completed_today} of ${total} done today` : null;
});

/** Controls fade away on the clock after a few idle seconds; the timer keeps them visible. */
function wake(): void {
    controlsVisible.value = true;
    window.clearTimeout(idle);
    idle = window.setTimeout(() => (controlsVisible.value = false), 3000);
}

async function toggleFullscreen(): Promise<void> {
    try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else await root.value?.requestFullscreen();
    } catch {
        // Fullscreen can be refused (e.g. iframes); the page still fills the window.
    }
}

function onKey(event: KeyboardEvent): void {
    wake();
    const target = event.target as HTMLElement | null;
    if (target && ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)) return;
    if (event.key.toLowerCase() === 'f' && !event.ctrlKey && !event.metaKey) void toggleFullscreen();
}

const syncFullscreen = () => (fullscreen.value = !!document.fullscreenElement);

watch(prefs, savePrefs, { deep: true });

onMounted(() => {
    tick = window.setInterval(() => (now.value = new Date()), 500);
    document.addEventListener('fullscreenchange', syncFullscreen);
    document.addEventListener('keydown', onKey);
    void dashboard.load();
    wake();
});

onBeforeUnmount(() => {
    window.clearInterval(tick);
    window.clearTimeout(idle);
    document.removeEventListener('fullscreenchange', syncFullscreen);
    document.removeEventListener('keydown', onKey);
    if (document.fullscreenElement) void document.exitFullscreen();
});
</script>

<template>
    <main
        ref="root"
        :class="[
            'clock relative flex min-h-dvh flex-col overflow-x-hidden',
            `wallpaper-${wallpaper.id}`,
            wallpaper.ink === 'light' ? 'text-fg-inverse' : 'text-fg',
        ]"
        @pointermove="wake"
        @focusin="wake"
    >
        <h1 class="sr-only">{{ mode === 'timer' ? 'Focus timer' : 'Focus clock' }}</h1>

        <div
            :class="[
                'z-(--z-sticky) flex flex-wrap items-center justify-between gap-3 p-4 transition-opacity duration-(--duration-slow) sm:p-6',
                controlsVisible || mode === 'timer' ? 'opacity-100' : 'pointer-events-none opacity-0',
            ]"
        >
            <div class="flex items-center gap-3">
                <IconButton
                    icon="arrow-left"
                    label="Leave focus mode"
                    variant="secondary"
                    motion="nudge-back"
                    :to="{ name: 'dashboard' }"
                />
                <div role="group" aria-label="Mode" class="flex rounded-full bg-chrome p-1 shadow-floating">
                    <button
                        v-for="item in modes"
                        :key="item.id"
                        type="button"
                        :aria-pressed="mode === item.id"
                        :class="[
                            'h-(--control-sm) rounded-full px-4 text-label font-semibold transition-colors',
                            mode === item.id
                                ? 'bg-highlight text-highlight-ink'
                                : 'text-fg-inverse hover:bg-chrome-hover',
                        ]"
                        @click="mode = item.id"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>
            <div
                class="flex flex-wrap items-center gap-2 rounded-full bg-chrome p-2 shadow-floating"
                role="group"
                aria-label="Display settings"
            >
                <button
                    v-for="item in wallpapers"
                    :key="item.id"
                    type="button"
                    :aria-label="`Wallpaper: ${item.label}`"
                    :aria-pressed="prefs.wallpaper === item.id"
                    :title="item.label"
                    :class="[
                        'swatch size-(--control-sm) rounded-full border-2 transition-transform duration-(--duration-fast) hover:scale-110',
                        `wallpaper-${item.id}`,
                        prefs.wallpaper === item.id ? 'border-highlight' : 'border-chrome-border',
                    ]"
                    @click="prefs.wallpaper = item.id"
                />
                <template v-if="mode === 'clock'">
                    <span class="mx-1 h-6 w-px bg-chrome-border" aria-hidden="true" />
                    <button
                        type="button"
                        :aria-pressed="prefs.hour12"
                        class="h-(--control-sm) rounded-full px-3 text-label font-semibold text-fg-inverse hover:bg-chrome-hover"
                        @click="prefs.hour12 = !prefs.hour12"
                    >
                        {{ prefs.hour12 ? '12h' : '24h' }}
                    </button>
                    <button
                        type="button"
                        :aria-pressed="prefs.seconds"
                        class="h-(--control-sm) rounded-full px-3 text-label font-semibold text-fg-inverse hover:bg-chrome-hover"
                        @click="prefs.seconds = !prefs.seconds"
                    >
                        Seconds
                    </button>
                </template>
                <IconButton
                    :icon="fullscreen ? 'exit-fullscreen' : 'fullscreen'"
                    :label="fullscreen ? 'Exit full screen (F)' : 'Full screen (F)'"
                    variant="primary"
                    size="sm"
                    @click="toggleFullscreen"
                />
            </div>
        </div>

        <div v-if="mode === 'timer'" class="flex flex-1 justify-center px-4 pb-12">
            <FocusTimer />
        </div>

        <div v-else class="flex flex-1 flex-col items-center justify-center gap-6 px-4 text-center">
            <p class="text-lead font-semibold opacity-90">{{ date }}</p>
            <time :datetime="now.toISOString()" class="clock-face font-display font-semibold tracking-tight tabular">
                {{ time }}<span v-if="prefs.seconds" class="clock-seconds">{{ seconds }}</span>
            </time>
            <div
                v-if="next || progress"
                class="flex max-w-dialog flex-col items-center gap-2 rounded-lg bg-chrome px-6 py-4 text-fg-inverse shadow-floating"
            >
                <p v-if="progress" class="flex items-center gap-2 text-label font-semibold text-highlight">
                    <Icon name="flame" />{{ progress }}
                </p>
                <p v-if="next" class="text-lead font-medium">
                    <span class="text-fg-inverse-muted">Next: </span>
                    <RouterLink
                        :to="{ name: 'task-detail', params: { id: next.id } }"
                        class="underline-offset-4 hover:underline"
                        >{{ next.title }}</RouterLink
                    >
                </p>
                <RouterLink
                    :to="{ query: { mode: 'timer' } }"
                    class="group mt-1 inline-flex items-center gap-2 text-label font-semibold text-highlight underline-offset-4 hover:underline"
                >
                    <Icon name="clock" motion="spin" />
                    Start a focus timer
                </RouterLink>
            </div>
            <figure v-if="dashboard.data?.quote" class="max-w-dialog opacity-90">
                <blockquote class="text-lead font-medium text-balance">“{{ dashboard.data.quote.text }}”</blockquote>
                <figcaption class="mt-2 text-label opacity-80">
                    — {{ dashboard.data.quote.author ?? 'Unknown' }}
                </figcaption>
            </figure>
        </div>
    </main>
</template>

<style scoped>
/* Wallpapers are drawn from theme colours; decorative only. */
.clock-face {
    font-size: clamp(5rem, 18vw, 16rem);
    line-height: 1;
}

.clock-seconds {
    margin-left: 0.15em;
    font-size: 0.3em;
    vertical-align: super;
    opacity: 0.7;
}

.wallpaper-neon {
    background:
        radial-gradient(60% 50% at 20% 25%, color-mix(in oklab, var(--highlight) 45%, transparent), transparent 70%),
        radial-gradient(50% 45% at 85% 80%, color-mix(in oklab, var(--highlight) 30%, transparent), transparent 70%),
        var(--accent-solid);
}

.wallpaper-grid {
    background:
        linear-gradient(color-mix(in oklab, var(--fg-inverse) 6%, transparent) 1px, transparent 1px) 0 0 / 48px 48px,
        linear-gradient(90deg, color-mix(in oklab, var(--fg-inverse) 6%, transparent) 1px, transparent 1px) 0 0 / 48px
            48px,
        var(--surface-inverse);
}

.wallpaper-aurora {
    background:
        radial-gradient(120% 70% at 50% 120%, color-mix(in oklab, var(--highlight) 55%, transparent), transparent 60%),
        linear-gradient(160deg, var(--accent-solid), var(--surface-inverse) 60%, var(--accent-solid));
}

.wallpaper-tones {
    background:
        radial-gradient(40% 40% at 15% 20%, color-mix(in oklab, var(--tone-violet) 55%, transparent), transparent 70%),
        radial-gradient(40% 40% at 85% 25%, color-mix(in oklab, var(--tone-blue) 55%, transparent), transparent 70%),
        radial-gradient(45% 45% at 70% 85%, color-mix(in oklab, var(--tone-green) 55%, transparent), transparent 70%),
        radial-gradient(35% 35% at 20% 85%, color-mix(in oklab, var(--tone-rose) 45%, transparent), transparent 70%),
        var(--accent-solid);
}

.wallpaper-midnight {
    background: var(--accent-solid);
}

.wallpaper-paper {
    background: radial-gradient(80% 60% at 50% 0%, var(--surface), transparent 70%), var(--canvas);
}

.swatch {
    background-size: cover;
}
</style>
