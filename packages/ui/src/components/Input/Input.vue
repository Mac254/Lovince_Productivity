<script setup lang="ts">
import { LvInput } from '@lovince/ui-vue';
import Field from '../Field/Field.vue';
import { controlClasses, type ControlSurface } from '../Field/controlClasses';

defineOptions({ inheritAttrs: false });
defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    optional?: boolean;
    surface?: ControlSurface;
}>();
const model = defineModel<string | null>({ default: '' });
</script>

<template>
    <Field
        :id="id"
        v-slot="{ describedBy, invalid }"
        :label="label"
        :error="error"
        :hint="hint"
        :optional="optional"
        :surface="surface"
    >
        <LvInput
            :model-value="model ?? ''"
            v-bind="$attrs"
            :aria-describedby="describedBy"
            :class="[controlClasses(invalid, surface), 'h-(--control-md) read-only:bg-canvas']"
            @update:model-value="model = $event"
        />
    </Field>
</template>
