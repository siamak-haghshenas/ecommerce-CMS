<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useToast } from '../composables/useToast';
import AppIcon from './AppIcon.vue';

const { t } = useI18n();
const { toasts, dismiss } = useToast();
</script>

<template>
    <div
        aria-live="polite"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:start-auto sm:end-6 sm:items-end"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            leave-active-class="transition duration-150 ease-in"
            enter-from-class="translate-y-2 opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :role="toast.tone === 'danger' ? 'alert' : 'status'"
                class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-xl border border-line bg-surface px-4 py-3 text-sm text-fg shadow-xl"
            >
                <AppIcon
                    :name="toast.tone === 'danger' ? 'alert' : 'check'"
                    :class="toast.tone === 'danger' ? 'text-danger' : 'text-primary'"
                />
                <span class="grow">{{ toast.message }}</span>
                <button
                    type="button"
                    :aria-label="t('common.close')"
                    class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted"
                    @click="dismiss(toast.id)"
                >
                    <AppIcon name="close" :size="16" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
