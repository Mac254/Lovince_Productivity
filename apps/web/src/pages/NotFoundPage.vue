<script setup lang="ts">
import { Button, Heading } from '@focus/ui';
</script>

<template>
    <main class="flex min-h-dvh flex-col items-center justify-center gap-6 px-4 text-center">
        <p class="font-display text-display-lg font-semibold text-highlight-fg">404</p>
        <Heading :level="1" role="heading">This page doesn’t exist</Heading>
        <Button variant="secondary" :to="{ name: 'dashboard' }">Back to dashboard</Button>
    </main>
</template>
