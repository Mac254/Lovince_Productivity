import { api, errorMessage } from '@focus/api-client';
import type { IconName, MenuItem } from '@focus/ui';
import type { Suggestion, SuggestionStatus } from '@focus/types';
import { formatDate } from '@/lib/dates';

export interface SuggestionHandlers {
    changed: (suggestion: Suggestion, previous: Suggestion) => void;
    removed: (suggestion: Suggestion) => void;
    edit: (suggestion: Suggestion) => void;
}

export function primaryAction(suggestion: Suggestion): { label: string; icon: IconName } {
    const action = suggestion.action;
    if (action.type === 'apply_schedule')
        return { label: `Plan ${formatDate(action.date, { weekday: 'long' })}`, icon: 'calendar-check' };
    if (action.type === 'open') return { label: 'Go there', icon: 'arrow-right' };
    if (action.type === 'achievement') return { label: 'Add to achievements', icon: 'star' };
    return { label: 'Add to today', icon: 'add' };
}

function acceptedMessage(suggestion: Suggestion): string {
    return {
        apply_schedule: 'Day planned',
        open: 'On it',
        achievement: 'Added to your achievements',
        task: 'Added to today',
    }[suggestion.action.type];
}

const markedMessage: Record<SuggestionStatus, string> = {
    new: 'Moved back',
    accepted: 'Accepted',
    done: 'Nice work',
    dismissed: 'Dismissed',
};

export function useSuggestionActions(current: () => Suggestion, handlers: SuggestionHandlers) {
    const toast = useToast();
    const router = useRouter();
    const busy = ref(false);
    const deleting = ref(false);

    async function run<T>(work: () => Promise<T>, success: string, failure: string): Promise<T | null> {
        busy.value = true;
        try {
            const result = await work();
            toast.success(success);
            return result;
        } catch (e) {
            toast.error(errorMessage(e, failure));
            return null;
        } finally {
            busy.value = false;
        }
    }

    async function accept(): Promise<void> {
        const suggestion = current();
        const saved = await run(
            () => api.suggestions.accept(suggestion.id),
            acceptedMessage(suggestion),
            'Could not act on this suggestion.',
        );
        if (!saved) return;
        handlers.changed(saved, suggestion);
        const action = suggestion.action;
        if (action.type === 'open') await router.push({ name: action.route, params: action.params });
    }

    async function mark(status: SuggestionStatus): Promise<void> {
        const suggestion = current();
        const saved = await run(
            () => api.suggestions.update(suggestion.id, { status }),
            markedMessage[status],
            'Could not update this suggestion.',
        );
        if (saved) handlers.changed(saved, suggestion);
    }

    async function remove(): Promise<void> {
        const suggestion = current();
        const removed = await run(
            () => api.suggestions.remove(suggestion.id).then(() => true),
            'Suggestion deleted',
            'Could not delete this suggestion.',
        );
        if (removed) handlers.removed(suggestion);
    }

    function menu(withStatusActions: boolean): MenuItem[] {
        const suggestion = current();
        const open = suggestion.status === 'new';
        return [
            ...(suggestion.source === 'library'
                ? []
                : [{ label: 'Edit', icon: 'edit' as const, onSelect: () => handlers.edit(suggestion) }]),
            ...(withStatusActions && suggestion.status !== 'done'
                ? [{ label: 'Mark done', icon: 'check-circle' as const, onSelect: () => void mark('done') }]
                : []),
            ...(withStatusActions && open
                ? [{ label: 'Dismiss', icon: 'close' as const, onSelect: () => void mark('dismissed') }]
                : []),
            ...(open ? [] : [{ label: 'Move back to new', icon: 'undo' as const, onSelect: () => void mark('new') }]),
            { label: 'Delete', icon: 'trash' as const, destructive: true, onSelect: () => (deleting.value = true) },
        ];
    }

    return { busy, deleting, accept, mark, remove, menu };
}
