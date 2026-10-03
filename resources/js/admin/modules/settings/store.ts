import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { settingsApi } from './api';
import type { StoreSettings } from './types';

const DEFAULTS: StoreSettings = { price_display_currency: 'IRT', low_stock_threshold: 5 };

/** Store settings, loaded once per session; defaults apply until they arrive. */
export const useSettingsStore = defineStore('admin-settings', () => {
    const settings = ref<StoreSettings>({ ...DEFAULTS });
    let loading: Promise<void> | null = null;

    function load(force = false): Promise<void> {
        if (force || loading === null) {
            loading = settingsApi
                .get()
                .then((loaded) => {
                    settings.value = loaded;
                })
                .catch(() => {
                    loading = null;
                });
        }

        return loading;
    }

    function set(next: StoreSettings): void {
        settings.value = next;
    }

    /** Rials per displayed unit: 10 for Toman, 1 for Rial. */
    const rialsPerUnit = computed(() => (settings.value.price_display_currency === 'IRT' ? 10 : 1));

    return { settings, rialsPerUnit, load, set };
});

/**
 * Amounts in the admin panel: stored in Rials, shown in the store's display currency
 * (Toman by default) with the language's digits and separators.
 */
export function useMoney() {
    const store = useSettingsStore();
    const { t } = useI18n();
    const { formatNumber } = useLocale();

    void store.load();

    const unit = computed(() => t(`admin.money.${store.settings.price_display_currency}`));

    function format(rials: number): string {
        return `${formatNumber(rials / store.rialsPerUnit)} ${unit.value}`;
    }

    return { unit, rialsPerUnit: computed(() => store.rialsPerUnit), format };
}
