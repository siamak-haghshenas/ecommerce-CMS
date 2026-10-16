<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import type { Brand } from '../../brands/types';
import { brandsApi } from '../../brands/api';
import { categoriesApi } from '../../categories/api';
import { type FlatCategory, flatten } from '../../categories/tree';
import { productsApi, tagsApi } from '../../products/api';
import type { ProductListItem, Tag } from '../../products/types';
import { TARGET_KINDS } from '../coupons';
import type { CouponLink, CouponTargetKind, CouponTargets } from '../types';

/**
 * "Applies to" of the coupon editor: categories (with their sub-categories), brands, tags and
 * products in tabs. A product counts when it is in any of them. A category chosen with its parent
 * already chosen is covered by it, so it shows ticked and can't be changed.
 */
const props = defineProps<{ open: boolean; targets: CouponTargets }>();
const emit = defineEmits<{ close: []; confirm: [targets: CouponTargets] }>();

const PER_PAGE = 20;

const { t } = useI18n();
const { formatNumber } = useLocale();

const tab = ref<CouponTargetKind>('categories');
const query = ref('');
const chosen = ref<Record<CouponTargetKind, Map<number, CouponLink>>>(emptyChoice());
const error = ref<string | null>(null);

function emptyChoice(): Record<CouponTargetKind, Map<number, CouponLink>> {
    return { categories: new Map(), brands: new Map(), tags: new Map(), products: new Map() };
}

// ---- lists ----

const categories = ref<FlatCategory[] | null>(null);
const brands = ref<Brand[] | null>(null);
const tags = ref<Tag[] | null>(null);
const products = ref<Paginated<ProductListItem> | null>(null);
const productPage = ref(1);
const loading = ref(false);

async function loadTab(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        if (tab.value === 'categories' && categories.value === null) {
            categories.value = flatten(await categoriesApi.tree('product'));
        } else if (tab.value === 'brands' && brands.value === null) {
            brands.value = await brandsApi.list();
        } else if (tab.value === 'tags') {
            tags.value = await tagsApi.list(query.value || undefined);
        } else if (tab.value === 'products') {
            products.value = await productsApi.list({
                q: query.value || undefined,
                sort: 'name',
                page: productPage.value,
                per_page: PER_PAGE,
            });
        }
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        const next = emptyChoice();
        TARGET_KINDS.forEach((kind) => props.targets[kind].forEach((link) => next[kind].set(link.id, link)));
        chosen.value = next;
        tab.value = 'categories';
        query.value = '';
        void loadTab();
    },
);

watch(tab, () => {
    query.value = '';
    productPage.value = 1;
    void loadTab();
});

watch(query, () => {
    productPage.value = 1;

    // Categories and brands are filtered in the browser.
    if (tab.value === 'tags' || tab.value === 'products') {
        void loadTab();
    }
});

function goTo(next: number): void {
    productPage.value = next;
    void loadTab();
}

const needle = computed(() => query.value.trim().toLowerCase());

const shownCategories = computed(() =>
    (categories.value ?? []).filter(
        (item) =>
            needle.value === '' ||
            [item.node.name, ...item.path].some((name) => name.toLowerCase().includes(needle.value)),
    ),
);
const shownBrands = computed(() =>
    (brands.value ?? []).filter(
        (brand) =>
            needle.value === '' ||
            brand.name.toLowerCase().includes(needle.value) ||
            (brand.persian_name ?? '').includes(needle.value),
    ),
);

// ---- choosing ----

/** The chosen category above this one, if any: its whole branch is already included. */
function coveringParent(item: FlatCategory): string | null {
    const ancestors = (categories.value ?? []).filter(
        (other) => chosen.value.categories.has(other.node.id) && isAncestor(other, item),
    );

    return ancestors[0]?.node.name ?? null;
}

