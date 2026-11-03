<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { Achievement } from '@focus/types';
import type { MenuItem } from '@focus/ui';
import { formatDate } from '@/lib/dates';
import { suggestionCategoryIcons, suggestionCategoryLabels, suggestionCategoryTones } from '@/lib/labels';
import { groupByMonth } from './months';

const props = defineProps<{ achievements: Achievement[]; showGoal?: boolean }>();
const emit = defineEmits<{ edit: [achievement: Achievement]; changed: [] }>();

const toast = useToast();
const deleting = ref<Achievement | null>(null);
const deleteBusy = ref(false);
const months = computed(() => groupByMonth(props.achievements));

function menu(achievement: Achievement): MenuItem[] {
    return [
        { label: 'Edit', icon: 'edit', onSelect: () => emit('edit', achievement) },
        { label: 'Delete', icon: 'trash', destructive: true, onSelect: () => (deleting.value = achievement) },
    ];
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deleteBusy.value = true;
    try {
        await api.achievements.remove(deleting.value.id);
        toast.success('Achievement deleted');
        deleting.value = null;
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the achievement.'));
    } finally {
        deleteBusy.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-8">
        <section v-for="month in months" :key="month.key" :aria-label="month.label" class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-display text-lead font-semibold">{{ month.label }}</h2>
                <Text role="label" tone="muted" tabular>
                    {{ month.items.length }} {{ month.items.length === 1 ? 'win' : 'wins' }}
                </Text>
            </div>
            <Timeline>
                <TimelineItem
                    v-for="(achievement, index) in month.items"
                    :key="achievement.id"
                    :index="index"
                    :icon="achievement.category ? suggestionCategoryIcons[achievement.category] : 'star'"
                    :tone="achievement.category ? suggestionCategoryTones[achievement.category] : 'highlight'"
                    :datetime="achievement.achieved_on"
                    :last="index === month.items.length - 1"
                >
                    <template #time>
                        {{ formatDate(achievement.achieved_on, { weekday: 'short', day: 'numeric', month: 'short' }) }}
                    </template>
                    <Card as="article" class="gap-2">
                        <div class="flex items-start gap-3">
                            <p class="min-w-0 flex-1 font-display text-lead font-medium break-words">
                                {{ achievement.title }}
                            </p>
                            <DropdownMenu
                                :items="menu(achievement)"
                                :label="`Actions for ${achievement.title}`"
                                size="sm"
                                trigger-variant="ghost"
                            />
                        </div>
                        <Text
                            v-if="achievement.description"
                            tone="muted"
                            class="break-words whitespace-pre-line"
                        >
                            {{ achievement.description }}
                        </Text>
                        <p
                            v-if="achievement.category || (showGoal && achievement.goal) || achievement.task"
                            class="flex flex-wrap items-center gap-2 text-caption text-fg-muted"
                        >
                            <Badge v-if="achievement.category" :tone="suggestionCategoryTones[achievement.category]">
                                {{ suggestionCategoryLabels[achievement.category] }}
                            </Badge>
                            <RouterLink
                                v-if="showGoal && achievement.goal"
                                :to="{ name: 'goal-detail', params: { id: achievement.goal.id } }"
                                class="inline-flex items-center gap-1 truncate font-semibold text-fg underline-offset-4 hover:underline"
                            >
                                <ToneDot v-if="achievement.goal.tone" :tone="achievement.goal.tone" />
                                {{ achievement.goal.title }}
                            </RouterLink>
                            <RouterLink
                                v-if="achievement.task"
                                :to="{ name: 'task-detail', params: { id: achievement.task.id } }"
                                class="inline-flex items-center gap-1 truncate underline-offset-4 hover:underline"
                            >
                                <Icon name="tasks" />
                                {{ achievement.task.title }}
                            </RouterLink>
                        </p>
                    </Card>
                </TimelineItem>
            </Timeline>
        </section>

        <ConfirmDialog
            :open="!!deleting"
            title="Delete achievement?"
            :message="`“${deleting?.title}” will be removed from your achievements.`"
            :loading="deleteBusy"
            @update:open="(value: boolean) => !value && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>
