<script setup lang="ts" generic="Value extends string">
defineProps<{ label: string; options: { value: Value; label: string }[] }>();

const model = defineModel<Value>({ required: true });
</script>

<template>
    <div
        role="group"
        :aria-label="label"
        class="grid gap-1 rounded-xl bg-surface-muted p-1"
        :style="{ gridTemplateColumns: `repeat(${options.length}, minmax(0, 1fr))` }"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="model === option.value"
            :class="[
                'h-10 cursor-pointer rounded-[9px] text-sm transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                model === option.value
                    ? 'bg-segment-active font-semibold text-fg shadow-sm'
                    : 'font-medium text-fg-muted hover:text-fg',
            ]"
            @click="model = option.value"
        >
            {{ option.label }}
        </button>
    </div>
</template>