function isAncestor(ancestor: FlatCategory, item: FlatCategory): boolean {
    let parentId = item.node.parent_id;
    const byId = new Map((categories.value ?? []).map((other) => [other.node.id, other.node]));

    while (parentId !== null) {
        if (parentId === ancestor.node.id) {
            return true;
        }

        parentId = byId.get(parentId)?.parent_id ?? null;
    }

    return false;
}

function toggle(kind: CouponTargetKind, link: CouponLink): void {
    const next = { ...chosen.value, [kind]: new Map(chosen.value[kind]) };

    if (next[kind].has(link.id)) {
        next[kind].delete(link.id);
    } else {
        next[kind].set(link.id, link);

        // A chosen category covers its sub-categories: they no longer need their own entry.
        if (kind === 'categories') {
            const item = (categories.value ?? []).find((other) => other.node.id === link.id);
            (categories.value ?? [])
                .filter((other) => item && isAncestor(item, other))
                .forEach((other) => next.categories.delete(other.node.id));
        }
    }

    chosen.value = next;
}

const counts = computed(
    () =>
        Object.fromEntries(TARGET_KINDS.map((kind) => [kind, chosen.value[kind].size])) as Record<
            CouponTargetKind,
            number
        >,
);

const summary = computed(() => {
    const parts = TARGET_KINDS.filter((kind) => counts.value[kind] > 0).map((kind) =>
        t(`admin.discounts.coupons.list.count.${kind}`, { n: formatNumber(counts.value[kind]) }),
    );

    return parts.length > 0 ? parts.join(t('admin.discounts.separator')) : t('admin.discounts.coupons.targets.none');
});

function confirm(): void {
    emit('confirm', {
        categories: [...chosen.value.categories.values()],
        brands: [...chosen.value.brands.values()],
        tags: [...chosen.value.tags.values()],
        products: [...chosen.value.products.values()],
    });
}

