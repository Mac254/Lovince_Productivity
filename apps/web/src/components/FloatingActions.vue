<script setup lang="ts">
const route = useRoute();
</script>

<template>
    <nav aria-label="Quick actions" class="fixed right-4 bottom-6 z-(--z-sticky) flex flex-col gap-3 sm:right-6">
        <IconButton
            icon="calendar-clock"
            label="New schedule"
            variant="secondary"
            size="lg"
            motion="nudge"
            class="rounded-full shadow-floating"
            :to="{ name: 'schedules', query: { new: '1' } }"
        />
        <IconButton
            icon="journal"
            label="Write in today's journal"
            variant="primary"
            size="lg"
            motion="wiggle"
            class="rounded-full shadow-floating"
            :current="route.name === 'journal'"
            :to="{ name: 'journal' }"
        />
    </nav>
</template>
