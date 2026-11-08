<script setup lang="ts">
import { api, errorMessage } from '@focus/api-client';
import type { TabItem } from '@focus/ui';
import type { Book, BookStatus } from '@focus/types';
import { bookStatusLabels } from '@/lib/labels';

const toast = useToast();
const formOpen = ref(false);
const editing = ref<Book | null>(null);
const logOpen = ref(false);
const logging = ref<Book | null>(null);
const deleting = ref<Book | null>(null);
const deleteBusy = ref(false);

const { data, loading, error, run } = useAsync((signal) => api.books.list({}, { signal }));

const order: BookStatus[] = ['reading', 'to_read', 'to_find', 'finished'];
const tab = useTabQuery(order, 'reading');
const groups = computed(() =>
    order.map((status) => ({ status, items: (data.value ?? []).filter((book) => book.status === status) })),
);
const tabs = computed<TabItem<BookStatus>[]>(() =>
    groups.value.map((group) => ({
        value: group.status,
        label: bookStatusLabels[group.status],
        count: group.items.length,
    })),
);
const emptyTitle: Record<BookStatus, string> = {
    reading: 'Not reading anything right now',
    to_read: 'Your shelf is empty',
    to_find: 'Nothing to look for',
    finished: 'No finished books yet',
};
const empty: Record<BookStatus, string> = {
    reading: 'Nothing on the go. Start a book from your shelf, or add one.',
    to_read: 'Books you own but haven’t started will wait here.',
    to_find: 'Books you want to track down — to buy, borrow or download.',
    finished: 'Books you finish show up here with when you finished them.',
};

function add(): void {
    editing.value = null;
    formOpen.value = true;
}

function edit(book: Book): void {
    editing.value = book;
    formOpen.value = true;
}

function log(book: Book): void {
    logging.value = book;
    logOpen.value = true;
}

function saved(book: Book): void {
    tab.value = book.status;
    void run();
}

async function confirmDelete(): Promise<void> {
    if (!deleting.value) return;
    deleteBusy.value = true;
    try {
        await api.books.remove(deleting.value.id);
        toast.success('Book deleted');
        deleting.value = null;
        await run();
    } catch (e) {
        toast.error(errorMessage(e, 'Could not delete the book.'));
    } finally {
        deleteBusy.value = false;
    }
}

onMounted(run);
</script>

<template>
    <div class="flex flex-col gap-6">
        <PageHeader
            title="Books"
            title-id="page-title"
            description="What you’re reading, what’s on the shelf, what to look for and what you’ve finished."
        >
            <template #actions>
                <Button icon-start="add" @click="add">Add book</Button>
            </template>
        </PageHeader>

        <ErrorState v-if="error" :message="error" @retry="run" />
        <div v-else-if="loading && !data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true">
            <Skeleton v-for="n in 3" :key="n" class="h-56" />
        </div>
        <Tabs v-else v-model="tab" label="Books by status" :tabs="tabs">
            <template v-for="group in groups" :key="group.status" #[group.status]>
                <ul v-if="group.items.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="book in group.items" :key="book.id" class="flex">
                        <BookCard :book="book" class="w-full" @edit="edit" @log="log" @delete="deleting = $event" />
                    </li>
                </ul>
                <EmptyState v-else icon="book" :title="emptyTitle[group.status]" :message="empty[group.status]">
                    <Button variant="outline" icon-start="add" @click="add">Add book</Button>
                </EmptyState>
            </template>
        </Tabs>

        <BookFormDialog v-model:open="formOpen" :book="editing" :status="tab" @saved="saved" />
        <ReadingLogDialog v-model:open="logOpen" :book="logging" @saved="run" />
        <ConfirmDialog
            :open="!!deleting"
            title="Delete book?"
            :message="`“${deleting?.title}” and its reading log will be deleted. Its tasks are kept and simply unlinked.`"
            :loading="deleteBusy"
            @update:open="(value: boolean) => !value && (deleting = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>