const tabClass = (on: boolean): string[] => [
    'flex h-11 shrink-0 items-center border-b-2 px-1 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary text-fg' : 'border-transparent text-fg-muted hover:text-fg',
];
const rowClass = (on: boolean, locked = false): string[] => [
    'flex min-h-11 items-center gap-2.5 px-3.5 py-2',
    locked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-surface-muted/50',
    on && !locked ? 'bg-primary-soft/40' : '',
];
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.coupons.targets.title')"
        icon="package"
        size="lg"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.discounts.coupons.targets.intro') }}</p>

            <nav
                class="flex gap-5 overflow-x-auto border-b border-line"
                :aria-label="t('admin.discounts.coupons.targets.title')"
            >
                <button
                    v-for="kind in TARGET_KINDS"
                    :key="kind"
                    type="button"
                    :class="tabClass(tab === kind)"
                    :aria-pressed="tab === kind"
                    @click="tab = kind"
                >
                    {{ t(`admin.discounts.coupons.targets.tabs.${kind}`, { n: formatNumber(counts[kind]) }) }}
                </button>
            </nav>

            <SearchInput v-model="query" :label="t(`admin.discounts.coupons.targets.search.${tab}`)" />

            <FormAlert v-if="error" :message="error" />

            <div class="max-h-[26rem] overflow-y-auto rounded-xl border border-line">
                <div v-if="loading && !(tab === 'products' && products)" class="flex justify-center py-10">
                    <AppSpinner />
                </div>

                <ul v-else-if="tab === 'categories'" class="divide-y divide-line">
                    <li v-for="item in shownCategories" :key="item.node.id">
                        <label
                            :class="rowClass(chosen.categories.has(item.node.id), coveringParent(item) !== null)"
                            :style="{ paddingInlineStart: `${0.875 + (needle ? 0 : item.depth * 1.5)}rem` }"
                        >
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-[var(--color-primary)]"
                                :checked="chosen.categories.has(item.node.id) || coveringParent(item) !== null"
                                :disabled="coveringParent(item) !== null"
                                @change="toggle('categories', { id: item.node.id, name: item.node.name })"
                            />
                            <span class="flex min-w-0 grow flex-col">
                                <span class="truncate text-sm">{{ item.node.name }}</span>
                                <span v-if="needle && item.path.length" class="truncate text-xs text-fg-muted">{{
                                    item.path.join(' › ')
                                }}</span>
                            </span>
                            <span v-if="coveringParent(item)" class="shrink-0 text-xs text-fg-muted">{{
                                t('admin.discounts.coupons.targets.fromParent', { name: coveringParent(item) })
                            }}</span>
                            <span v-else-if="!item.node.is_visible" class="shrink-0 text-xs text-fg-muted">{{
                                t('admin.discounts.coupons.targets.hidden')
                            }}</span>
                        </label>
                    </li>
                    <li v-if="shownCategories.length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                        {{ t('admin.discounts.coupons.targets.noMatches') }}
                    </li>
                </ul>

                <ul v-else-if="tab === 'brands'" class="divide-y divide-line">
                    <li v-for="brand in shownBrands" :key="brand.id">
                        <label :class="rowClass(chosen.brands.has(brand.id))">
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-[var(--color-primary)]"
                                :checked="chosen.brands.has(brand.id)"
                                @change="toggle('brands', { id: brand.id, name: brand.name })"
                            />
                            <span class="grow truncate text-sm">{{ brand.name }}</span>
                            <span v-if="brand.persian_name" class="shrink-0 text-xs text-fg-muted">{{
                                brand.persian_name
                            }}</span>
                        </label>
                    </li>
                    <li v-if="shownBrands.length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                        {{ t('admin.discounts.coupons.targets.noMatches') }}
                    </li>
                </ul>

                <ul v-else-if="tab === 'tags'" class="divide-y divide-line">
                    <li v-for="tag in tags ?? []" :key="tag.id">
                        <label :class="rowClass(chosen.tags.has(tag.id))">
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-[var(--color-primary)]"
                                :checked="chosen.tags.has(tag.id)"
                                @change="toggle('tags', { id: tag.id, name: tag.name })"
                            />
                            <span class="grow truncate text-sm">{{ tag.name }}</span>
                            <span v-if="tag.products_count !== undefined" class="shrink-0 text-xs text-fg-muted">{{
                                t('admin.discounts.coupons.targets.products', { n: formatNumber(tag.products_count) })
                            }}</span>
                        </label>
                    </li>
                    <li v-if="(tags ?? []).length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                        {{ t('admin.discounts.coupons.targets.noMatches') }}
                    </li>
                </ul>

                <template v-else-if="products">
                    <ul :aria-busy="loading" :class="['divide-y divide-line', loading && 'opacity-60']">
                        <li v-for="product in products.data" :key="product.id">
                            <label :class="rowClass(chosen.products.has(product.id))">
                                <input
                                    type="checkbox"
                                    class="size-4 shrink-0 accent-[var(--color-primary)]"
                                    :checked="chosen.products.has(product.id)"
                                    @change="toggle('products', { id: product.id, name: product.name })"
                                />
                                <img
                                    v-if="product.image"
                                    :src="product.image"
                                    alt=""
                                    class="size-9 shrink-0 rounded-lg object-cover"
                                />
                                <span
                                    v-else
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-fg-muted"
                                    ><AppIcon name="image" :size="16"
                                /></span>
                                <span class="grow truncate text-sm">{{ product.name }}</span>
                                <span class="shrink-0 text-xs text-fg-muted">{{
                                    t(`admin.products.status.${product.status}`)
                                }}</span>
                            </label>
                        </li>
                        <li v-if="products.data.length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                            {{ t('admin.discounts.coupons.targets.noMatches') }}
                        </li>
                    </ul>
                    <AppPagination v-if="products.meta.last_page > 1" :meta="products.meta" @change="goTo" />
                </template>
            </div>

            <p class="text-[13px] font-semibold" aria-live="polite">{{ summary }}</p>
        </div>

        <template #footer>
            <AppButton size="md" @click="confirm">{{ t('admin.discounts.coupons.targets.confirm') }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
