import type { Goal, TaskInput, TaskPriority, TaskRecurrence, TaskType } from '@focus/types';
import { formatMinutes, parseDuration, todayIso } from './dates';
import { taskPriorityLabels, taskRecurrenceLabels } from './labels';

export interface QuickAddContext {
    today?: Date;
    types?: Pick<TaskType, 'id' | 'name'>[];
    goals?: Pick<Goal, 'id' | 'title' | 'status'>[];
}

export interface QuickAdd {
    title: string;
    input: TaskInput;
    parts: string[];
}

const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
const PRIORITIES: Record<string, TaskPriority> = {
    low: 'low',
    med: 'medium',
    medium: 'medium',
    high: 'high',
    urgent: 'urgent',
};
const RECURRENCES: Record<string, TaskRecurrence> = {
    daily: 'daily',
    day: 'daily',
    weekdays: 'weekdays',
    weekday: 'weekdays',
    weekly: 'weekly',
    week: 'weekly',
    monthly: 'monthly',
    month: 'monthly',
};
const CATEGORY_TAGS: Record<string, 'development' | 'integration'> = {
    dev: 'development',
    development: 'development',
    integration: 'integration',
};

const addDays = (date: Date, days: number) => {
    const next = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    next.setDate(next.getDate() + days);
    return next;
};
const slug = (text: string) => text.toLowerCase().trim().replace(/\s+/g, '-');

function weekday(word: string): number {
    if (word.length < 3) return -1;
    return WEEKDAYS.findIndex((day) => day.startsWith(word));
}

function readDate(words: string[], i: number, today: Date): { date: Date; used: number } | null {
    const word = words[i]!;
    const next = words[i + 1];
    if (word === 'today' || word === 'tod') return { date: today, used: 1 };
    if (word === 'tomorrow' || word === 'tmr' || word === 'tmrw') return { date: addDays(today, 1), used: 1 };
    if (/^\d{4}-\d{2}-\d{2}$/.test(word)) {
        const [y, m, d] = word.split('-').map(Number);
        const date = new Date(y!, m! - 1, d!);
        return date.getMonth() === m! - 1 ? { date, used: 1 } : null;
    }
    if (word === 'in' && next && words[i + 2]) {
        const count = Number(next);
        const unit = words[i + 2]!;
        if (Number.isInteger(count) && count > 0 && count <= 365) {
            if (/^days?$/.test(unit)) return { date: addDays(today, count), used: 3 };
            if (/^weeks?$/.test(unit)) return { date: addDays(today, count * 7), used: 3 };
        }
    }
    if (word === 'next' && next === 'week') return { date: addDays(today, (8 - today.getDay()) % 7 || 7), used: 2 };
    const offset = word === 'next' && next ? 1 : 0;
    const day = weekday(words[i + offset]!);
    if (day >= 0) return { date: addDays(today, (day - today.getDay() + 7) % 7 || 7), used: 1 + offset };
    return null;
}

export function parseQuickAdd(text: string, context: QuickAddContext = {}): QuickAdd {
    const today = context.today ?? new Date();
    const raw = text.trim().split(/\s+/).filter(Boolean);
    const words = raw.map((word) => word.toLowerCase());
    const input: TaskInput = {};
    const parts: string[] = [];
    const title: string[] = [];

    for (let i = 0; i < raw.length;) {
        const word = words[i]!;
        const lead = (word === 'due' || word === 'by' || word === 'on') && i + 1 < raw.length ? 1 : 0;
        const date = input.due_date ? null : readDate(words, i + lead, today);
        if (date) {
            input.due_date = todayIso(date.date);
            parts.push(
                `Due ${date.date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })}`,
            );
            i += lead + date.used;
            continue;
        }
        if (!input.recurrence) {
            const every = word === 'every' && words[i + 1] ? RECURRENCES[words[i + 1]!] : undefined;
            const plain = ['daily', 'weekly', 'monthly', 'weekdays'].includes(word) ? RECURRENCES[word] : undefined;
            const recurrence = every ?? plain;
            if (recurrence) {
                input.recurrence = recurrence;
                parts.push(taskRecurrenceLabels[recurrence]);
                i += every ? 2 : 1;
                continue;
            }
        }
        if (word.startsWith('!') && PRIORITIES[word.slice(1)]) {
            input.priority = PRIORITIES[word.slice(1)];
            parts.push(`${taskPriorityLabels[input.priority!]} priority`);
            i++;
            continue;
        }
        if (word.startsWith('~') && parseDuration(word.slice(1))) {
            input.estimate_minutes = parseDuration(word.slice(1));
            parts.push(`About ${formatMinutes(input.estimate_minutes)}`);
            i++;
            continue;
        }
        if (word === '*') {
            input.is_highlighted = true;
            parts.push('Starred');
            i++;
            continue;
        }
        if (word.startsWith('#') && word.length > 1) {
            const tag = word.slice(1);
            const type = context.types?.find((entry) => slug(entry.name) === tag);
            if (type) {
                input.task_type_id = type.id;
                input.category = 'general';
                parts.push(type.name);
                i++;
                continue;
            }
            if (CATEGORY_TAGS[tag]) {
                input.category = CATEGORY_TAGS[tag];
                input.task_type_id = null;
                parts.push(input.category === 'development' ? 'Development' : 'Integration');
                i++;
                continue;
            }
        }
        if (word.startsWith('+') && word.length > 1) {
            const goal = context.goals?.find(
                (entry) =>
                    entry.status !== 'completed' &&
                    entry.status !== 'cancelled' &&
                    slug(entry.title).startsWith(word.slice(1)),
            );
            if (goal) {
                input.goal_id = goal.id;
                parts.push(`Goal: ${goal.title}`);
                i++;
                continue;
            }
        }
        title.push(raw[i]!);
        i++;
    }

    if (input.recurrence && !input.due_date) input.due_date = todayIso(today);
    input.title = title.join(' ');
    return { title: input.title, input, parts };
}
