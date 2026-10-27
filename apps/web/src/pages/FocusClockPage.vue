<script setup lang="ts">
import { LIMITS } from '@focus/types';
import { IconButton } from '@focus/ui';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DayRail from '@/features/clock/DayRail.vue';
import FocusTimer from '@/features/clock/FocusTimer.vue';
import RollingDigits from '@/features/clock/RollingDigits.vue';
import TodayStrip from '@/features/clock/TodayStrip.vue';
import { useWallpapers } from '@/features/clock/useWallpapers';
import { customWallpaperKey, loadPrefs, savePrefs, wallpapers } from '@/features/clock/wallpapers';
import { useDashboard } from '@/composables/useDashboard';
import { isoWeek } from '@/lib/dates';
import { createTicker } from '@/lib/ticker';

const router = useRouter();
const route = useRoute();
const dashboard = useDashboard();

const modes = [
    { id: 'clock', label: 'Clock' },
    { id: 'timer', label: 'Timer' },
] as const;
const mode = computed<'clock' | 'timer'>({
    get: () => (route.query.mode === 'timer' ? 'timer' : 'clock'),
    set: (value) => void router.replace({ query: value === 'timer' ? { mode: 'timer' } : {} }),
});

const prefs = reactive(loadPrefs());
const { uploads, uploading, activeUpload, wallpaper, load: loadUploads, upload, removeActive } = useWallpapers(prefs);
const ticker = createTicker();

const now = ref(new Date());
const controlsVisible = ref(true);
const settingsOpen = ref(false);
const fullscreen = ref(false);
const root = ref<HTMLElement | null>(null);
const settings = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
let tick: number | undefined;
let idle: number | undefined;

const photoStyle = computed(() =>
    activeUpload.value
        ? { backgroundImage: `linear-gradient(rgb(0 0 0 / 0.35), rgb(0 0 0 / 0.6)), url("${activeUpload.value.url}")` }
        : undefined,
);

const timeParts = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        hour: prefs.hour12 ? 'numeric' : '2-digit',
        minute: '2-digit',
        hour12: prefs.hour12,
    }).formatToParts(now.value),
);
const time = computed(() =>
    timeParts.value
        .filter((part) => part.type !== 'dayPeriod')
        .map((part) => part.value)
        .join('')
        .trim(),
);
const period = computed(() => timeParts.value.find((part) => part.type === 'dayPeriod')?.value ?? '');
const seconds = computed(() => now.value.getSeconds());
const dateLine = computed(
    () =>
        `${new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' }).format(now.value)} · Week ${isoWeek(now.value)}`,
);
const showControls = computed(() => controlsVisible.value || settingsOpen.value || mode.value === 'timer');

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
    if (event.key === 'Escape') settingsOpen.value = false;
    const target = event.target as HTMLElement | null;
    if (target && ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)) return;
    if (event.key.toLowerCase() === 'f' && !event.ctrlKey && !event.metaKey) void toggleFullscreen();
}

function onPointerDown(event: PointerEvent): void {
    if (settingsOpen.value && !settings.value?.contains(event.target as Node)) settingsOpen.value = false;
}

function onFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (fileInput.value) fileInput.value.value = '';
    if (file) void upload(file);
}

const syncFullscreen = () => (fullscreen.value = !!document.fullscreenElement);

watch(prefs, savePrefs, { deep: true });
watch(
    () => prefs.tick,
    (on) => (on ? ticker.start() : ticker.stop()),
);

onMounted(() => {
    tick = window.setInterval(() => (now.value = new Date()), 250);
    document.addEventListener('fullscreenchange', syncFullscreen);
    document.addEventListener('keydown', onKey);
    document.addEventListener('pointerdown', onPointerDown);
    if (prefs.tick) ticker.start();
    void loadUploads();
    wake();
});

onBeforeUnmount(() => {
    window.clearInterval(tick);
    window.clearTimeout(idle);
    ticker.stop();
    document.removeEventListener('fullscreenchange', syncFullscreen);
    document.removeEventListener('keydown', onKey);
    document.removeEventListener('pointerdown', onPointerDown);
    if (document.fullscreenElement) void document.exitFullscreen();
});
</script>

