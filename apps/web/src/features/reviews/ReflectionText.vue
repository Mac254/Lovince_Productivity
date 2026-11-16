<script setup lang="ts">
import { reflectionBlocks } from './reflection';

/** A reflection as written: paragraphs, with `- ` and `1. ` lines read as lists. */
const props = defineProps<{ text: string | null; empty?: string }>();
const blocks = computed(() => reflectionBlocks(props.text));
</script>

<template>
    <div v-if="blocks.length" class="flex flex-col gap-3">
        <template v-for="(block, index) in blocks" :key="index">
            <Text v-if="block.kind === 'paragraph'" class="break-words">{{ block.text }}</Text>
            <component
                :is="block.kind === 'numbered' ? 'ol' : 'ul'"
                v-else
                :class="['flex flex-col gap-2 pl-6', block.kind === 'numbered' ? 'list-decimal' : 'list-disc']"
            >
                <li v-for="(item, i) in block.items" :key="i" class="break-words marker:text-fg-muted">
                    <Text as="span">{{ item }}</Text>
                </li>
            </component>
        </template>
    </div>
    <Text v-else role="label" tone="muted">{{ empty ?? 'Nothing written yet.' }}</Text>
</template>
