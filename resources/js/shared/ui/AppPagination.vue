<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '../composables/useLocale';
import type { Paginated } from '../types/api';
import AppIcon from './AppIcon.vue';

const props = defineProps<{ meta: Paginated<unknown>['meta'] }>();
const emit = defineEmits<{ change: [page: number] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

/** Current page with up to two neighbours, plus first and last, separated by gaps. */
const pages = computed<(number | 'gap')[]>(() => {
    const { current_page: current, last_page: last } = props.meta;
    const shown = [...new Set([1, current - 1, current, current + 1, last])]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    return shown.flatMap((page, index) =>
        index > 0 && page - (shown[index - 1] ?? page) > 1 ? ['gap' as const, page] : [page],
    );
});

const buttonClass =
    'flex size-10 cursor-pointer items-center justify-center rounded-[10px] border border-line bg-surface text-sm text-fg hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40';
</script>

<template>
    <nav
        :aria-label="t('common.pagination')"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3"
    >
        <span class="text-[13px] text-fg-muted">
            {{
                t('common.showing', {
                    from: formatNumber(meta.from ?? 0),
                    to: formatNumber(meta.to ?? 0),
                    total: formatNumber(meta.total),
                })
            }}
        </span>
        <div v-if="meta.last_page > 1" class="flex items-center gap-1.5">
            <button
                type="button"
                :aria-label="t('common.previousPage')"
                :disabled="meta.current_page <= 1"
                :class="buttonClass"
                @click="emit('change', meta.current_page - 1)"
            >
                <AppIcon name="chevronNext" flip-in-rtl class="rotate-180" />
            </button>
            <template v-for="(page, index) in pages" :key="index">
                <span v-if="page === 'gap'" class="w-6 text-center text-fg-muted">…</span>
                <button
                    v-else
                    type="button"
                    :aria-current="page === meta.current_page ? 'page' : undefined"
                    :class="[buttonClass, page === meta.current_page && 'border-primary bg-primary-soft font-bold']"
                    @click="emit('change', page)"
                >
                    {{ formatNumber(page) }}
                </button>
            </template>
            <button
                type="button"
                :aria-label="t('common.nextPage')"
                :disabled="meta.current_page >= meta.last_page"
                :class="buttonClass"
                @click="emit('change', meta.current_page + 1)"
            >
                <AppIcon name="chevronNext" flip-in-rtl />
            </button>
        </div>
    </nav>
</template>
