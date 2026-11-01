import type { TimeBlock } from '@focus/types';
import { api } from '@focus/api-client';
import { todayIso } from '@/lib/dates';

const DAILY_KEY = 'focus.reminders.daily';
const CHECK_MS = 30_000;
const BLOCKS_REFRESH_MS = 10 * 60_000;

function notify(title: string, body: string, toast: (message: string) => void): void {
    try {
        if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body, silent: false });
            return;
        }
    } catch {
        return;
    }
    toast(`${title} · ${body}`);
}

function readDaily(): string | null {
    try {
        return localStorage.getItem(DAILY_KEY);
    } catch {
        return null;
    }
}

function writeDaily(date: string): void {
    try {
        localStorage.setItem(DAILY_KEY, date);
    } catch {
        return;
    }
}

export function useReminders(): void {
    const settings = useSettingsStore();
    const auth = useAuthStore();
    const toast = useToast();
    const announce = (message: string) => toast.info(message);

    let blocks: TimeBlock[] = [];
    let blocksDate = '';
    let blocksLoadedAt = 0;
    const announced = new Set<number>();
    let checking = false;
    let interval: number | undefined;

    async function dailyReminder(now: Date, today: string): Promise<void> {
        const hour = settings.values.tasks.daily_reminder;
        if (hour === 'off' || now.getHours() < Number(hour) || readDaily() === today) return;
        writeDaily(today);
        const { data, meta } = await api.tasks.list({ due: 'today', status: 'open', per_page: 5 });
        if (!meta.total) return;
        const titles = data.map((task) => task.title).join(', ');
        notify(`${meta.total} ${meta.total === 1 ? 'task' : 'tasks'} due today`, titles, announce);
    }

    async function blockReminders(now: Date, today: string): Promise<void> {
        const lead = settings.values.planner.reminder;
        if (lead === 'off' || !settings.values.planner.enabled) return;
        if (blocksDate !== today || now.getTime() - blocksLoadedAt > BLOCKS_REFRESH_MS) {
            blocks = await api.timeBlocks.list(today);
            blocksDate = today;
            blocksLoadedAt = now.getTime();
        }
        const minute = now.getHours() * 60 + now.getMinutes();
        for (const block of blocks) {
            const until = block.start_minute - minute;
            if (until < 0 || until > Number(lead) || announced.has(block.id)) continue;
            announced.add(block.id);
            notify(block.title ?? 'Time block', until === 0 ? 'Starting now' : `Starts in ${until} min`, announce);
        }
    }

    async function check(): Promise<void> {
        if (checking || !auth.isAuthenticated || !navigator.onLine) return;
        checking = true;
        const now = new Date();
        const today = todayIso(now);
        try {
            await Promise.allSettled([dailyReminder(now, today), blockReminders(now, today)]);
        } finally {
            checking = false;
        }
    }

    onMounted(() => {
        void check();
        interval = window.setInterval(() => void check(), CHECK_MS);
    });
    onUnmounted(() => window.clearInterval(interval));
}
