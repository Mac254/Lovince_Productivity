<script setup lang="ts">
import Field from '../Field/Field.vue';
import { controlClasses } from '../Field/controlClasses';

defineOptions({ inheritAttrs: false });
defineProps<{ id: string; label: string; error?: string; hint?: string; optional?: boolean }>();
const model = defineModel<string | null>({ default: '' });
</script>

<template>
    <Field :id="id" v-slot="{ describedBy, invalid }" :label="label" :error="error" :hint="hint" :optional="optional">
        <input
            :id="id"
            v-model="model"
            v-bind="$attrs"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            :class="[controlClasses(invalid), 'h-(--control-md)']"
        />
    </Field>
</template>
