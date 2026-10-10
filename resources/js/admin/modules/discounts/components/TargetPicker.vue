<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import { words } from '../../seo/analysis';
import type { NamedItem } from '../types';

/**
 * Several items chosen from a list (categories, brands, tags) or from a server search (products):
 * chosen ones show as removable chips; typing filters the suggestions (Arrow keys + Enter, Esc).
 */
export interface PickerOption extends NamedItem {
    /** Shown under the name, e.g. a category's path. */
    detail?: string;
}

const props = defineProps<{
    label: string;
    hint?: string;
    placeholder: string;
    error?: string;
    /** A fixed list, filtered in the browser… */
    options?: PickerOption[];
    /** …or a search on the server (debounced). */
    search?: (query: string) => Promise<PickerOption[]>;
}>();

const selected = defineModel<NamedItem[]>({ required: true });

const { t } = useI18n();
const id = useId();
const query = ref('');
const open = ref(false);
const active = ref(0);
const remote = ref<PickerOption[]>([]);
const searching = ref(false);
let timer: number | undefined;

const chosenIds = computed(() => new Set(selected.value.map((item) => item.id)));

const suggestions = computed<PickerOption[]>(() => {
    const pool = props.search ? remote.value : (props.options ?? []);
    const needle = words(query.value).join(' ');
    const matching = needle === '' ? pool : pool.filter((option) => words(option.name).join(' ').includes(needle));

    return matching.filter((option) => !chosenIds.value.has(option.id)).slice(0, 30);
});

watch(query, (value) => {
    active.value = 0;
    open.value = true;

    if (!props.search) {
        return;
    }

    window.clearTimeout(timer);
    timer = window.setTimeout(async () => {
        if (value.trim() === '') {
            remote.value = [];

            return;
        }

        searching.value = true;

        try {
            remote.value = await props.search!(value.trim());
        } catch {
            remote.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
});

onBeforeUnmount(() => window.clearTimeout(timer));

function pick(option: PickerOption): void {
    // A fresh array: the model updates only after the parent re-renders.
    selected.value = [...selected.value, { id: option.id, name: option.name }];
    query.value = '';
    open.value = false;
}

function remove(itemId: number): void {
    selected.value = selected.value.filter((item) => item.id !== itemId);
}

function onKeydown(event: KeyboardEvent): void {
    const list = suggestions.value;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        active.value = Math.min(active.value + 1, list.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (event.key === 'Enter') {
        const option = list[active.value];

        if (open.value && option) {
            event.preventDefault();
            pick(option);
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    } else if (event.key === 'Backspace' && query.value === '' && selected.value.length > 0) {
        remove(selected.value[selected.value.length - 1]!.id);
    }
}

const showList = computed(
    () => open.value && (suggestions.value.length > 0 || (props.search !== undefined && query.value.trim() !== '')),
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" class="text-sm font-semibold text-fg">{{ label }}</label>
        <div
            :class="[
                'relative flex min-h-13 flex-wrap items-center gap-1.5 rounded-xl border bg-surface px-2.5 py-2 focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft',
                error ? 'border-2 border-danger' : 'border-line-strong',
            ]"
        >
            <span
                v-for="item in selected"
                :key="item.id"
                class="flex max-w-full items-center gap-1 rounded-lg bg-surface-muted py-1 ps-2.5 pe-1 text-sm"
            >
                <span class="truncate">{{ item.name }}</span>
                <button
                    type="button"
                    :aria-label="t('admin.discounts.picker.remove', { name: item.name })"
                    class="flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-md text-fg-muted hover:bg-surface hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="remove(item.id)"
                >
                    <AppIcon name="close" :size="14" />
                </button>
            </span>
            <input
                :id="id"
                v-model="query"
                type="text"
                role="combobox"
                autocomplete="off"
                :aria-expanded="showList"
                :aria-controls="`${id}-list`"
                :aria-activedescendant="
                    showList && suggestions[active] ? `${id}-option-${suggestions[active]!.id}` : undefined
                "
                :placeholder="placeholder"
                class="h-9 min-w-40 grow bg-transparent px-1 text-[15px] text-fg outline-none placeholder:text-fg-muted"
                @focus="open = true"
                @blur="open = false"
                @keydown="onKeydown"
            />
            <ul
                v-if="showList"
                :id="`${id}-list`"
                role="listbox"
                class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-xl border border-line bg-surface p-1 shadow-lg"
            >
                <li v-if="searching" class="flex justify-center py-3"><AppSpinner /></li>
                <li v-else-if="suggestions.length === 0" class="px-3 py-2.5 text-sm text-fg-muted">
                    {{ t('admin.discounts.picker.noMatches') }}
                </li>
                <li
                    v-for="(option, index) in searching ? [] : suggestions"
                    :id="`${id}-option-${option.id}`"
                    :key="option.id"
                    role="option"
                    :aria-selected="index === active"
                    :class="[
                        'flex cursor-pointer flex-col rounded-lg px-3 py-2 text-sm',
                        index === active ? 'bg-primary-soft' : 'hover:bg-surface-muted',
                    ]"
                    @mousedown.prevent="pick(option)"
                    @mouseenter="active = index"
                >
                    <span>{{ option.name }}</span>
                    <span v-if="option.detail" class="text-xs text-fg-muted">{{ option.detail }}</span>
                </li>
            </ul>
        </div>
        <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>
        <p v-else-if="hint" class="text-[13px] leading-relaxed text-fg-muted">{{ hint }}</p>
    </div>
</template>
