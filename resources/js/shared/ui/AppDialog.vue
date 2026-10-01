<script setup lang="ts">
import { nextTick, onBeforeUnmount, useId, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppIcon, { type IconName } from './AppIcon.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        icon?: IconName;
        tone?: 'primary' | 'danger';
        /** Blocks closing while a request runs. */
        busy?: boolean;
    }>(),
    { tone: 'primary', busy: false },
);

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const titleId = useId();
const panel = useTemplateRef('panel');
let restoreFocus: HTMLElement | null = null;

const FOCUSABLE =
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function focusables(): HTMLElement[] {
    return panel.value ? [...panel.value.querySelectorAll<HTMLElement>(FOCUSABLE)] : [];
}

function close(): void {
    if (!props.busy) {
        emit('close');
    }
}

/** Keep keyboard focus inside the dialog while it is open. */
function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (event.key === 'Tab') {
        const items = focusables();
        const first = items[0];
        const last = items[items.length - 1];

        if (!first || !last) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

watch(
    () => props.open,
    async (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            restoreFocus = document.activeElement as HTMLElement | null;
            await nextTick();
            // Prefer the first form field, else the first button after the close button.
            const target =
                panel.value?.querySelector<HTMLElement>('input:not([disabled]), select, textarea') ?? focusables()[1];
            target?.focus();
        } else {
            restoreFocus?.focus();
        }
    },
);

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            leave-active-class="transition-opacity duration-100"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/45 p-0 sm:items-center sm:p-4"
                @click.self="close"
            >
                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    class="flex max-h-[92dvh] w-full flex-col rounded-t-[20px] bg-surface shadow-2xl sm:max-w-[520px] sm:rounded-[20px]"
                    @keydown="onKeydown"
                >
                    <div class="flex items-start justify-between gap-3 px-6 pt-6">
                        <div class="flex flex-col gap-3.5">
                            <span
                                v-if="icon"
                                :class="[
                                    'flex size-12 items-center justify-center rounded-[14px]',
                                    tone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-primary-soft text-primary',
                                ]"
                            >
                                <AppIcon :name="icon" :size="24" />
                            </span>
                            <h2 :id="titleId" class="text-xl font-bold text-fg">{{ title }}</h2>
                        </div>
                        <button
                            type="button"
                            :aria-label="t('common.close')"
                            :disabled="busy"
                            class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-[10px] text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                            @click="close"
                        >
                            <AppIcon name="close" />
                        </button>
                    </div>
                    <div class="flex flex-col gap-4.5 overflow-y-auto px-6 pt-4 pb-6">
                        <slot />
                    </div>
                    <div v-if="$slots.footer" class="flex flex-wrap gap-2.5 border-t border-line px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
