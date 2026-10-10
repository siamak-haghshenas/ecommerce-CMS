<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { brandsApi } from '../../brands/api';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import { productsApi, tagsApi } from '../../products/api';
import { fromLocalInput, toLocalInput } from '../../products/variants';
import { useMoney } from '../../settings/store';
import { promotionsApi } from '../api';
import TargetPicker, { type PickerOption } from '../components/TargetPicker.vue';
import type { NamedItem, Promotion, PromotionScope, PromotionType, SavePromotionPayload } from '../types';

/** Create (`id` null) or edit a promotion. Saving reprices every product it touches. */
const props = defineProps<{ id: number | null }>();

const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const router = useRouter();
const auth = useAdminAuthStore();
const money = useMoney();
const canManage = computed(() => auth.can(Permission.ManageDiscounts));

const FIELDS = [
    'name',
    'type',
    'value',
    'max_discount',
    'scope',
    'product_ids',
    'category_ids',
    'brand_ids',
    'tag_ids',
    'excluded_product_ids',
    'starts_at',
    'ends_at',
    'is_active',
] as const;
const form = useApiForm(FIELDS);

const promotion = ref<Promotion | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);

const fields = reactive({
    name: '',
    type: 'percent' as PromotionType,
    value: null as number | null,
    maxDiscount: null as number | null,
    scope: 'selected' as PromotionScope,
    startsAt: '',
    endsAt: '',
    isActive: true,
});
const products = ref<NamedItem[]>([]);
const categories = ref<NamedItem[]>([]);
const brands = ref<NamedItem[]>([]);
const tags = ref<NamedItem[]>([]);
const excluded = ref<NamedItem[]>([]);

// Choices for the pickers (products are searched on the server).
const categoryOptions = ref<PickerOption[]>([]);
const brandOptions = ref<PickerOption[]>([]);
const tagOptions = ref<PickerOption[]>([]);

async function searchProducts(query: string): Promise<PickerOption[]> {
    const result = await productsApi.list({ q: query });

    return result.data.map((product) => ({
        id: product.id,
        name: product.name,
        detail: product.primary_category?.name,
    }));
}

