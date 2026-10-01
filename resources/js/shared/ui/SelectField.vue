<script setup lang="ts" generic="Value extends string">
import { useId } from 'vue';
import AppIcon from './AppIcon.vue';

defineProps<{ label: string; options: { value: Value; label: string }[] }>();

/** A native <select>: keyboard, screen-reader and mobile pickers work out of the box. */
const model = defineModel<Value>({ required: true });
const id = useId();
</script>

<template>
    <div
        class="relative flex h-11 items-center gap-2 rounded-[10px] border border-line-strong bg-surface ps-3.5 pe-2 text-sm focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft"
    >
        <label :for="id" class="whitespace-nowrap text-fg-muted">{{ label }}:</label>
        <select
            :id="id"
            v-model="model"
            class="h-full cursor-pointer appearance-none bg-transparent pe-6 font-medium text-fg outline-none"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <AppIcon name="chevronDown" :size="16" class="pointer-events-none absolute end-2.5 text-icon" />
    </div>
</template>
