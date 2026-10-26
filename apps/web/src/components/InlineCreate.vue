<script setup lang="ts">
import { errorMessage, isApiError } from '@focus/api-client';
import { Button, Input } from '@focus/ui';
import { nextTick, ref } from 'vue';

/**
 * A "+ New …" link that opens a one-line form under a picker. `create` returns the
 * new record's id, which is emitted so the picker can select it.
 */
const props = defineProps<{ id: string; noun: string; maxlength: number; create: (name: string) => Promise<number> }>();
const emit = defineEmits<{ created: [id: number] }>();

const open = ref(false);
const name = ref('');
const error = ref('');
const busy = ref(false);

async function show(): Promise<void> {
    open.value = true;
    await nextTick();
    document.getElementById(props.id)?.focus();
}

async function submit(): Promise<void> {
    if (!name.value.trim()) {
        error.value = `Give the ${props.noun} a name.`;
        return;
    }
    busy.value = true;
    error.value = '';
    try {
        emit('created', await props.create(name.value.trim()));
        name.value = '';
        open.value = false;
    } catch (e) {
        error.value =
            isApiError(e) && e.isValidation
                ? (Object.values(e.fieldErrors())[0] ?? '')
                : errorMessage(e, `Could not create the ${props.noun}.`);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <button
            v-if="!open"
            type="button"
            class="text-label font-semibold text-accent-fg underline-offset-4 hover:underline"
            @click="show"
        >
            + New {{ noun }}
        </button>
        <div
            v-else
            class="flex flex-col gap-2"
            @keydown.enter.prevent="submit"
            @keydown.esc.stop.prevent="open = false"
        >
            <div class="min-w-0">
                <Input
                    :id="id"
                    v-model="name"
                    :label="`New ${noun}`"
                    :maxlength="maxlength"
                    autocomplete="off"
                    :error="error"
                />
            </div>
            <slot />
            <div class="flex gap-2">
                <Button :loading="busy" @click="submit">Add</Button>
                <Button variant="ghost" @click="open = false">Cancel</Button>
            </div>
        </div>
    </div>
</template>