function fill(loaded: Promotion | null): void {
    promotion.value = loaded;
    fields.name = loaded?.name ?? '';
    fields.type = loaded?.type ?? 'percent';
    fields.value = loaded?.value ?? null;
    fields.maxDiscount = loaded?.max_discount ?? null;
    fields.scope = loaded?.scope ?? 'selected';
    fields.startsAt = toLocalInput(loaded?.starts_at ?? null);
    fields.endsAt = toLocalInput(loaded?.ends_at ?? null);
    fields.isActive = loaded?.is_active ?? true;
    products.value = loaded?.products ?? [];
    categories.value = loaded?.categories ?? [];
    brands.value = loaded?.brands ?? [];
    tags.value = loaded?.tags ?? [];
    excluded.value = loaded?.excluded_products ?? [];
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const [loaded, tree, brandList, tagList] = await Promise.all([
            props.id === null ? Promise.resolve(null) : promotionsApi.show(props.id),
            categoriesApi.tree('product'),
            brandsApi.list(),
            tagsApi.list(),
        ]);
        categoryOptions.value = flatten(tree).map((item) => ({
            id: item.node.id,
            name: item.node.name,
            detail: item.path.length > 0 ? item.path.join(' › ') : undefined,
        }));
        brandOptions.value = brandList.map((brand) => ({
            id: brand.id,
            name: brand.name,
            detail: brand.persian_name ?? undefined,
        }));
        tagOptions.value = tagList.map((tag) => ({ id: tag.id, name: tag.name }));
        fill(loaded);
        productsCount.value = loaded?.products_count ?? null;
        snapshot.value = JSON.stringify(payload());
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

// ---- payload, unsaved changes ----

function payload(): SavePromotionPayload {
    const ids = (items: NamedItem[]): number[] => items.map((item) => item.id);
    const selectedScope = fields.scope === 'selected';

    return {
        name: fields.name.trim(),
        type: fields.type,
        value: fields.value ?? 0,
        max_discount: fields.type === 'percent' ? fields.maxDiscount : null,
        scope: fields.scope,
        product_ids: selectedScope ? ids(products.value) : [],
        category_ids: selectedScope ? ids(categories.value) : [],
        brand_ids: selectedScope ? ids(brands.value) : [],
        tag_ids: selectedScope ? ids(tags.value) : [],
        excluded_product_ids: ids(excluded.value),
        starts_at: fromLocalInput(fields.startsAt),
        ends_at: fromLocalInput(fields.endsAt),
        is_active: fields.isActive,
    };
}

const snapshot = ref('');
const dirty = computed(
    () => canManage.value && !loading.value && snapshot.value !== '' && JSON.stringify(payload()) !== snapshot.value,
);

// ---- "applies to N products" ----

const productsCount = ref<number | null>(null);
const counting = ref(false);
let countTimer: number | undefined;

/** The scope and targets only: the count doesn't depend on the amount or dates. */
const targetsKey = computed(() => {
    const body = payload();

    return JSON.stringify([
        body.scope,
        body.product_ids,
        body.category_ids,
        body.brand_ids,
        body.tag_ids,
        body.excluded_product_ids,
    ]);
});

watch(targetsKey, () => {
    if (loading.value || !canManage.value) {
        return;
    }

    window.clearTimeout(countTimer);
    countTimer = window.setTimeout(async () => {
        const body = payload();
        const hasTargets =
            body.scope === 'all' ||
            [body.product_ids, body.category_ids, body.brand_ids, body.tag_ids].some((list) => list.length > 0);

        if (!hasTargets) {
            productsCount.value = 0;

            return;
        }

        counting.value = true;

        try {
            // The count ignores the amount and dates; send valid ones so a half-filled form still counts.
            productsCount.value = await promotionsApi.preview({
                ...body,
                name: body.name || '—',
                value: body.value || 1,
                starts_at: null,
                ends_at: null,
            });
        } catch {
            productsCount.value = null;
        } finally {
            counting.value = false;
        }
    }, 400);
});

onBeforeUnmount(() => window.clearTimeout(countTimer));

// ---- saving, deleting ----

const fieldErrors = computed(() => form.lastError.value?.fieldErrors ?? {});
/** The first error of a target list or of one of its items. */
const listError = (key: string): string | undefined =>
    Object.entries(fieldErrors.value).find(([field]) => field === key || field.startsWith(`${key}.`))?.[1]?.[0];

const alertMessage = computed(() => {
    const keys = Object.keys(fieldErrors.value);
    const placed = (key: string): boolean =>
        (FIELDS as readonly string[]).some((field) => key === field || key.startsWith(`${field}.`));

    return keys.length > 0 && keys.every(placed) && form.lastError.value?.code === 'validation_failed'
        ? null
        : form.generalError.value;
});

async function save(): Promise<void> {
    const existing = promotion.value;
    const saved = await form.submit(() =>
        existing ? promotionsApi.update(existing.id, payload()) : promotionsApi.create(payload()),
    );

    if (!saved) {
        toast.show(t('admin.discounts.promotions.saveFailed'), 'danger');

        return;
    }

    fill(saved);
    snapshot.value = JSON.stringify(payload());
    toast.show(
        t(existing ? 'admin.discounts.promotions.saved' : 'admin.discounts.promotions.created', { name: saved.name }),
    );

    if (!existing) {
        await router.replace({ name: 'promotion', params: { id: saved.id } });
    }
}

const confirmDelete = ref(false);

async function remove(): Promise<void> {
    if (!promotion.value) {
        return;
    }

    try {
        await promotionsApi.remove(promotion.value.id);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');

        return;
    }

    snapshot.value = JSON.stringify(payload());
    toast.show(t('admin.discounts.promotions.deleted', { name: promotion.value.name }));
    await router.push({ name: 'discounts' });
}

// ---- leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    (to) =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value || to.name === 'promotion') {
                resolve(true);

                return;
            }

            leaveTarget.value = () => resolve(true);
            leaveCancel = () => resolve(false);
        }),
);

function stay(): void {
    leaveCancel?.();
    leaveTarget.value = null;
}

function leave(): void {
    leaveTarget.value?.();
    leaveTarget.value = null;
}

const beforeUnload = (event: BeforeUnloadEvent): void => {
    if (dirty.value) {
        event.preventDefault();
    }
};
window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));

// ---- page ----

const typeOptions = computed(() => [
    { value: 'percent' as const, label: t('admin.discounts.promotions.types.percent') },
    { value: 'fixed' as const, label: t('admin.discounts.promotions.types.fixed') },
]);
const scopeOptions = computed(() => [
    { value: 'selected' as const, label: t('admin.discounts.promotions.scopes.selected') },
    { value: 'all' as const, label: t('admin.discounts.promotions.scopes.all') },
]);
const stateTone = (state: Promotion['state']): 'success' | 'warning' | 'neutral' | 'danger' =>
    state === 'active' ? 'success' : state === 'scheduled' ? 'warning' : 'neutral';

