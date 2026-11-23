<script setup lang="ts">
import { useToast } from '../composables/useToast';

const { toasts } = useToast();
</script>

<template>
    <div
        aria-live="polite"
        class="pointer-events-none fixed inset-x-4 bottom-24 z-40 flex flex-col items-center gap-2 lg:bottom-6"
    >
        <p
            v-for="toast in toasts"
            :key="toast.id"
            :role="toast.tone === 'error' ? 'alert' : 'status'"
            class="rounded-[10px] px-4 py-3 text-sm font-medium shadow-lg"
            :class="toast.tone === 'error' ? 'bg-primary text-white' : 'bg-ink text-white'"
        >
            {{ toast.text }}
        </p>
    </div>
</template>
