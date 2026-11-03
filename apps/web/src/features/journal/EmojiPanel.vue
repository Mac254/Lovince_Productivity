<script setup lang="ts">
import { emojiGroups, emojis, recentEmoji } from './emoji';

const emit = defineEmits<{ pick: [char: string]; close: [] }>();

onMounted(() => document.getElementById('journal-emoji-search')?.focus());
</script>

<template>
    <div class="border-b border-border bg-surface-raised px-4 py-4 sm:px-6" @keydown.esc.stop.prevent="emit('close')">
        <EmojiPicker
            v-model:recent="recentEmoji"
            search-id="journal-emoji-search"
            :emojis="emojis"
            :groups="emojiGroups"
            @select="(char) => emit('pick', char)"
        />
    </div>
</template>
