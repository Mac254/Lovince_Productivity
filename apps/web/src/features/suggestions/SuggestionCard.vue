<script setup lang="ts">
import type { Suggestion } from '@focus/types';
import { categoryCover, coverSrcset, coverUrl } from '@/features/covers/covers';
import {
    suggestionCategoryIcons,
    suggestionCategoryLabels,
    suggestionCategoryTones,
    suggestionSourceLabels,
} from '@/lib/labels';
import { primaryAction, useSuggestionActions } from './useSuggestionActions';

const props = defineProps<{ suggestion: Suggestion; fresh?: boolean }>();
const emit = defineEmits<{ edit: [Suggestion]; changed: [Suggestion, Suggestion]; removed: [Suggestion] }>();

const uid = useId();
const { busy, deleting, accept, mark, remove, menu } = useSuggestionActions(() => props.suggestion, {
    changed: (saved, previous) => emit('changed', saved, previous),
    removed: (suggestion) => emit('removed', suggestion),
    edit: (suggestion) => emit('edit', suggestion),
});

const coverKey = computed(() => categoryCover(props.suggestion.category, props.suggestion.id));
const isOpen = computed(() => props.suggestion.status === 'new');
const primary = computed(() => primaryAction(props.suggestion));
const items = computed(() => menu(false));
</script>

<template>
    <Card
        as="article"
        :aria-labelledby="`${uid}-title`"
        padding="none"
        :class="[
            'group h-full gap-4 p-3 pb-4 ring-highlight transition-shadow duration-(--duration-slower) sm:pb-6',
            fresh && 'ring-2',
        ]"
    >
        <div class="aspect-video overflow-hidden rounded-md bg-surface-raised">
            <img
                :src="coverUrl(coverKey)"
                :srcset="coverSrcset(coverKey)"
                sizes="(min-width: 1280px) 33vw, (min-width: 640px) 50vw, 100vw"
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
                <div class="flex items-center gap-2">
                    <Badge v-if="fresh" tone="highlight" icon="lightbulb">Just in</Badge>
                    <DropdownMenu
                        :items="items"
                        :label="`${suggestion.title} actions`"
                        trigger-variant="ghost"
                        size="sm"
                        class="-mt-1 -mr-2"
                    />
                </div>
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