<template>
    <main
        ref="root"
        :class="[
            'clock relative flex min-h-dvh flex-col overflow-x-hidden bg-chrome',
            wallpaper.ink === 'light' ? 'text-chrome-fg' : 'text-fg-inverse',
            activeUpload && 'on-photo',
            !showControls && 'cursor-none',
        ]"
        @pointermove="wake"
        @focusin="wake"
    >
        <Transition name="wallpaper">
            <div
                :key="activeUpload ? `photo-${activeUpload.id}` : wallpaper.id"
                :class="['wallpaper-layer', `wallpaper-${wallpaper.id}`]"
                :style="photoStyle"
                aria-hidden="true"
            />
        </Transition>

        <h1 class="sr-only">{{ mode === 'timer' ? 'Focus timer' : 'Focus clock' }}</h1>

        <header
            :class="[
                'z-(--z-sticky) flex items-center justify-between gap-3 p-4 transition-opacity duration-(--duration-slow) sm:p-6',
                showControls ? 'opacity-100' : 'pointer-events-none opacity-0',
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
                                : 'text-chrome-fg hover:bg-chrome-hover',
                        ]"
                        @click="mode = item.id"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <div ref="settings" class="relative flex items-center gap-1 rounded-full bg-chrome p-1 shadow-floating">
                <button
                    type="button"
                    :aria-expanded="settingsOpen"
                    aria-controls="clock-settings"
                    :class="[
                        'h-(--control-sm) rounded-full px-4 text-label font-semibold text-chrome-fg transition-colors hover:bg-chrome-hover',
                        settingsOpen && 'bg-chrome-hover',
                    ]"
                    @click="settingsOpen = !settingsOpen"
                >
                    Display
                </button>
                <IconButton
                    :icon="fullscreen ? 'exit-fullscreen' : 'fullscreen'"
                    :label="fullscreen ? 'Exit full screen (F)' : 'Full screen (F)'"
                    variant="primary"
                    size="sm"
                    @click="toggleFullscreen"
                />

                <Transition name="panel">
                    <div
                        v-show="settingsOpen"
                        id="clock-settings"
                        role="group"
                        aria-label="Display settings"
                        class="absolute top-full right-0 mt-2 flex settings-panel flex-col gap-6 rounded-lg border border-chrome-border bg-chrome p-6 text-chrome-fg shadow-overlay"
                    >
                        <div class="flex flex-col gap-3">
                            <p class="text-caption font-semibold tracking-wide text-chrome-fg-muted uppercase">
                                Wallpaper
                            </p>
                            <div class="grid grid-cols-6 gap-2">
                                <button
                                    v-for="item in wallpapers"
                                    :key="item.id"
                                    type="button"
                                    :aria-label="`Wallpaper: ${item.label}`"
                                    :aria-pressed="prefs.wallpaper === item.id"
                                    :title="item.label"
                                    :class="[
                                        'swatch aspect-square rounded-md border-2 transition-transform duration-(--duration-fast) hover:scale-105',
                                        `wallpaper-${item.id}`,
                                        prefs.wallpaper === item.id ? 'border-highlight' : 'border-chrome-border',
                                    ]"
                                    @click="prefs.wallpaper = item.id"
                                />
                                <button
                                    v-for="item in uploads"
                                    :key="`custom-${item.id}`"
                                    type="button"
                                    :aria-label="`Wallpaper: ${item.name}`"
                                    :aria-pressed="prefs.wallpaper === customWallpaperKey(item.id)"
                                    :title="item.name"
                                    :class="[
                                        'swatch aspect-square rounded-md border-2 bg-chrome-hover transition-transform duration-(--duration-fast) hover:scale-105',
                                        prefs.wallpaper === customWallpaperKey(item.id)
                                            ? 'border-highlight'
                                            : 'border-chrome-border',
                                    ]"
                                    :style="{ backgroundImage: `url(&quot;${item.url}&quot;)` }"
                                    @click="prefs.wallpaper = customWallpaperKey(item.id)"
                                />
                                <IconButton
                                    v-if="uploads.length < LIMITS.wallpapers"
                                    icon="upload"
                                    label="Add your own wallpaper"
                                    variant="ghost"
                                    size="sm"
                                    class="aspect-square size-auto rounded-md border-2 border-dashed border-chrome-border text-chrome-fg hover:bg-chrome-hover"
                                    :disabled="uploading"
                                    @click="fileInput?.click()"
                                />
                            </div>
                            <button
                                v-if="activeUpload"
                                type="button"
                                class="self-start text-label font-semibold text-chrome-fg-muted underline-offset-4 hover:text-chrome-fg hover:underline"
                                @click="removeActive"
                            >
                                Remove this photo
                            </button>
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                aria-label="Choose a wallpaper image"
                                @change="onFile"
                            />
                        </div>

                        <div class="flex flex-col gap-3">
                            <p class="text-caption font-semibold tracking-wide text-chrome-fg-muted uppercase">Time</p>
                            <div
                                role="group"
                                aria-label="Time format"
                                class="grid grid-cols-2 rounded-full bg-chrome-hover p-1"
                            >
                                <button
                                    v-for="option in [false, true]"
                                    :key="String(option)"
                                    type="button"
                                    :aria-pressed="prefs.hour12 === option"
                                    :class="[
                                        'h-(--control-sm) rounded-full text-label font-semibold transition-colors',
                                        prefs.hour12 === option ? 'bg-highlight text-highlight-ink' : 'text-chrome-fg',
                                    ]"
                                    @click="prefs.hour12 = option"
                                >
                                    {{ option ? '12-hour' : '24-hour' }}
                                </button>
                            </div>
                            <button
                                v-for="toggle in [
                                    { key: 'seconds', label: 'Show seconds' },
                                    { key: 'tick', label: 'Ticking sound' },
                                ] as const"
                                :key="toggle.key"
                                type="button"
                                role="switch"
                                :aria-checked="prefs[toggle.key]"
                                class="flex items-center justify-between gap-3 text-label font-semibold"
                                @click="prefs[toggle.key] = !prefs[toggle.key]"
                            >
                                {{ toggle.label }}
                                <span
                                    :class="[
                                        'switch relative h-5 w-9 rounded-full transition-colors',
                                        prefs[toggle.key] ? 'bg-highlight' : 'bg-chrome-border',
                                    ]"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>

                        <p class="text-caption text-chrome-fg-muted">
                            Press <kbd class="font-semibold text-chrome-fg">F</kbd> for full screen
                        </p>
                    </div>
                </Transition>
            </div>
        </header>

        <Transition name="mode" mode="out-in">
            <div v-if="mode === 'timer'" key="timer" class="flex flex-1 justify-center px-4 pb-12">
                <FocusTimer />
            </div>

            <div v-else key="clock" class="flex flex-1 flex-col px-4 pb-6 sm:px-12 sm:pb-8">
                <div class="flex flex-1 flex-col items-center justify-center py-8">
                    <div class="flex w-full flex-col items-stretch gap-12 sm:w-auto">
                        <div class="flex flex-col items-center gap-4 text-center">
                            <p class="text-label font-semibold tracking-wide uppercase opacity-70">{{ dateLine }}</p>
                            <time :datetime="now.toISOString()" class="clock-face font-display font-semibold tabular">
                                <RollingDigits :text="time" />
                                <span v-if="period || prefs.seconds" class="clock-aside">
                                    <span v-if="period">{{ period }}</span>
                                    <RollingDigits
                                        v-if="prefs.seconds"
                                        :text="String(seconds).padStart(2, '0')"
                                        :label="`${seconds} seconds`"
                                        class="opacity-60"
                                    />
                                </span>
                            </time>
                            <div
                                v-if="prefs.seconds"
                                class="sweep h-0.5 w-full overflow-hidden rounded-full"
                                aria-hidden="true"
                            >
                                <div
                                    :class="['sweep-fill h-full', seconds === 0 && 'is-reset']"
                                    :style="{ transform: `scaleX(${seconds / 59})` }"
                                />
                            </div>
                        </div>

                        <TodayStrip :data="dashboard.data" :error="dashboard.error" @retry="dashboard.load()" />
                    </div>
                </div>

                <footer class="flex flex-col gap-6">
                    <figure v-if="dashboard.data?.quote" class="max-w-dialog">
                        <blockquote class="text-body font-medium text-balance opacity-80">
                            “{{ dashboard.data.quote.text }}”
                        </blockquote>
                        <figcaption class="mt-1 text-caption opacity-60">
                            {{ dashboard.data.quote.author ?? 'Unknown' }}
                        </figcaption>
                    </figure>
                    <DayRail :now="now" :hour12="prefs.hour12" />
                </footer>
            </div>
        </Transition>
    </main>
