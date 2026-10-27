<script setup lang="ts">
import { computed } from 'vue';
import { parseMarkdown, type Inline } from '@/lib/markdownLite';

/** The entry rendered from its Markdown subset. Everything is text interpolation — never HTML. */
const props = defineProps<{ source: string }>();
const blocks = computed(() => parseMarkdown(props.source));
const headingTag = (level: number) => `h${level + 2}`;
defineSlots<{ empty?: () => unknown }>();
const isEmpty = computed(() => !blocks.value.length);
const key = (inline: Inline, index: number) => `${index}-${inline.kind}`;
</script>

<template>
    <div class="journal-preview flex flex-col gap-4 text-body leading-relaxed text-fg">
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
                <template v-for="(part, p) in block.inline" :key="key(part, p)">
                    <code v-if="part.kind === 'code'" class="inline-code">{{ part.text }}</code>
                    <strong v-else-if="part.kind === 'strong'" class="font-semibold">{{ part.text }}</strong>
                    <template v-else>{{ part.text }}</template>
                </template>
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
                        <template v-for="(part, p) in item.inline" :key="key(part, p)">
                            <code v-if="part.kind === 'code'" class="inline-code">{{ part.text }}</code>
                            <strong v-else-if="part.kind === 'strong'" class="font-semibold">{{ part.text }}</strong>
                            <template v-else>{{ part.text }}</template>
                        </template>
                    </span>
                </li>
            </component>

            <blockquote
                v-else-if="block.kind === 'quote'"
                class="border-l-2 border-highlight-fg pl-4 whitespace-pre-line text-fg-muted"
            >
                <template v-for="(part, p) in block.inline" :key="key(part, p)">
                    <code v-if="part.kind === 'code'" class="inline-code">{{ part.text }}</code>
                    <strong v-else-if="part.kind === 'strong'" class="font-semibold">{{ part.text }}</strong>
                    <template v-else>{{ part.text }}</template>
                </template>
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
                <template v-for="(part, p) in block.inline" :key="key(part, p)">
                    <code v-if="part.kind === 'code'" class="inline-code">{{ part.text }}</code>
                    <strong v-else-if="part.kind === 'strong'" class="font-semibold">{{ part.text }}</strong>
                    <template v-else>{{ part.text }}</template>
                </template>
            </p>
        </template>
    </div>
</template>

<style scoped>
.inline-code {
    border-radius: var(--radius-sm);
    background: var(--surface-raised);
    padding: 0.1em 0.35em;
    font-family: var(--font-mono);
    font-size: 0.875em;
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
