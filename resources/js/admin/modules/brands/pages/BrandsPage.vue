<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { ratingOf } from '../../seo/analysis';
import { brandsApi } from '../api';
import BrandFormDialog from '../components/BrandFormDialog.vue';
import BrandRow, { type BrandAction } from '../components/BrandRow.vue';
import DeleteBrandDialog from '../components/DeleteBrandDialog.vue';
import type { Brand } from '../types';

const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const auth = useAdminAuthStore();

const canManage = computed(() => auth.can(Permission.ManageBrands));

const brands = ref<Brand[] | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

// Filters run in the browser: the whole list is loaded, since admins order it by hand.
const search = ref('');
const status = ref<'all' | 'visible' | 'hidden'>('all');
const needsSeo = ref(false);

const statusOptions = computed(() => [
    { value: 'all' as const, label: t('admin.brands.statusAll') },
    { value: 'visible' as const, label: t('admin.brands.visible') },
    { value: 'hidden' as const, label: t('admin.brands.hidden') },
]);

const filtering = computed(() => search.value.trim() !== '' || status.value !== 'all' || needsSeo.value);

const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (brands.value ?? []).filter(
        (brand) =>
            (query === '' ||
                brand.name.toLowerCase().includes(query) ||
                (brand.persian_name ?? '').includes(query) ||
                brand.slug.includes(query)) &&
            (status.value === 'all' || brand.is_active === (status.value === 'visible')) &&
            (!needsSeo.value || ratingOf(brand.seo_score) !== 'good'),
    );
});

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        brands.value = await brandsApi.list();
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void load();

/** Saves the full order; the server's list then replaces the local one. */
async function reorder(ids: number[]): Promise<void> {
    try {
        brands.value = await brandsApi.reorder(ids);
        toast.show(t('admin.brands.orderSaved'));
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
        // The list already shows the drop; reload the real order.
        await load();
    }
}

async function onDrop(): Promise<void> {
    // Let the list model catch up with the drop before reading the new order.
    await nextTick();
    void reorder((brands.value ?? []).map((brand) => brand.id));
}

function move(brand: Brand, offset: -1 | 1): void {
    const ids = (brands.value ?? []).map((item) => item.id);
    const index = ids.indexOf(brand.id);
    const target = index + offset;

    if (index === -1 || target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target] as number, brand.id];
    void reorder(ids);
}

async function toggleVisibility(brand: Brand): Promise<void> {
    try {
        await brandsApi.update(brand.id, {
            name: brand.name,
            persian_name: brand.persian_name,
            slug: brand.slug,
            description: brand.description,
            website: brand.website,
            meta_title: brand.meta_title,
            meta_description: brand.meta_description,
            focus_keyphrase: brand.focus_keyphrase,
            is_active: !brand.is_active,
            logo_id: brand.logo?.id ?? null,
        });
        toast.show(t(brand.is_active ? 'admin.brands.hiddenToast' : 'admin.brands.shownToast', { name: brand.name }));
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

// ---- dialogs ----

const formOpen = ref(false);
const editing = ref<Brand | null>(null);
const deleting = ref<Brand | null>(null);

function openForm(brand: Brand | null): void {
    editing.value = brand;
    formOpen.value = true;
}

function act(action: BrandAction, brand: Brand): void {
    if (action === 'edit') {
        openForm(brand);
    } else if (action === 'moveUp' || action === 'moveDown') {
        move(brand, action === 'moveUp' ? -1 : 1);
    } else if (action === 'toggleVisibility') {
        void toggleVisibility(brand);
    } else {
        deleting.value = brand;
    }
}

function onSaved(brand: Brand, created: boolean): void {
    formOpen.value = false;
    toast.show(t(created ? 'admin.brands.created' : 'admin.brands.saved', { name: brand.name }));
    void load();
}

function onDeleted(brand: Brand): void {
    deleting.value = null;
    toast.show(t('admin.brands.deleted', { name: brand.name }));
    void load();
}
</script>

<template>
    <div class="mx-auto flex max-w-5xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.brands.title') }}</h1>
                <p class="text-sm text-fg-muted">
                    {{ t('admin.brands.count', { n: formatNumber(brands?.length ?? 0) }) }}
                </p>
            </div>
            <AppButton v-if="canManage" size="md" @click="openForm(null)">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.brands.new') }}
            </AppButton>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.brands.search')" />
            <SegmentedControl v-model="status" :label="t('admin.brands.status')" :options="statusOptions" />
            <button
                type="button"
                :aria-pressed="needsSeo"
                :class="[
                    'flex h-11 cursor-pointer items-center gap-2 rounded-[10px] border px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                    needsSeo
                        ? 'border-primary bg-primary-soft text-fg'
                        : 'border-line-strong bg-surface text-fg-muted hover:text-fg',
                ]"
                @click="needsSeo = !needsSeo"
            >
                <span class="size-2.5 rounded-full bg-warning" aria-hidden="true" />
                {{ t('admin.seo.needsWork') }}
            </button>
        </div>

        <p
            v-if="canManage && brands && brands.length > 1 && !filtering"
            class="hidden items-center gap-2.5 rounded-xl bg-surface-muted px-4 py-3 text-sm text-fg-muted lg:flex"
        >
            <AppIcon name="grip" :size="18" />
            {{ t('admin.brands.dragTip') }}
        </p>

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <section
            v-else
            :aria-busy="loading"
            :aria-label="t('admin.brands.title')"
            :class="[
                'rounded-2xl border border-line bg-surface p-2 transition-opacity',
                loading && brands && 'opacity-60',
            ]"
        >
            <div v-if="!brands" aria-hidden="true" class="flex flex-col gap-2 p-1">
                <div
                    v-for="row in 5"
                    :key="row"
                    class="h-16 animate-pulse rounded-xl bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="brands.length === 0"
                icon="award"
                :title="t('admin.brands.empty')"
                :description="t('admin.brands.emptyText')"
            >
                <AppButton v-if="canManage" size="md" @click="openForm(null)">
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.brands.new') }}
                </AppButton>
            </EmptyState>

            <template v-else-if="filtering">
                <EmptyState
                    v-if="filtered.length === 0"
                    :icon="needsSeo && !search ? 'check' : 'search'"
                    :title="needsSeo && !search ? t('admin.seo.allGood') : t('admin.brands.noResults')"
                />
                <ul v-else class="flex flex-col">
                    <li v-for="brand in filtered" :key="brand.id">
                        <BrandRow
                            :brand="brand"
                            :can-manage="canManage"
                            :reorderable="false"
                            :is-first="false"
                            :is-last="false"
                            @act="act"
                        />
                    </li>
                </ul>
            </template>

            <VueDraggable
                v-else
                v-model="brands"
                tag="ul"
                handle=".drag-handle"
                ghost-class="brand-drop-ghost"
                :animation="150"
                :disabled="!canManage"
                class="flex flex-col"
                @update="onDrop"
            >
                <li v-for="(brand, index) in brands" :key="brand.id">
                    <BrandRow
                        :brand="brand"
                        :can-manage="canManage"
                        reorderable
                        :is-first="index === 0"
                        :is-last="index === brands.length - 1"
                        @act="act"
                    />
                </li>
            </VueDraggable>
        </section>

        <template v-if="canManage && brands">
            <BrandFormDialog
                :open="formOpen"
                :brand="editing"
                :brands="brands"
                @close="formOpen = false"
                @saved="onSaved"
            />
            <DeleteBrandDialog :brand="deleting" @close="deleting = null" @deleted="onDeleted" />
        </template>
    </div>
</template>

<style scoped>
.brand-drop-ghost {
    opacity: 0.4;
}
</style>