</template>

<style scoped>
/* Wallpapers are drawn from theme colours; decorative only. */
.clock {
    isolation: isolate;
}

.wallpaper-layer {
    position: absolute;
    inset: 0;
    z-index: -1;
}

/* Wallpapers crossfade instead of snapping. */
.wallpaper-enter-active,
.wallpaper-leave-active {
    transition: opacity var(--duration-slower) var(--ease-in-out);
}

.wallpaper-enter-from,
.wallpaper-leave-to {
    opacity: 0;
}

/* Clock ↔ timer: a short fade with a small rise. */
.mode-enter-active {
    transition:
        opacity var(--duration-slow) var(--ease-out),
        transform var(--duration-slow) var(--ease-out);
}

.mode-leave-active {
    transition:
        opacity var(--duration-fast) var(--ease-in),
        transform var(--duration-fast) var(--ease-in);
}

.mode-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.mode-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.clock-face {
    display: inline-flex;
    align-items: flex-end;
    gap: 0.06em;
    animation: clock-in var(--duration-slower) var(--ease-out) both;
    font-size: clamp(5rem, 19vw, 17rem);
    letter-spacing: var(--tracking-tight);
    line-height: 0.9;
}

@keyframes clock-in {
    from {
        opacity: 0;
        transform: scale(0.97);
    }
}

