<script setup lang="ts">
import { journalFonts, type JournalFont } from './journalFonts';
import { parseMarkdown } from '@/lib/markdownLite';

/** The entry rendered from its Markdown subset. Everything is text interpolation — never HTML. */
const props = defineProps<{ source: string; font?: JournalFont }>();
const blocks = computed(() => parseMarkdown(props.source));
const headingTag = (level: number) => `h${level + 2}`;
defineSlots<{ empty?: () => unknown }>();
const isEmpty = computed(() => !blocks.value.length);
</script>

<template>
    <div
        :data-journal-font="font"
        :class="[
            'journal-preview flex flex-col gap-4 leading-relaxed text-fg',
            font ? journalFonts[font].size : 'text-body',
        ]"
    >
        <slot v-if="isEmpty" name="empty" />
        <template v-for="(block, b) in blocks" :key="b">
            <component
                :is="headingTag(block.level)"
                v-if="block.kind === 'heading'"
                :class="[
                    'font-display font-semibold',
                    block.level === 1 ? 'text-heading' : block.level === 2 ? 'text-title' : 'text-lead',
                ]"
            >
                <JournalInline :parts="block.inline" />
            </component>

            <component :is="block.ordered ? 'ol' : 'ul'" v-else-if="block.kind === 'list'" class="flex flex-col gap-1">
                <li
                    v-for="(item, i) in block.items"
                    :key="i"
                    :class="['flex items-start gap-3', `depth-${item.depth}`]"
                >
                    <span
                        v-if="item.checked !== null"
                        :class="[
                            'mt-1 inline-flex size-4 shrink-0 items-center justify-center rounded-sm border',
                            item.checked ? 'border-highlight bg-highlight text-highlight-ink' : 'border-border-control',
                        ]"
                        :aria-label="item.checked ? 'Done' : 'Not done'"
                        role="img"
                    >
                        <svg v-if="item.checked" viewBox="0 0 16 16" class="size-3" aria-hidden="true">
                            <path d="M3.5 8.5l3 3 6-7" fill="none" stroke="currentColor" stroke-width="2" />
                        </svg>
                    </span>
                    <span v-else-if="block.ordered" class="min-w-4 text-fg-muted tabular" aria-hidden="true"
                        >{{ i + 1 }}.</span
                    >
                    <span v-else aria-hidden="true" class="mt-2 size-1.5 shrink-0 rounded-full bg-highlight-fg" />
                    <span :class="['min-w-0 break-words', item.checked && 'text-fg-muted line-through']">
                        <JournalInline :parts="item.inline" />
                    </span>
                </li>
            </component>

            <blockquote
                v-else-if="block.kind === 'quote'"
                class="border-l-2 border-highlight-fg pl-4 whitespace-pre-line text-fg-muted"
            >
                <JournalInline :parts="block.inline" />
            </blockquote>

            <figure v-else-if="block.kind === 'code'" class="overflow-hidden rounded-md border border-border">
                <figcaption
                    v-if="block.lang"
                    class="border-b border-border bg-surface-raised px-3 py-1 font-mono text-caption text-fg-muted"
                >
                    {{ block.lang }}
                </figcaption>
                <pre
                    class="overflow-x-auto bg-surface-raised p-3 font-mono text-label leading-relaxed"
                ><code>{{ block.text }}</code></pre>
            </figure>

            <hr v-else-if="block.kind === 'rule'" class="border-border" />

            <p v-else class="break-words whitespace-pre-line">
                <JournalInline :parts="block.inline" />
            </p>
        </template>
    </div>
</template>

<style scoped>
.journal-preview[data-journal-font] {
    font-family: var(--journal-font);
}

.depth-1 {
    padding-left: calc(var(--spacing) * 6);
}

.depth-2 {
    padding-left: calc(var(--spacing) * 12);
}

.depth-3 {
    padding-left: calc(var(--spacing) * 18);
}
</style>
