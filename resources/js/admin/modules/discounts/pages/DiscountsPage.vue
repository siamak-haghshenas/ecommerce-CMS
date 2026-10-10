<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { useMoney } from '../../settings/store';
import { payloadOf, promotionsApi } from '../api';
import type { Promotion, PromotionState } from '../types';

/** Sales › Discounts: the promotions (coupons come with their own phase). */
const { t } = useI18n();
const { formatNumber, formatDate } = useLocale();
const money = useMoney();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageDiscounts));

const result = ref<Paginated<Promotion> | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

// Filters live in the URL so a filtered list can be bookmarked and survives back/forward.
const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

function updateQuery(changes: Record<string, string | undefined>, keepPage = false): void {
    void router.replace({ query: { ...route.query, ...changes, ...(keepPage ? {} : { page: undefined }) } });
}

const STATES = ['all', 'active', 'scheduled', 'expired', 'disabled'] as const;
const search = computed({ get: () => queryString('q'), set: (q: string) => updateQuery({ q: q || undefined }) });
const status = computed<(typeof STATES)[number]>({
    get: () =>
        STATES.includes(queryString('status') as (typeof STATES)[number])
            ? (queryString('status') as (typeof STATES)[number])
            : 'all',
    set: (value) => updateQuery({ status: value === 'all' ? undefined : value }),
});
const page = computed(() => Math.max(1, Number(queryString('page')) || 1));

