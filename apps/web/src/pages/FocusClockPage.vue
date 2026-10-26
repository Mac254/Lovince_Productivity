<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { LIMITS, type Wallpaper } from '@focus/types';
import { Icon, IconButton, useToast } from '@focus/ui';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FocusTimer from '@/features/clock/FocusTimer.vue';
import RollingDigits from '@/features/clock/RollingDigits.vue';
import { customWallpaperKey, loadPrefs, savePrefs, wallpapers } from '@/features/clock/wallpapers';
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

const toast = useToast();
const uploads = ref<Wallpaper[]>([]);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const activeUpload = computed(() => uploads.value.find((w) => customWallpaperKey(w.id) === prefs.wallpaper) ?? null);
const wallpaper = computed(() =>
    activeUpload.value
        ? { id: 'photo', label: activeUpload.value.name, ink: 'light' as const }
        : (wallpapers.find((w) => w.id === prefs.wallpaper) ?? wallpapers[0]!),
);
const photoStyle = computed(() =>
    activeUpload.value
        ? { backgroundImage: `linear-gradient(rgb(0 0 0 / 0.35), rgb(0 0 0 / 0.6)), url("${activeUpload.value.url}")` }
        : undefined,
);

async function loadUploads(): Promise<void> {
    try {
        uploads.value = await api.wallpapers.list();
        // A chosen photo that was removed elsewhere falls back to the default wallpaper.
        if (prefs.wallpaper.startsWith('custom:') && !activeUpload.value) prefs.wallpaper = wallpapers[0]!.id;
    } catch {
        // Built-in wallpapers still work without the list.
    }
}

async function upload(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (fileInput.value) fileInput.value.value = '';
    if (!file) return;
    if (file.size > LIMITS.wallpaperKb * 1024) {
        toast.error(`Choose an image under ${Math.round(LIMITS.wallpaperKb / 1024)} MB.`);
        return;
    }
    uploading.value = true;
    try {
        const added = await api.wallpapers.upload(file);
        uploads.value = [added, ...uploads.value];
        prefs.wallpaper = customWallpaperKey(added.id);
        toast.success('Wallpaper added');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not upload that image.'));
    } finally {
        uploading.value = false;
    }
}

async function removeActive(): Promise<void> {
    const current = activeUpload.value;
    if (!current) return;
    try {
        await api.wallpapers.remove(current.id);
        uploads.value = uploads.value.filter((w) => w.id !== current.id);
        prefs.wallpaper = wallpapers[0]!.id;
        toast.success('Wallpaper removed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not remove the wallpaper.'));
    }
}

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
    void loadUploads();
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
            'bg-chrome',
            wallpaper.ink === 'light' ? 'text-chrome-fg' : 'text-fg-inverse',
            activeUpload && 'on-photo',
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
                                : 'text-chrome-fg hover:bg-chrome-hover',
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
                <button
                    v-for="item in uploads"
                    :key="`custom-${item.id}`"
                    type="button"
                    :aria-label="`Wallpaper: ${item.name}`"
                    :aria-pressed="prefs.wallpaper === customWallpaperKey(item.id)"
                    :title="item.name"
                    :class="[
                        'swatch size-(--control-sm) rounded-full border-2 bg-chrome-hover transition-transform duration-(--duration-fast) hover:scale-110',
                        prefs.wallpaper === customWallpaperKey(item.id) ? 'border-highlight' : 'border-chrome-border',
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
                    class="text-chrome-fg hover:bg-chrome-hover"
                    :disabled="uploading"
                    @click="fileInput?.click()"
                />
                <IconButton
                    v-if="activeUpload"
                    icon="trash"
                    :label="`Remove wallpaper ${activeUpload.name}`"
                    variant="ghost"
                    size="sm"
                    class="text-chrome-fg hover:bg-chrome-hover"
                    @click="removeActive"
                />
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    aria-label="Choose a wallpaper image"
                    @change="upload"
                />
                <template v-if="mode === 'clock'">
                    <span class="mx-1 h-6 w-px bg-chrome-border" aria-hidden="true" />
                    <button
                        type="button"
                        :aria-pressed="prefs.hour12"
                        class="h-(--control-sm) rounded-full px-3 text-label font-semibold text-chrome-fg hover:bg-chrome-hover"
                        @click="prefs.hour12 = !prefs.hour12"
                    >
                        {{ prefs.hour12 ? '12h' : '24h' }}
                    </button>
                    <button
                        type="button"
                        :aria-pressed="prefs.seconds"
                        class="h-(--control-sm) rounded-full px-3 text-label font-semibold text-chrome-fg hover:bg-chrome-hover"
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

        <Transition name="mode" mode="out-in">
            <div v-if="mode === 'timer'" key="timer" class="flex flex-1 justify-center px-4 pb-12">
                <FocusTimer />
            </div>

            <div v-else key="clock" class="flex flex-1 flex-col items-center justify-center gap-6 px-4 text-center">
                <p class="text-lead font-semibold opacity-90">{{ date }}</p>
                <time
                    :datetime="now.toISOString()"
                    class="clock-face font-display font-semibold tracking-tight tabular"
                >
                    <RollingDigits :text="time" /><span v-if="prefs.seconds" class="clock-seconds"
                        ><RollingDigits :text="seconds" :label="`${seconds} seconds`"
                    /></span>
                </time>
                <div
                    v-if="next || progress"
                    class="flex max-w-dialog flex-col items-center gap-2 rounded-lg bg-chrome px-6 py-4 text-chrome-fg shadow-floating"
                >
                    <p v-if="progress" class="flex items-center gap-2 text-label font-semibold text-highlight">
                        <Icon name="flame" />{{ progress }}
                    </p>
                    <p v-if="next" class="text-lead font-medium">
                        <span class="text-chrome-fg-muted">Next: </span>
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
                    <blockquote class="text-lead font-medium text-balance">
                        “{{ dashboard.data.quote.text }}”
                    </blockquote>
                    <figcaption class="mt-2 text-label opacity-80">
                        — {{ dashboard.data.quote.author ?? 'Unknown' }}
                    </figcaption>
                </figure>
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
    align-items: flex-start;
    animation: clock-in var(--duration-slower) var(--ease-out) both;
    font-size: clamp(5rem, 18vw, 16rem);
    line-height: 1;
}

@keyframes clock-in {
    from {
        opacity: 0;
        transform: scale(0.97);
    }
}

.clock-seconds {
    margin-left: 0.15em;
    font-size: 0.3em;
    vertical-align: super;
    opacity: 0.7;
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
