<script setup lang="ts">
import {
    LvEmojiPickerEmpty,
    LvEmojiPickerGroup,
    LvEmojiPickerGroupHeading,
    LvEmojiPickerItem,
    LvEmojiPickerList,
    LvEmojiPickerNav,
    LvEmojiPickerNavItem,
    LvEmojiPickerRoot,
    LvEmojiPickerSearch,
    type EmojiPickerSection,
} from '@lovince/ui-vue';
import Icon from '../../primitives/Icon/Icon.vue';
import type { EmojiGroup, EmojiRecord } from './types';

/**
 * Emoji picker on Lovince UI: typing in the search filters, arrow keys move through the grid while focus
 * stays in the search, Enter picks, Escape clears the search, and the group chips jump to a section.
 */
defineProps<{ emojis: readonly EmojiRecord[]; groups: readonly EmojiGroup[]; searchId?: string }>();
const emit = defineEmits<{ select: [emoji: string] }>();
const recent = defineModel<readonly string[]>('recent', { default: () => [] });

const heading = (section: EmojiPickerSection) =>
    section.kind === 'group' ? section.group.label : section.kind === 'recent' ? 'Recently used' : 'Results';
</script>

<template>
    <LvEmojiPickerRoot
        v-slot="{ sections }"
        v-model:recent="recent"
        :emojis="emojis"
        :groups="groups"
        :columns="8"
        class="flex flex-col gap-3"
        @select="(emoji: string) => emit('select', emoji)"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative sm:w-56 sm:shrink-0">
                <Icon
                    name="search"
                    class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-fg-muted"
                />
                <LvEmojiPickerSearch
                    :id="searchId"
                    aria-label="Search emoji"
                    placeholder="Search emoji"
                    class="h-(--control-md) w-full rounded-full border border-border-control bg-surface pr-4 pl-12 text-body placeholder:text-fg-subtle transition-colors duration-(--duration-instant) hover:border-border-strong focus-visible:border-highlight focus-visible:outline-none"
                />
            </div>
            <LvEmojiPickerNav label="Emoji groups" class="flex flex-wrap gap-2">
                <LvEmojiPickerNavItem
                    v-for="group in groups"
                    :key="group.key"
                    :section="group.key"
                    :label="group.label"
                    tabindex="-1"
                    class="h-(--control-sm) rounded-full border border-border-control px-4 text-label font-semibold text-fg transition-colors duration-(--duration-fast) hover:border-border-strong data-active:border-transparent data-active:bg-surface-inverse data-active:text-fg-inverse"
                >
                    {{ group.label }}
                </LvEmojiPickerNavItem>
            </LvEmojiPickerNav>
        </div>
        <LvEmojiPickerList
            label="Emoji"
            class="flex max-h-64 flex-col gap-3 overflow-y-auto rounded-md focus-visible:outline-2 focus-visible:outline-focus-ring"
        >
            <LvEmojiPickerGroup
                v-for="section in sections"
                :key="section.key"
                v-slot="{ emojis: shown, start }"
                :section="section"
                class="flex flex-col gap-1"
            >
                <LvEmojiPickerGroupHeading class="text-caption font-semibold text-fg-muted">
                    {{ heading(section) }}
                </LvEmojiPickerGroupHeading>
                <div class="grid w-fit grid-cols-8 gap-1">
                    <LvEmojiPickerItem
                        v-for="(emoji, index) in shown"
                        :key="emoji.emoji"
                        :emoji="emoji"
                        :index="start + index"
                        class="emoji inline-flex size-(--control-md) cursor-pointer items-center justify-center rounded-md transition-colors duration-(--duration-instant) data-highlighted:bg-surface"
                    />
                </div>
            </LvEmojiPickerGroup>
        </LvEmojiPickerList>
        <LvEmojiPickerEmpty v-slot="{ search }" class="text-label text-fg-muted">
            No emoji match “{{ search }}”.
        </LvEmojiPickerEmpty>
    </LvEmojiPickerRoot>
</template>

<style scoped>
.emoji {
    font-size: 1.375rem;
    line-height: 1;
}
</style>
