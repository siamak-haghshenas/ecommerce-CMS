<script setup lang="ts">
import { computed, onMounted, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { words } from '../../seo/analysis';
import { tagsApi } from '../api';
import type { Tag } from '../types';

/**
 * Tags typed freely: Enter or comma adds one, existing tags are suggested while typing.
 * New tags are created when the product is saved.
 */
const tags = defineModel<string[]>({ required: true });

const { t } = useI18n();
const id = useId();
const text = ref('');
const known = ref<Tag[]>([]);
const open = ref(false);

onMounted(async () => {
    try {
        known.value = await tagsApi.list();
    } catch {
        // Suggestions are a convenience; typing still works without them.
    }
});

/** Matching ignores case and Arabic/Persian letter differences, like the server. */
const key = (name: string): string => words(name).join(' ');

const suggestions = computed(() => {
    const query = key(text.value);
    const chosen = new Set(tags.value.map(key));

    return query === ''
        ? []
        : known.value.filter((tag) => key(tag.name).includes(query) && !chosen.has(key(tag.name))).slice(0, 8);
});

function add(name: string): void {
    const clean = name.trim().replace(/\s+/g, ' ');

    if (clean !== '' && !tags.value.some((tag) => key(tag) === key(clean))) {
        tags.value = [...tags.value, clean];
    }
    text.value = '';
    open.value = false;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',' || event.key === '،') {
        event.preventDefault();
        add(text.value);
    } else if (event.key === 'Backspace' && text.value === '' && tags.value.length > 0) {
        tags.value = tags.value.slice(0, -1);
    }
}

function remove(name: string): void {
    tags.value = tags.value.filter((tag) => tag !== name);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" class="text-sm font-semibold text-fg">
            {{ t('admin.products.tags') }} <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
        </label>
        <div
            class="relative flex min-h-13 flex-wrap items-center gap-1.5 rounded-xl border border-line-strong bg-surface px-2.5 py-2 focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft"
        >
            <span
                v-for="tag in tags"
                :key="tag"
                class="inline-flex h-8 items-center gap-1 rounded-full bg-surface-muted ps-3 pe-1 text-sm"
            >
                {{ tag }}
                <button
                    type="button"
                    :aria-label="t('admin.products.removeTag', { name: tag })"
                    class="flex size-6 cursor-pointer items-center justify-center rounded-full text-fg-muted hover:bg-surface hover:text-fg"
                    @click="remove(tag)"
                >
                    <AppIcon name="close" :size="14" />
                </button>
            </span>
            <input
                :id="id"
                v-model="text"
                dir="auto"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open && suggestions.length > 0"
                :aria-controls="`${id}-list`"
                :placeholder="tags.length === 0 ? t('admin.products.tagsPlaceholder') : ''"
                class="h-8 min-w-32 grow bg-transparent text-sm text-fg outline-none"
                @keydown="onKeydown"
                @input="open = true"
                @blur="add(text)"
            />
            <ul
                v-if="open && suggestions.length > 0"
                :id="`${id}-list`"
                role="listbox"
                class="absolute inset-x-0 top-full z-20 mt-1 rounded-xl border border-line bg-surface p-1 shadow-xl"
            >
                <li v-for="tag in suggestions" :key="tag.id" role="option" :aria-selected="false">
                    <button
                        type="button"
                        class="flex h-10 w-full cursor-pointer items-center justify-between rounded-lg px-3 text-start text-sm hover:bg-surface-muted"
                        @mousedown.prevent="add(tag.name)"
                    >
                        {{ tag.name }}
                        <span class="text-xs text-fg-muted">{{ tag.products_count }}</span>
                    </button>
                </li>
            </ul>
        </div>
        <p class="text-[13px] text-fg-muted">{{ t('admin.products.tagsHint') }}</p>
    </div>
</template>
