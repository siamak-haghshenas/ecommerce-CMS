<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { shippingApi } from '../api';
import type { Province, ShippingCity } from '../types';

/**
 * "Add cities" to a courier method: open a province and tick its cities (or all of them), or
 * search every province. Cities already in the method are shown but can't be ticked again.
 */
const props = defineProps<{ open: boolean; chosenIds: Set<number> }>();
const emit = defineEmits<{ close: []; add: [cities: ShippingCity[]] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const provinces = ref<Province[] | null>(null);
const loadError = ref<string | null>(null);
const provinceId = ref<number | null>(null);
const search = ref('');
const picked = ref<Map<number, ShippingCity>>(new Map());

async function load(): Promise<void> {
    loadError.value = null;

    try {
        provinces.value = await shippingApi.provinces();
        provinceId.value ??= provinces.value[0]?.id ?? null;
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            picked.value = new Map();
            search.value = '';

            if (!provinces.value) {
                void load();
            }
        }
    },
);

/** Arabic ي/ك and half-spaces typed by mistake still match. */
const normalize = (text: string): string =>
    text.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/‌/g, ' ').trim().toLowerCase();

const province = computed(() => provinces.value?.find((item) => item.id === provinceId.value) ?? null);

/** The open province's cities, or every province's matches while searching. */
const shown = computed((): ShippingCity[] => {
    const query = normalize(search.value);
    const list = query === '' ? (province.value ? [province.value] : []) : (provinces.value ?? []);

    return list.flatMap((item) =>
        item.cities
            .filter((city) => query === '' || normalize(city.name).includes(query))
            .map((city) => ({ id: city.id, name: city.name, province: { id: item.id, name: item.name }, fee: null })),
    );
});

const chosenIn = (item: Province): number =>
    item.cities.filter((city) => props.chosenIds.has(city.id) || picked.value.has(city.id)).length;

function toggle(city: ShippingCity): void {
    const next = new Map(picked.value);

    if (next.has(city.id)) {
        next.delete(city.id);
    } else {
        next.set(city.id, city);
    }

    picked.value = next;
}

const selectable = computed(() => shown.value.filter((city) => !props.chosenIds.has(city.id)));
const allPicked = computed(
    () => selectable.value.length > 0 && selectable.value.every((city) => picked.value.has(city.id)),
);

function toggleAll(): void {
    const next = new Map(picked.value);

    for (const city of selectable.value) {
        if (allPicked.value) {
            next.delete(city.id);
        } else {
            next.set(city.id, city);
        }
    }

    picked.value = next;
}

function confirm(): void {
    emit('add', [...picked.value.values()]);
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.shipping.cities.dialogTitle')"
        icon="mapPin"
        size="lg"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-3">
            <p class="text-sm text-fg-muted">{{ t('admin.shipping.cities.dialogIntro') }}</p>
            <label
                class="flex h-11 items-center gap-2 rounded-[10px] border border-line-strong px-3 focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft"
            >
                <AppIcon name="search" :size="16" class="text-fg-muted" />
                <span class="sr-only">{{ t('admin.shipping.cities.search') }}</span>
                <input
                    v-model="search"
                    type="search"
                    :placeholder="t('admin.shipping.cities.search')"
                    class="h-full w-full bg-transparent text-sm outline-none"
                />
            </label>

            <FormAlert v-if="loadError" :message="loadError" />
            <div v-else-if="!provinces" class="flex justify-center py-10"><AppSpinner /></div>

            <div v-else class="grid gap-3 sm:grid-cols-[13rem_minmax(0,1fr)]">
                <ul
                    v-if="search.trim() === ''"
                    :aria-label="t('admin.shipping.cities.provinces')"
                    class="flex max-h-80 gap-1 overflow-auto sm:flex-col"
                >
                    <li v-for="item in provinces" :key="item.id" class="shrink-0">
                        <button
                            type="button"
                            :aria-pressed="item.id === provinceId"
                            :class="[
                                'flex h-10 w-full items-center justify-between gap-2 rounded-[10px] px-3 text-start text-[13px] whitespace-nowrap focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                item.id === provinceId
                                    ? 'bg-primary-soft font-bold text-fg'
                                    : 'text-fg-muted hover:bg-surface-muted hover:text-fg',
                            ]"
                            @click="provinceId = item.id"
                        >
                            <span>{{ item.name }}</span>
                            <span v-if="chosenIn(item) > 0" class="text-[11px] font-semibold text-primary">{{
                                formatNumber(chosenIn(item))
                            }}</span>
                        </button>
                    </li>
                </ul>

                <div :class="['flex min-w-0 flex-col gap-2', search.trim() !== '' && 'sm:col-span-2']">
                    <div class="flex items-center justify-between gap-3 border-b border-line pb-2">
                        <span class="text-sm font-bold">{{
                            search.trim() === ''
                                ? (province?.name ?? '')
                                : t('admin.shipping.cities.matches', { n: formatNumber(shown.length) })
                        }}</span>
                        <label
                            v-if="selectable.length > 0"
                            class="flex cursor-pointer items-center gap-2 text-[13px] font-semibold"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-[var(--color-primary)]"
                                :checked="allPicked"
                                @change="toggleAll"
                            />
                            {{
                                search.trim() === ''
                                    ? t('admin.shipping.cities.allOfProvince')
                                    : t('admin.shipping.cities.allMatches')
                            }}
                        </label>
                    </div>
                    <p v-if="shown.length === 0" class="py-6 text-center text-[13px] text-fg-muted">
                        {{ t('admin.shipping.cities.none') }}
                    </p>
                    <ul v-else class="grid max-h-72 gap-x-3 overflow-auto sm:grid-cols-2">
                        <li v-for="city in shown" :key="city.id">
                            <label
                                :class="[
                                    'flex h-10 cursor-pointer items-center gap-2.5 rounded-lg px-2 text-[13px]',
                                    chosenIds.has(city.id)
                                        ? 'cursor-default opacity-55'
                                        : picked.has(city.id)
                                          ? 'bg-primary-soft/50'
                                          : 'hover:bg-surface-muted/60',
                                ]"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 accent-[var(--color-primary)]"
                                    :checked="chosenIds.has(city.id) || picked.has(city.id)"
                                    :disabled="chosenIds.has(city.id)"
                                    @change="toggle(city)"
                                />
                                <span class="grow">{{ city.name }}</span>
                                <span v-if="search.trim() !== ''" class="text-[11px] text-fg-muted">{{
                                    city.province.name
                                }}</span>
                                <span v-else-if="chosenIds.has(city.id)" class="text-[11px] text-fg-muted">{{
                                    t('admin.shipping.cities.already')
                                }}</span>
                            </label>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <template #footer>
            <AppButton size="md" :disabled="picked.size === 0" @click="confirm">{{
                t('admin.shipping.cities.add', { n: formatNumber(picked.size) })
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
