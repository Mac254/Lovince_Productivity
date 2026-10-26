<script setup lang="ts">
import Field from '../Field/Field.vue';
import { controlClasses } from '../Field/controlClasses';

defineOptions({ inheritAttrs: false });
defineProps<{ id: string; label: string; error?: string; hint?: string; optional?: boolean }>();
const model = defineModel<string | null>({ default: '' });
</script>

<template>
    <Field :id="id" v-slot="{ describedBy, invalid }" :label="label" :error="error" :hint="hint" :optional="optional">
        <textarea
            :id="id"
            v-model="model"
            v-bind="$attrs"
            rows="3"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            :class="[controlClasses(invalid), 'min-h-24 resize-y py-3']"
        />
    </Field>
</template>