const statusOptions = computed(() =>
    STATES.map((value) => ({
        value,
        label: value === 'all' ? t('admin.discounts.all') : t(`admin.discounts.states.${value}`),
    })),
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        result.value = await promotionsApi.list({
            q: search.value || undefined,
            status: status.value === 'all' ? undefined : (status.value as PromotionState),
            page: page.value,
        });
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(() => route.query, load, { immediate: true });

// ---- display ----

function amount(promotion: Promotion): string {
    if (promotion.type === 'fixed') {
        return t('admin.discounts.promotions.amountOff', { amount: money.format(promotion.value) });
    }

    const percent = t('admin.discounts.promotions.percentOff', { n: formatNumber(promotion.value) });

    return promotion.max_discount === null
        ? percent
        : t('admin.discounts.promotions.percentUpTo', { percent, max: money.format(promotion.max_discount) });
}

function targets(promotion: Promotion): string {
    if (promotion.scope === 'all') {
        return t('admin.discounts.promotions.scopes.all');
    }

    return [
        [promotion.categories.length, 'categoriesCount'],
        [promotion.brands.length, 'brandsCount'],
        [promotion.tags.length, 'tagsCount'],
        [promotion.products.length, 'productsCount'],
    ]
        .filter(([count]) => (count as number) > 0)
        .map(([count, key]) => t(`admin.discounts.promotions.${key as string}`, { n: formatNumber(count as number) }))
        .join(t('admin.discounts.separator'));
}

function dateWindow(promotion: Promotion): string {
    const start = promotion.starts_at ? formatDate(promotion.starts_at) : null;
    const end = promotion.ends_at ? formatDate(promotion.ends_at) : null;

    if (start && end) {
        return t('admin.discounts.window.between', { start, end });
    }

    return end ? t('admin.discounts.window.until', { end }) : t('admin.discounts.window.from', { start: start ?? '—' });
}

const stateTone = (state: PromotionState): 'success' | 'warning' | 'neutral' =>
    state === 'active' ? 'success' : state === 'scheduled' ? 'warning' : 'neutral';

const menuItems = (promotion: Promotion): ActionMenuItem[] => [
    { key: 'edit', label: t('admin.discounts.promotions.edit'), icon: 'pen' },
    promotion.is_active
        ? { key: 'toggle', label: t('admin.discounts.promotions.disable'), icon: 'ban' }
        : { key: 'toggle', label: t('admin.discounts.promotions.enable'), icon: 'check' },
    { key: 'delete', label: t('admin.discounts.promotions.delete'), icon: 'trash', danger: true, separated: true },
];

// ---- actions ----

const deleting = ref<Promotion | null>(null);
const busy = ref(false);

async function act(promotion: Promotion, action: string): Promise<void> {
    if (action === 'edit') {
        await router.push({ name: 'promotion', params: { id: promotion.id } });
    } else if (action === 'delete') {
        deleting.value = promotion;
    } else if (action === 'toggle') {
        try {
            const saved = await promotionsApi.update(promotion.id, {
                ...payloadOf(promotion),
                is_active: !promotion.is_active,
            });
            toast.show(
                t(saved.is_active ? 'admin.discounts.promotions.enabled' : 'admin.discounts.promotions.disabled', {
                    name: saved.name,
                }),
            );
            await load();
        } catch (caught) {
            toast.show(toApiError(caught).message, 'danger');
        }
    }
}

async function remove(): Promise<void> {
    const promotion = deleting.value;

    if (!promotion) {
        return;
    }

    busy.value = true;

    try {
        await promotionsApi.remove(promotion.id);
        toast.show(t('admin.discounts.promotions.deleted', { name: promotion.name }));
        deleting.value = null;
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

const tabClass = (on: boolean): string[] => [
    'flex h-11 items-center gap-2 border-b-2 px-1 text-sm font-semibold',
    on ? 'border-primary text-fg' : 'border-transparent text-fg-muted',
];
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.discounts.title') }}</h1>
                <p class="text-sm text-fg-muted">{{ t('admin.discounts.intro') }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="router.push({ name: 'promotion-create' })">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.discounts.promotions.new') }}
            </AppButton>
        </div>

        <nav :aria-label="t('admin.discounts.title')" class="flex gap-6 border-b border-line">
            <span :class="tabClass(true)" aria-current="page">{{ t('admin.discounts.tabs.promotions') }}</span>
            <span :class="tabClass(false)" :title="t('admin.nav.soon')">
                {{ t('admin.discounts.tabs.coupons') }}
                <AppBadge>{{ t('admin.nav.soon') }}</AppBadge>
            </span>
        </nav>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.discounts.promotions.search')" />
            <div class="w-full sm:w-auto sm:min-w-[28rem]">
                <SegmentedControl v-model="status" :label="t('admin.discounts.statusLabel')" :options="statusOptions" />
            </div>
        </div>

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <section
            v-else
            :aria-busy="loading"
            :class="['rounded-2xl border border-line bg-surface transition-opacity', loading && result && 'opacity-60']"
        >
            <div v-if="!result" aria-hidden="true" class="flex flex-col gap-px overflow-hidden rounded-2xl">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-18 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="result.data.length === 0"
                icon="tag"
                :title="
                    search || status !== 'all'
                        ? t('admin.discounts.promotions.noResults')
                        : t('admin.discounts.promotions.empty')
                "
                :description="search || status !== 'all' ? undefined : t('admin.discounts.promotions.emptyText')"
            >
                <AppButton
                    v-if="canManage && !search && status === 'all'"
                    size="md"
                    @click="router.push({ name: 'promotion-create' })"
                >
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.discounts.promotions.new') }}
                </AppButton>
            </EmptyState>

            <template v-else>
                <ul class="divide-y divide-line">
                    <li
                        v-for="promotion in result.data"
                        :key="promotion.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 hover:bg-surface-muted/40 sm:flex-nowrap"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-sm font-bold text-fg"
                        >
                            {{
                                promotion.type === 'percent'
                                    ? t('admin.discounts.promotions.percentBadge', { n: formatNumber(promotion.value) })
                                    : '−'
                            }}
                        </span>
                        <span class="flex min-w-0 grow basis-48 flex-col gap-0.5">
                            <RouterLink
                                :to="{ name: 'promotion', params: { id: promotion.id } }"
                                class="truncate rounded font-semibold text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                            >
                                {{ promotion.name }}
                            </RouterLink>
                            <span class="truncate text-xs text-fg-muted"
                                >{{ amount(promotion) }} · {{ targets(promotion) }}</span
                            >
                        </span>
                        <span class="shrink-0 text-xs text-fg-muted sm:w-48 sm:text-end">{{
                            dateWindow(promotion)
                        }}</span>
                        <AppBadge :tone="stateTone(promotion.state)" class="shrink-0">{{
                            t(`admin.discounts.states.${promotion.state}`)
                        }}</AppBadge>
                        <ActionMenu
                            v-if="canManage"
                            :label="t('admin.discounts.promotions.actionsFor', { name: promotion.name })"
                            :items="menuItems(promotion)"
                            @select="act(promotion, $event)"
                        />
                    </li>
                </ul>

                <AppPagination :meta="result.meta" @change="updateQuery({ page: String($event) }, true)" />
            </template>
        </section>

        <AppDialog
            :open="deleting !== null"
            :title="t('admin.discounts.promotions.deleteTitle', { name: deleting?.name ?? '' })"
            icon="trash"
            tone="danger"
            :busy="busy"
            @close="deleting = null"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.promotions.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="busy" @click="remove">{{
                    t('admin.discounts.promotions.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="busy" @click="deleting = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
