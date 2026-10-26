<script setup lang="ts" generic="T extends string | number">
import Icon from '../../primitives/Icon/Icon.vue';
import Field from '../Field/Field.vue';
import { controlClasses } from '../Field/controlClasses';

export interface SelectOption<V> {
    value: V | '';
    label: string;
}

defineOptions({ inheritAttrs: false });
defineProps<{
    id: string;
    label: string;
    options: SelectOption<T>[];
    error?: string;
    hint?: string;
    optional?: boolean;
}>();
const model = defineModel<T | '' | null>({ default: '' });
</script>

<template>
    <Field :id="id" v-slot="{ describedBy, invalid }" :label="label" :error="error" :hint="hint" :optional="optional">
        <div class="relative">
            <select
                :id="id"
                v-model="model"
                v-bind="$attrs"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
                :class="[controlClasses(invalid), 'h-(--control-md) appearance-none pr-12']"
            >
                <option v-for="option in options" :key="String(option.value)" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <Icon
                name="chevron-down"
                class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-fg-muted"
            />
        </div>
    </Field>
</template>
