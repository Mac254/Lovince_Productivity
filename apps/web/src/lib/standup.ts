import type { JournalContext } from '@focus/types';
import { formatMinutes } from './dates';

/**
 * A stand-up style draft from what happened on a day: done (completed tasks and logged work),
 * learned (left for you), blockers and next. Empty sections keep a blank bullet to fill in.
 */
export function standupDraft(context: JournalContext | null): string {
    const done = [
        ...(context?.completed ?? []).map((task) => `Completed ${task.title}`),
        ...(context?.work_log ?? []).map((entry) => {
            const time = entry.minutes ? ` (${formatMinutes(entry.minutes)})` : '';
            return entry.task ? `${entry.task.title}: ${entry.body}${time}` : `${entry.body}${time}`;
        }),
    ];
    const blockers = (context?.blockers ?? []).map(
        (blocker) =>
            `${blocker.description}${blocker.task ? ` (${blocker.task.title})` : ''}${blocker.resolved ? ' — resolved' : ''}`,
    );
    const next = (context?.still_open ?? []).map((task) => task.title);

    const section = (title: string, items: string[]) =>
        `## ${title}\n${(items.length ? items : ['']).map((item) => `- ${item}`).join('\n')}`;

    return [section('Done', done), section('Learned', []), section('Blockers', blockers), section('Next', next)].join(
        '\n\n',
    );
}
