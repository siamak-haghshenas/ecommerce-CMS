<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = withDefaults(defineProps<{ label: string; delay?: number }>(), { delay: 300 });

/** Updated after typing pauses, so each keystroke does not trigger a request. */
const model = defineModel<string>({ required: true });

const draft = ref(model.value);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(draft, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => (model.value = value.trim()), props.delay);
});

watch(model, (value) => {
    if (value !== draft.value.trim()) {
        draft.value = value;
    }
});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div
        class="flex h-11 w-full items-center gap-2.5 rounded-[10px] border border-line-strong bg-surface px-3.5 text-icon focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft sm:w-80"
    >
        <AppIcon name="search" :size="18" />
        <input
            v-model="draft"
            type="search"
            :aria-label="label"
            :placeholder="label"
            class="h-full min-w-0 grow bg-transparent text-sm text-fg outline-none placeholder:text-fg-muted"
        />
    </div>
</template>
