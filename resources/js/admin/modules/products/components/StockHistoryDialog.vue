<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { productsApi } from '../api';
import type { StockMovement } from '../types';

/** Every stock change of the product's variants: who, why, before → after. */
const props = defineProps<{ open: boolean; productId: number }>();
const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const { formatNumber, formatDateTime, localizeDigits } = useLocale();

const result = ref<Paginated<StockMovement> | null>(null);
const error = ref<string | null>(null);
const page = ref(1);

async function load(): Promise<void> {
    error.value = null;

    try {
        result.value = await productsApi.stockMovements(props.productId, page.value);
    } catch (caught) {
        error.value = toApiError(caught).message;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            page.value = 1;
            result.value = null;
            void load();
        }
    },
);
watch(page, load);

const signed = (value: number): string => (value > 0 ? `+${formatNumber(value)}` : formatNumber(value));
</script>

<template>
    <AppDialog :open="open" :title="t('admin.products.stockHistory.title')" size="lg" @close="emit('close')">
        <FormAlert v-if="error" :message="error" />
        <div v-else-if="!result" class="flex justify-center py-8"><AppSpinner /></div>
        <EmptyState
            v-else-if="result.data.length === 0"
            icon="history"
            :title="t('admin.products.stockHistory.empty')"
        />
        <template v-else>
            <ul class="flex flex-col divide-y divide-line">
                <li
                    v-for="movement in result.data"
                    :key="movement.id"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5 text-sm"
                >
                    <span
                        dir="ltr"
                        :class="[
                            'w-16 font-semibold tabular-nums',
                            movement.quantity_change < 0 ? 'text-danger' : 'text-success',
                        ]"
                    >
                        {{ signed(movement.quantity_change) }}
                    </span>
                    <AppBadge>{{ t(`admin.products.stockHistory.reasons.${movement.reason}`) }}</AppBadge>
                    <span class="text-fg-muted" dir="ltr">{{ localizeDigits(movement.variant.sku ?? '') }}</span>
                    <span class="text-fg-muted">
                        {{ formatNumber(movement.quantity_before) }} → {{ formatNumber(movement.quantity_after) }}
                    </span>
                    <span class="ms-auto text-xs text-fg-muted">
                        {{ movement.user?.name ?? t('admin.products.stockHistory.system') }} ·
                        {{ movement.created_at ? formatDateTime(movement.created_at) : '' }}
                    </span>
                </li>
            </ul>
            <AppPagination :meta="result.meta" @change="page = $event" />
        </template>
    </AppDialog>
</template>
