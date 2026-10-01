<script setup lang="ts">
import AppIcon from './AppIcon.vue';

withDefaults(defineProps<{ message: string; tone?: 'danger' | 'success' }>(), { tone: 'danger' });
</script>

<template>
    <div
        :role="tone === 'danger' ? 'alert' : 'status'"
        :class="[
            'flex gap-2.5 rounded-xl border px-3.5 py-3 text-sm leading-relaxed',
            tone === 'danger' ? 'border-danger-line bg-danger-soft text-danger' : 'border-line bg-primary-soft text-fg',
        ]"
    >
        <AppIcon :name="tone === 'danger' ? 'alert' : 'check'" class="mt-0.5" />
        <span>{{ message }}</span>
    </div>
</template>