const cardClass = 'flex flex-col gap-4.5 rounded-2xl border border-line bg-surface p-5 sm:p-6';
const dateClass =
    'h-13 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-5">
        <RouterLink
            :to="{ name: 'discounts' }"
            class="flex w-fit items-center gap-1.5 rounded-lg text-sm font-medium text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
        >
            <AppIcon name="arrowForward" :size="16" flip-in-rtl class="rotate-180" />
            {{ t('admin.discounts.backToList') }}
        </RouterLink>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                <h1 class="truncate text-2xl font-bold">
                    {{ promotion ? promotion.name : t('admin.discounts.promotions.newTitle') }}
                </h1>
                <AppBadge v-if="promotion" :tone="stateTone(promotion.state)">{{
                    t(`admin.discounts.states.${promotion.state}`)
                }}</AppBadge>
            </div>
            <AppButton v-if="promotion && canManage" variant="danger-outline" size="md" @click="confirmDelete = true">
                <AppIcon name="trash" :size="18" />
                {{ t('admin.discounts.promotions.delete') }}
            </AppButton>
        </div>

        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <div v-else-if="loadError" class="flex flex-col gap-3">
            <FormAlert :message="loadError" />
            <div>
                <AppButton variant="secondary" size="md" @click="load">{{ t('common.retry') }}</AppButton>
            </div>
        </div>

        <form v-else id="promotion-form" class="flex flex-col gap-5" novalidate @submit.prevent="save">
            <FormAlert v-if="alertMessage" :message="alertMessage" />

            <fieldset :disabled="!canManage" class="contents">
                <section :class="cardClass" aria-labelledby="promotion-discount-title">
                    <h2 id="promotion-discount-title" class="text-lg font-bold">
                        {{ t('admin.discounts.promotions.sections.discount') }}
                    </h2>
                    <TextField
                        v-model="fields.name"
                        :label="t('admin.discounts.promotions.name')"
                        :hint="t('admin.discounts.promotions.nameHint')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <div class="flex flex-col gap-2">
                        <span class="text-sm font-semibold">{{ t('admin.discounts.promotions.type') }}</span>
                        <div class="sm:w-96">
                            <SegmentedControl
                                v-model="fields.type"
                                :label="t('admin.discounts.promotions.type')"
                                :options="typeOptions"
                            />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <NumberField
                            v-if="fields.type === 'percent'"
                            v-model="fields.value"
                            :label="t('admin.discounts.promotions.percent')"
                            :hint="t('admin.discounts.promotions.percentHint')"
                            unit="%"
                            :error="form.errors.value"
                        />
                        <NumberField
                            v-else
                            v-model="fields.value"
                            :label="t('admin.discounts.promotions.amount')"
                            :hint="t('admin.discounts.promotions.amountHint')"
                            :unit="money.unit.value"
                            :factor="money.rialsPerUnit.value"
                            :error="form.errors.value"
                        />
                        <NumberField
                            v-if="fields.type === 'percent'"
                            v-model="fields.maxDiscount"
                            :label="t('admin.discounts.promotions.maxDiscount')"
                            :hint="t('admin.discounts.promotions.maxDiscountHint')"
                            :unit="money.unit.value"
                            :factor="money.rialsPerUnit.value"
                            optional
                            :error="form.errors.max_discount"
                        />
                    </div>
                </section>

                <section :class="cardClass" aria-labelledby="promotion-targets-title">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h2 id="promotion-targets-title" class="text-lg font-bold">
                            {{ t('admin.discounts.promotions.sections.targets') }}
                        </h2>
                        <span
                            role="status"
                            class="flex items-center gap-2 rounded-full bg-primary-soft px-3 py-1 text-sm font-semibold text-fg"
                        >
                            <AppSpinner v-if="counting" />
                            <template v-else>{{
                                productsCount === null
                                    ? t('admin.discounts.promotions.countUnknown')
                                    : t('admin.discounts.promotions.count', { n: formatNumber(productsCount) })
                            }}</template>
                        </span>
                    </div>
                    <div class="sm:w-96">
                        <SegmentedControl
                            v-model="fields.scope"
                            :label="t('admin.discounts.promotions.sections.targets')"
                            :options="scopeOptions"
                        />
                    </div>
                    <p v-if="form.errors.scope" class="text-[13px] text-danger">{{ form.errors.scope }}</p>
                    <template v-if="fields.scope === 'selected'">
                        <TargetPicker
                            v-model="categories"
                            :label="t('admin.discounts.promotions.categories')"
                            :hint="t('admin.discounts.promotions.categoriesHint')"
                            :placeholder="t('admin.discounts.picker.categories')"
                            :options="categoryOptions"
                            :error="listError('category_ids')"
                        />
                        <TargetPicker
                            v-model="brands"
                            :label="t('admin.discounts.promotions.brands')"
                            :placeholder="t('admin.discounts.picker.brands')"
                            :options="brandOptions"
                            :error="listError('brand_ids')"
                        />
                        <TargetPicker
                            v-model="tags"
                            :label="t('admin.discounts.promotions.tags')"
                            :placeholder="t('admin.discounts.picker.tags')"
                            :options="tagOptions"
                            :error="listError('tag_ids')"
                        />
                        <TargetPicker
                            v-model="products"
                            :label="t('admin.discounts.promotions.products')"
                            :placeholder="t('admin.discounts.picker.products')"
                            :search="searchProducts"
                            :error="listError('product_ids')"
                        />
                    </template>
                    <TargetPicker
                        v-model="excluded"
                        :label="t('admin.discounts.promotions.excluded')"
                        :hint="t('admin.discounts.promotions.excludedHint')"
                        :placeholder="t('admin.discounts.picker.products')"
                        :search="searchProducts"
                        :error="listError('excluded_product_ids')"
                    />
                </section>

                <section :class="cardClass" aria-labelledby="promotion-schedule-title">
                    <h2 id="promotion-schedule-title" class="text-lg font-bold">
                        {{ t('admin.discounts.promotions.sections.schedule') }}
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="promotion-starts" class="mb-2 block text-sm font-semibold">{{
                                t('admin.discounts.promotions.startsAt')
                            }}</label>
                            <input
                                id="promotion-starts"
                                v-model="fields.startsAt"
                                type="datetime-local"
                                :class="dateClass"
                            />
                            <p class="mt-2 text-[13px] text-fg-muted">
                                {{ t('admin.discounts.promotions.startsAtHint') }}
                            </p>
                        </div>
                        <div>
                            <label for="promotion-ends" class="mb-2 block text-sm font-semibold">{{
                                t('admin.discounts.promotions.endsAt')
                            }}</label>
                            <input
                                id="promotion-ends"
                                v-model="fields.endsAt"
                                type="datetime-local"
                                :class="[dateClass, form.errors.ends_at && 'border-2 border-danger']"
                            />
                            <p v-if="form.errors.ends_at" class="mt-2 text-[13px] text-danger">
                                {{ form.errors.ends_at }}
                            </p>
                            <p v-else class="mt-2 text-[13px] text-fg-muted">
                                {{ t('admin.discounts.promotions.endsAtHint') }}
                            </p>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line p-3.5">
                        <input
                            v-model="fields.isActive"
                            type="checkbox"
                            class="mt-1 size-4 accent-[var(--color-primary)]"
                        />
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ t('admin.discounts.promotions.active') }}</span>
                            <span class="text-[13px] text-fg-muted">{{
                                t('admin.discounts.promotions.activeHint')
                            }}</span>
                        </span>
                    </label>
                </section>
            </fieldset>
        </form>

        <div
            v-if="!loading && !loadError && canManage"
            class="sticky bottom-0 z-30 -mx-4 flex flex-wrap items-center gap-3 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur sm:mx-0 sm:rounded-t-2xl sm:border-x"
        >
            <span class="grow text-[13px] text-fg-muted">{{ t('admin.discounts.promotions.repriceNote') }}</span>
            <span v-if="dirty" class="text-xs text-warning">{{ t('admin.discounts.unsaved') }}</span>
            <AppButton type="submit" form="promotion-form" size="md" :loading="form.processing.value">
                {{ promotion ? t('admin.discounts.promotions.save') : t('admin.discounts.promotions.create') }}
            </AppButton>
        </div>

        <AppDialog
            :open="confirmDelete"
            :title="t('admin.discounts.promotions.deleteTitle', { name: promotion?.name ?? '' })"
            icon="trash"
            tone="danger"
            @close="confirmDelete = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.promotions.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="remove">{{
                    t('admin.discounts.promotions.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="confirmDelete = false">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.discounts.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.leaveText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="leave">{{ t('admin.discounts.leaveConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="stay">{{ t('admin.discounts.leaveStay') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
