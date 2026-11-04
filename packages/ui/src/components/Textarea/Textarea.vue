<script setup lang="ts">
import { LvTextarea } from '@lovince/ui-vue';
import Field from '../Field/Field.vue';
import { controlClasses } from '../Field/controlClasses';
import { useFieldAttrs } from '../../composables/useFieldAttrs';

defineOptions({ inheritAttrs: false });
const { root, control } = useFieldAttrs();
defineProps<{ id: string; label: string; error?: string; hint?: string; optional?: boolean }>();
const model = defineModel<string | null>({ default: '' });
</script>

<template>
    <Field
        v-bind="root()"
        :id="id"
        v-slot="{ describedBy, invalid }"
        :label="label"
        :error="error"
        :hint="hint"
        :optional="optional"
    >
        <LvTextarea
            :model-value="model ?? ''"
            v-bind="control()"
            :aria-describedby="describedBy"
            :class="[controlClasses(invalid), 'min-h-24 resize-y py-3 read-only:bg-canvas']"
            @update:model-value="model = $event"
        />
    </Field>
</template>
