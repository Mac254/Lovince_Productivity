<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import { ROADMAP_STAGES, type RoadmapItem, type RoadmapStage } from '@focus/types';
import { roadmapStageLabels, roadmapStageTones } from '@/lib/labels';

const route = useRoute();
const toast = useToast();
const settings = useSettingsStore();
const formOpen = ref(false);
const editing = ref<RoadmapItem | null>(null);
const newStage = ref<RoadmapStage>('next');
const deleting = ref<RoadmapItem | null>(null);
const deleteBusy = ref(false);

const { data, loading, error, run } = useAsync((signal) => api.roadmap.list({ signal }));

const hints: Record<RoadmapStage, string> = {
    now: 'Being built right now',
    next: 'Up after what’s in progress',
    later: 'Good ideas that can wait',
    shipped: 'Done and in the app',
};
const lanes = computed(() =>
    ROADMAP_STAGES.filter((stage) => stage !== 'shipped' || settings.values.roadmap.show_shipped).map((stage) => {
        const items = (data.value ?? []).filter((item) => item.stage === stage);
        if (stage === 'shipped')
            items.sort((a, b) => (b.shipped_on ?? '').localeCompare(a.shipped_on ?? '') || b.id - a.id);
        return { stage, items };
    }),
);
const highlighted = computed(() => Number(route.query.item) || null);

function add(stage: RoadmapStage): void {
    editing.value = null;
    newStage.value = stage;
    formOpen.value = true;
}

function edit(item: RoadmapItem): void {
    editing.value = item;
    formOpen.value = true;
}

async function reorder(stage: RoadmapStage, ids: number[], message?: string): Promise<void> {
    try {
        data.value = await api.roadmap.reorder(stage, ids);
        if (message) toast.success(message);
    } catch (e) {
        toast.error(errorMessage(e, 'Could not move the item.'));
    }
}

function move(item: RoadmapItem, offset: -1 | 1): void {
    const ids = lanes.value.find((lane) => lane.stage === item.stage)!.items.map((i) => i.id);
    const from = ids.indexOf(item.id);
    const to = from + offset;
    if (to < 0 || to >= ids.length) return;
    [ids[from], ids[to]] = [ids[to]!, ids[from]!];
    void reorder(item.stage, ids);
}

const dragging = ref<RoadmapItem | null>(null);
const target = ref<{ stage: RoadmapStage; index: number } | null>(null);

function dragStart(event: DragEvent, item: RoadmapItem): void {
    dragging.value = item;
    if (!event.dataTransfer) return;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(item.id));
}

function dragEnd(): void {
    dragging.value = null;
    target.value = null;
}

function dragOverItem(event: DragEvent, stage: RoadmapStage, index: number): void {
    if (!dragging.value) return;
    const box = (event.currentTarget as HTMLElement).getBoundingClientRect();
    target.value = { stage, index: event.clientY > box.top + box.height / 2 ? index + 1 : index };
}

function dragOverLane(stage: RoadmapStage, count: number): void {
    if (!dragging.value || target.value?.stage === stage) return;
    target.value = { stage, index: count };
}

function drop(): void {
    const item = dragging.value;
    const to = target.value;
    dragEnd();
    if (!item || !to) return;
    const lane = lanes.value.find((l) => l.stage === to.stage)!.items.map((i) => i.id);
    const from = lane.indexOf(item.id);
    let index = to.index;
    if (from !== -1) {
        if (from === index || from + 1 === index) return;
        lane.splice(from, 1);
        if (from < index) index--;
    }
    lane.splice(index, 0, item.id);
    if (data.value)
        data.value = data.value.map((i) =>
            i.id === item.id || i.stage === to.stage ? { ...i, stage: to.stage, position: lane.indexOf(i.id) } : i,
        );
    const moved = item.stage !== to.stage;
    const message = !moved
        ? undefined
        : to.stage === 'shipped'
          ? `“${item.title}” shipped`
          : `Moved to ${roadmapStageLabels[to.stage]}`;
    void reorder(to.stage, lane, message);
}

