<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, useTemplateRef } from 'vue';
import AppIcon, { type IconName } from './AppIcon.vue';

export interface ActionMenuItem {
    key: string;
    label: string;
    icon: IconName;
    danger?: boolean;
    /** Show a divider above this item. */
    separated?: boolean;
}

defineProps<{ label: string; items: ActionMenuItem[] }>();
const emit = defineEmits<{ select: [key: string] }>();

const open = ref(false);
const menuId = useId();
const root = useTemplateRef('root');
const trigger = useTemplateRef('trigger');

function menuItems(): HTMLElement[] {
    return root.value ? [...root.value.querySelectorAll<HTMLElement>('[role="menuitem"]')] : [];
}

async function toggle(): Promise<void> {
    open.value = !open.value;

    if (open.value) {
        document.addEventListener('pointerdown', onOutside);
        await nextTick();
        menuItems()[0]?.focus();
    } else {
        document.removeEventListener('pointerdown', onOutside);
    }
}

function close(returnFocus = true): void {
    open.value = false;
    document.removeEventListener('pointerdown', onOutside);

    if (returnFocus) {
        trigger.value?.focus();
    }
}

function onOutside(event: PointerEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        close(false);
    }
}

function choose(key: string): void {
    close();
    emit('select', key);
}

function onKeydown(event: KeyboardEvent): void {
    const items = menuItems();
    const index = items.indexOf(document.activeElement as HTMLElement);

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        items[(index + 1) % items.length]?.focus();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        items[(index - 1 + items.length) % items.length]?.focus();
    } else if (event.key === 'Tab') {
        close(false);
    }
}

onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>

<template>
    <div ref="root" class="relative inline-flex" @keydown="onKeydown">
        <button
            ref="trigger"
            type="button"
            :aria-label="label"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-controls="open ? menuId : undefined"
            class="flex size-10 cursor-pointer items-center justify-center rounded-[10px] text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            @click="toggle"
        >
            <AppIcon name="more" />
        </button>
        <ul
            v-if="open"
            :id="menuId"
            role="menu"
            :aria-label="label"
            class="absolute end-0 top-11 z-30 w-56 rounded-xl border border-line bg-surface p-1.5 shadow-xl"
        >
            <template v-for="item in items" :key="item.key">
                <li v-if="item.separated" role="separator" class="mx-1 my-1.5 h-px bg-line" />
                <li role="none">
                    <button
                        type="button"
                        role="menuitem"
                        tabindex="-1"
                        :class="[
                            'flex h-10 w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 text-start text-sm hover:bg-surface-muted focus:bg-surface-muted focus:outline-none',
                            item.danger ? 'text-danger' : 'text-fg',
                        ]"
                        @click="choose(item.key)"
                    >
                        <AppIcon :name="item.icon" :size="18" />
                        {{ item.label }}
                    </button>
                </li>
            </template>
        </ul>
    </div>
</template>