/* AM/PM and seconds sit as a small column on the time's baseline. */
.clock-aside {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    padding-bottom: 0.08em;
    font-size: 0.22em;
    line-height: 1.1;
    letter-spacing: var(--tracking-normal);
}

.sweep {
    background: color-mix(in oklab, currentColor 14%, transparent);
}

.sweep-fill {
    background: var(--highlight);
    transform-origin: left;
    transition: transform 1s linear;
}

/* Snap back at the top of the minute instead of sweeping backwards. */
.sweep-fill.is-reset {
    transition: none;
}

.settings-panel {
    width: min(20rem, calc(100vw - 2rem));
}

.switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: calc(1.25rem - 4px);
    height: calc(1.25rem - 4px);
    border-radius: var(--radius-full);
    background: var(--chrome-fg);
    transition: transform var(--duration-fast) var(--ease-out);
}

[aria-checked='true'] .switch::after {
    background: var(--highlight-ink);
    transform: translateX(1rem);
}

.panel-enter-active,
.panel-leave-active {
    transition:
        opacity var(--duration-fast) var(--ease-out),
        transform var(--duration-fast) var(--ease-out);
}

.panel-enter-from,
.panel-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/*
 * Built-in wallpapers use only the chrome roles (always black) and the brand accents, so they look
 * the same whatever the app theme. Paper is the one light wallpaper and gets dark text.
 */
.wallpaper-neon {
    background:
        radial-gradient(60% 50% at 20% 25%, color-mix(in oklab, var(--highlight) 45%, transparent), transparent 70%),
        radial-gradient(50% 45% at 85% 80%, color-mix(in oklab, var(--highlight) 30%, transparent), transparent 70%),
        var(--chrome);
}

.wallpaper-grid {
    background:
        linear-gradient(color-mix(in oklab, var(--chrome-fg) 7%, transparent) 1px, transparent 1px) 0 0 / 48px 48px,
        linear-gradient(90deg, color-mix(in oklab, var(--chrome-fg) 7%, transparent) 1px, transparent 1px) 0 0 / 48px
            48px,
        var(--chrome-hover);
}

.wallpaper-aurora {
    background:
        radial-gradient(120% 70% at 50% 120%, color-mix(in oklab, var(--highlight) 55%, transparent), transparent 60%),
        linear-gradient(160deg, var(--chrome), var(--chrome-hover) 60%, var(--chrome));
}

.wallpaper-tones {
    background:
        radial-gradient(40% 40% at 15% 20%, color-mix(in oklab, var(--tone-violet) 55%, transparent), transparent 70%),
        radial-gradient(40% 40% at 85% 25%, color-mix(in oklab, var(--tone-blue) 55%, transparent), transparent 70%),
        radial-gradient(45% 45% at 70% 85%, color-mix(in oklab, var(--tone-green) 55%, transparent), transparent 70%),
        radial-gradient(35% 35% at 20% 85%, color-mix(in oklab, var(--tone-rose) 45%, transparent), transparent 70%),
        var(--chrome);
}

.wallpaper-midnight {
    background: var(--chrome);
}

.wallpaper-paper {
    background:
        radial-gradient(80% 60% at 50% 0%, var(--surface-inverse-hover), transparent 70%), var(--surface-inverse);
}

/* Photos: a dark scrim keeps the clock readable on any image. */
.wallpaper-photo {
    background-color: var(--chrome);
    background-size: cover;
    background-position: center;
}

.on-photo .clock-face,
.on-photo :deep(.timer-digits) {
    text-shadow: 0 2px 24px rgb(0 0 0 / 0.55);
}

.swatch {
    background-size: cover;
}
</style>
