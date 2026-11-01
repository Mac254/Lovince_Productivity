<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Suggestion, SuggestionStatus } from '@focus/types';
import { categoryCover, coverUrl } from '@/features/covers/covers';
import { formatDate } from '@/lib/dates';
import {
    suggestionCategoryIcons,
    suggestionCategoryLabels,
    suggestionCategoryTones,
    suggestionSourceLabels,
} from '@/lib/labels';

const props = defineProps<{ suggestion: Suggestion }>();
const emit = defineEmits<{ edit: []; changed: [] }>();

const toast = useToast();
const router = useRouter();
const uid = useId();
const busy = ref(false);
const deleting = ref(false);

const cover = computed(() => coverUrl(categoryCover(props.suggestion.category, props.suggestion.id)));
const isOpen = computed(() => props.suggestion.status === 'new');
const primary = computed(() => {
    const action = props.suggestion.action;
    if (action.type === 'apply_schedule')
        return { label: `Plan ${formatDate(action.date, { weekday: 'long' })}`, icon: 'calendar-check' as const };
    if (action.type === 'open') return { label: 'Go there', icon: 'arrow-right' as const };
    return { label: 'Add to today', icon: 'add' as const };
});
const menu = computed(() => [
    ...(props.suggestion.source === 'library'
        ? []
        : [{ label: 'Edit', icon: 'edit' as const, onSelect: () => emit('edit') }]),
    ...(isOpen.value ? [] : [{ label: 'Move back to new', icon: 'undo' as const, onSelect: () => void mark('new') }]),
    { label: 'Delete', icon: 'trash' as const, destructive: true, onSelect: () => (deleting.value = true) },
]);

async function run(work: () => Promise<unknown>, success: string, failure: string): Promise<void> {
    busy.value = true;
    try {
        await work();
        toast.success(success);
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, failure));
    } finally {
        busy.value = false;
    }
}

async function accept(): Promise<void> {
    const action = props.suggestion.action;
    await run(
        () => api.suggestions.accept(props.suggestion.id),
        acceptedMessage(),
        'Could not act on this suggestion.',
    );
    if (action.type === 'open') await router.push({ name: action.route });
}

function acceptedMessage(): string {
    if (props.suggestion.action.type === 'apply_schedule') return 'Day planned';
    if (props.suggestion.action.type === 'open') return 'On it';
    return 'Added to today';
}

const mark = (status: SuggestionStatus) =>
    run(
        () => api.suggestions.update(props.suggestion.id, { status }),
        status === 'done' ? 'Nice work' : status === 'dismissed' ? 'Dismissed' : 'Moved back',
        'Could not update this suggestion.',
    );

const remove = () =>
    run(() => api.suggestions.remove(props.suggestion.id), 'Suggestion deleted', 'Could not delete this suggestion.');
</script>

<template>
    <Card as="article" :aria-labelledby="`${uid}-title`" padding="none" class="group h-full gap-4 p-3 pb-4 sm:pb-6">
        <div class="aspect-video overflow-hidden rounded-md bg-surface-raised">
            <img
                :src="cover"
                alt=""
                loading="lazy"
                :class="[
                    'size-full object-cover transition-transform duration-(--duration-slow) ease-out group-hover:scale-105',
                    !isOpen && 'grayscale',
                ]"
            />
        </div>
        <div class="flex flex-1 flex-col gap-4 px-1 sm:px-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        :tone="suggestionCategoryTones[suggestion.category]"
                        :icon="suggestionCategoryIcons[suggestion.category]"
                        >{{ suggestionCategoryLabels[suggestion.category] }}</Badge
                    >
                    <Text role="caption" tone="muted">{{ suggestionSourceLabels[suggestion.source] }}</Text>
                </div>
                <DropdownMenu
                    :items="menu"
                    :label="`${suggestion.title} actions`"
                    trigger-variant="ghost"
                    size="sm"
                    class="-mt-1 -mr-2"
                />
            </div>

            <div class="flex flex-1 flex-col gap-2">
                <Heading :id="`${uid}-title`" :level="3" role="title">{{ suggestion.title }}</Heading>
                <Text v-if="suggestion.body" role="label" tone="muted" class="leading-relaxed">{{
                    suggestion.body
                }}</Text>
            </div>

            <RouterLink
                v-if="suggestion.task"
                :to="{ name: 'tasks' }"
                class="inline-flex items-center gap-2 self-start text-label font-medium text-fg-muted hover:text-fg"
            >
                <Icon name="tasks" size="sm" />{{ suggestion.task.title }}
            </RouterLink>

            <div v-if="isOpen" class="flex items-center gap-2 border-t border-border pt-4">
                <Button size="sm" :icon-start="primary.icon" :loading="busy" class="mr-auto" @click="accept">{{
                    primary.label
                }}</Button>
                <IconButton
                    icon="check-circle"
                    label="Mark done"
                    variant="ghost"
                    size="sm"
                    :disabled="busy"
                    @click="mark('done')"
                />
                <IconButton
                    icon="close"
                    label="Dismiss"
                    variant="ghost"
                    size="sm"
                    :disabled="busy"
                    @click="mark('dismissed')"
                />
            </div>
            <div
                v-else-if="suggestion.status === 'accepted'"
                class="flex items-center gap-2 border-t border-border pt-4"
            >
                <Button size="sm" variant="outline" icon-start="check-circle" :loading="busy" @click="mark('done')"
                    >Mark done</Button
                >
            </div>
        </div>

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete suggestion?"
            :message="`“${suggestion.title}” will be removed. Ideas from the library can come back later.`"
            @confirm="remove"
        />
    </Card>
</template>