async function changeStage(item: RoadmapItem, stage: RoadmapStage): Promise<void> {
    try {
        await api.roadmap.update(item.id, { stage });
        toast.success(stage === 'shipped' ? `“${item.title}” shipped` : `Moved to ${roadmapStageLabels[stage]}`);
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not move the item.'));
    }
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deleteBusy.value = true;
    try {
        await api.roadmap.remove(deleting.value.id);
        toast.success('Removed from the roadmap');
        deleting.value = null;
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the item.'));
    } finally {
        deleteBusy.value = false;
    }
}

watch(data, async () => {
    if (!highlighted.value) return;
    await nextTick();
    document.getElementById(`roadmap-item-${highlighted.value}`)?.scrollIntoView({ block: 'center' });
});
onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Roadmap"
            title-id="page-title"
            description="Where LovinceTracker is heading: what you’re building now, what’s next, what can wait and what has shipped. Drag cards to reorder them or move them between lanes."
        >
            <template #actions>
                <Button icon-start="add" @click="add('next')">Add item</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-busy="true">
            <Skeleton v-for="n in 4" :key="n" class="h-64" />
        </div>
        <div
            v-else
            :class="['grid items-start gap-6 md:grid-cols-2', lanes.length === 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-3']"
        >
            <section
                v-for="lane in lanes"
                :key="lane.stage"
                :aria-labelledby="`lane-${lane.stage}`"
                :class="[
                    'flex min-h-48 flex-col gap-3 rounded-lg',
                    dragging &&
                        target?.stage === lane.stage &&
                        'outline-2 outline-offset-4 outline-border-control outline-dashed',
                ]"
                @dragover.prevent="dragOverLane(lane.stage, lane.items.length)"
                @drop.prevent="drop"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex flex-col">
                        <h2
                            :id="`lane-${lane.stage}`"
                            class="flex items-center gap-2 font-display text-lead font-semibold tracking-tight"
                        >
                            {{ roadmapStageLabels[lane.stage] }}
                            <Badge :tone="roadmapStageTones[lane.stage]">{{ lane.items.length }}</Badge>
                        </h2>
                        <Text role="caption" tone="muted">{{ hints[lane.stage] }}</Text>
                    </div>
                    <IconButton
                        icon="add"
                        variant="ghost"
                        size="sm"
                        :label="`Add to ${roadmapStageLabels[lane.stage]}`"
                        @click="add(lane.stage)"
                    />
                </div>
                <ul v-if="lane.items.length" class="flex flex-col gap-3">
                    <li
                        v-for="(item, index) in lane.items"
                        :key="item.id"
                        draggable="true"
                        :class="[
                            'relative cursor-grab active:cursor-grabbing',
                            dragging?.id === item.id && 'opacity-50',
                        ]"
                        @dragstart="dragStart($event, item)"
                        @dragend="dragEnd"
                        @dragover.prevent.stop="dragOverItem($event, lane.stage, index)"
                    >
                        <span
                            v-if="dragging && target?.stage === lane.stage && target.index === index"
                            class="absolute inset-x-0 -top-2 h-0.5 rounded-full bg-highlight"
                            aria-hidden="true"
                        />
                        <span
                            v-if="
                                dragging &&
                                target?.stage === lane.stage &&
                                target.index === index + 1 &&
                                index === lane.items.length - 1
                            "
                            class="absolute inset-x-0 -bottom-2 h-0.5 rounded-full bg-highlight"
                            aria-hidden="true"
                        />
                        <RoadmapCard
                            :item="item"
                            :first="index === 0"
                            :last="index === lane.items.length - 1"
                            :highlighted="item.id === highlighted"
                            @edit="edit"
                            @delete="deleting = $event"
                            @move="move"
                            @stage="changeStage"
                        />
                    </li>
                </ul>
                <Text
                    v-else
                    role="label"
                    tone="muted"
                    class="rounded-lg border border-dashed border-border-control px-4 py-6 text-center"
                    >{{ dragging ? 'Drop here' : 'Nothing here yet.' }}</Text
                >
            </section>
        </div>

        <RoadmapItemDialog v-model:open="formOpen" :item="editing" :stage="newStage" @saved="run" />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete roadmap item?"
            :message="`“${deleting?.title}” will be removed from the roadmap.`"
            :loading="deleteBusy"
            @update:open="(value: boolean) => !value && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>
