<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import { limitInvalid, MAX_PER_ORDER } from '../saleEvents';

/**
 * One order limit for the selected rows of a sale event: the most units of a variant
 * one order may hold at the discounted price. Empty removes the limit.
 */
const props = defineProps<{ open: boolean; count: number }>();
const emit = defineEmits<{ close: []; apply: [limit: number | null] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const limit = ref<number | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            limit.value = null;
        }
    },
);

const invalid = computed(() => limitInvalid(limit.value));

function apply(): void {
    if (!invalid.value) {
        emit('apply', limit.value);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.limit.title', { n: formatNumber(count) })"
        icon="package"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.discounts.limit.intro') }}</p>
            <NumberField
                v-model="limit"
                :label="t('admin.discounts.limit.label')"
                :placeholder="t('admin.discounts.limit.none')"
                :unit="t('admin.discounts.limit.unit')"
                :error="invalid ? t('admin.discounts.limit.range', { max: formatNumber(MAX_PER_ORDER) }) : undefined"
                optional
            />
        </div>
        <template #footer>
            <AppButton size="md" :disabled="invalid" @click="apply">{{
                limit === null
                    ? t('admin.discounts.limit.remove', { n: formatNumber(count) })
                    : t('admin.discounts.limit.apply', { n: formatNumber(count) })
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
