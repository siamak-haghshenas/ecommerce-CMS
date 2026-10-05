<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { settingsApi } from '../api';
import { useSettingsStore } from '../store';
import type { PriceDisplayCurrency } from '../types';

const { t } = useI18n();
const toast = useToast();
const auth = useAdminAuthStore();
const store = useSettingsStore();
const form = useApiForm(['price_display_currency', 'low_stock_threshold', 'storefront_preview_url'] as const);

/** Only the super admin changes settings; everyone else sees them. */
const canManage = computed(() => auth.can(Permission.ManageSettings));

const fields = reactive({ currency: 'IRT' as PriceDisplayCurrency, lowStock: 5 as number | null, previewUrl: '' });
const loading = ref(true);
const loadError = ref<string | null>(null);

const currencies = computed(() => [
    { value: 'IRT' as const, label: t('admin.money.IRT') },
    { value: 'IRR' as const, label: t('admin.money.IRR') },
]);

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const settings = await settingsApi.get();
        fields.currency = settings.price_display_currency;
        fields.lowStock = settings.low_stock_threshold;
        fields.previewUrl = settings.storefront_preview_url ?? '';
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void load();

async function save(): Promise<void> {
    const saved = await form.submit(() =>
        settingsApi.update({
            price_display_currency: fields.currency,
            low_stock_threshold: fields.lowStock ?? 0,
            storefront_preview_url: fields.previewUrl.trim() === '' ? null : fields.previewUrl.trim(),
        }),
    );

    if (saved) {
        store.set(saved);
        toast.show(t('admin.settings.saved'));
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-3xl flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold">{{ t('admin.settings.title') }}</h1>
            <p class="text-sm text-fg-muted">{{ t('admin.settings.intro') }}</p>
        </div>

        <p
            v-if="!canManage"
            role="note"
            class="flex items-center gap-2.5 rounded-xl bg-surface-muted px-4 py-3 text-sm text-fg-muted"
        >
            <AppIcon name="info" :size="18" />
            {{ t('admin.settings.readOnly') }}
        </p>

        <div v-if="loading" class="flex justify-center py-10"><AppSpinner /></div>
        <FormAlert v-else-if="loadError" :message="loadError" />

        <form
            v-else
            class="flex flex-col gap-6 rounded-2xl border border-line bg-surface p-5 sm:p-6"
            novalidate
            @submit.prevent="save"
        >
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />

            <fieldset class="flex flex-col gap-2" :disabled="!canManage">
                <legend class="mb-1 text-sm font-semibold">{{ t('admin.settings.currency') }}</legend>
                <SegmentedControl
                    v-model="fields.currency"
                    :label="t('admin.settings.currency')"
                    :options="currencies"
                />
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.settings.currencyHint') }}</p>
            </fieldset>

            <NumberField
                v-model="fields.lowStock"
                :label="t('admin.settings.lowStock')"
                :hint="t('admin.settings.lowStockHint')"
                :disabled="!canManage"
                :error="form.errors.low_stock_threshold"
            />

            <fieldset :disabled="!canManage">
                <TextField
                    v-model="fields.previewUrl"
                    :label="t('admin.settings.previewUrl')"
                    :hint="t('admin.settings.previewUrlHint')"
                    placeholder="https://shop.example/preview?token={token}"
                    dir="ltr"
                    optional
                    autocomplete="off"
                    :error="form.errors.storefront_preview_url"
                />
            </fieldset>

            <div v-if="canManage">
                <AppButton type="submit" size="md" :loading="form.processing.value">{{
                    t('admin.settings.save')
                }}</AppButton>
            </div>
        </form>
    </div>
</template>
