<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { saleEventPayloadOf, saleEventsApi } from '../api';
import DiscountTabs from '../components/DiscountTabs.vue';
import type { SaleEvent, SaleEventState } from '../types';
import { useLandingPreview } from '../useLandingPreview';

/**
 * Sales › Discounts › Sale events: the whole list in priority order (highest first). Dragging or
 * Move up / Move down saves the new order; search and status filter in the browser.
 */
const { t } = useI18n();
const { formatNumber, formatDateTime } = useLocale();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageDiscounts));
const { openPreview } = useLandingPreview();

const events = ref<SaleEvent[] | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        events.value = await saleEventsApi.list();
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void load();

// ---- filters (in the URL) ----

const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

const STATES = ['all', 'active', 'scheduled', 'expired', 'disabled'] as const;
const search = computed({
    get: () => queryString('q'),
    set: (q: string) => void router.replace({ query: { ...route.query, q: q || undefined } }),
});
const status = computed<(typeof STATES)[number]>({
    get: () =>
        STATES.includes(queryString('status') as (typeof STATES)[number])
            ? (queryString('status') as (typeof STATES)[number])
            : 'all',
    set: (value) => void router.replace({ query: { ...route.query, status: value === 'all' ? undefined : value } }),
});
const statusOptions = computed(() =>
    STATES.map((value) => ({
        value,
        label: value === 'all' ? t('admin.discounts.all') : t(`admin.discounts.states.${value}`),
    })),
);

const filtering = computed(() => search.value.trim() !== '' || status.value !== 'all');
const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (events.value ?? []).filter(
        (event) =>
            (status.value === 'all' || event.state === status.value) &&
            (query === '' || event.name.toLowerCase().includes(query) || (event.slug ?? '').includes(query)),
    );
});
/** Priority as shown: 1 is the highest. */
const rank = (event: SaleEvent): number => (events.value ?? []).findIndex((item) => item.id === event.id) + 1;

// ---- order ----

/** Saves the full order; the server's list then replaces the local one. */
async function reorder(ids: number[]): Promise<void> {
    try {
        events.value = await saleEventsApi.reorder(ids);
        toast.show(t('admin.discounts.events.orderSaved'));
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
        // The list already shows the drop; reload the real order.
        await load();
    }
}

async function onDrop(): Promise<void> {
    // Let the list model catch up with the drop before reading the new order.
    await nextTick();
    void reorder((events.value ?? []).map((event) => event.id));
}

function move(event: SaleEvent, offset: -1 | 1): void {
    const ids = (events.value ?? []).map((item) => item.id);
    const index = ids.indexOf(event.id);
    const target = index + offset;

    if (index === -1 || target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target] as number, event.id];
    void reorder(ids);
}

// ---- display ----

const stateTone = (state: SaleEventState): 'success' | 'warning' | 'neutral' =>
    state === 'active' ? 'success' : state === 'scheduled' ? 'warning' : 'neutral';

const counts = (event: SaleEvent): string =>
    t('admin.discounts.events.counts', {
        products: formatNumber(event.products_count ?? 0),
        variants: formatNumber(event.items_count ?? 0),
    });

const menuItems = (event: SaleEvent): ActionMenuItem[] => {
    const index = rank(event) - 1;
    const last = (events.value?.length ?? 0) - 1;

    return [
        { key: 'edit', label: t('admin.discounts.events.edit'), icon: 'pen' },
        ...(canManage.value && !filtering.value
            ? [
                  ...(index > 0
                      ? [{ key: 'up', label: t('admin.discounts.events.moveUp'), icon: 'arrowUp' as const }]
                      : []),
                  ...(index < last
                      ? [{ key: 'down', label: t('admin.discounts.events.moveDown'), icon: 'arrowDown' as const }]
                      : []),
              ]
            : []),
        ...(event.has_landing_page
            ? [{ key: 'preview', label: t('admin.discounts.events.previewLanding'), icon: 'eye' as const }]
            : []),
        ...(canManage.value
            ? [
                  event.is_active
                      ? { key: 'toggle', label: t('admin.discounts.events.disable'), icon: 'ban' as const }
                      : { key: 'toggle', label: t('admin.discounts.events.enable'), icon: 'check' as const },
                  {
                      key: 'delete',
                      label: t('admin.discounts.events.delete'),
                      icon: 'trash' as const,
                      danger: true,
                      separated: true,
                  },
              ]
            : []),
    ];
};

// ---- actions ----

const deleting = ref<SaleEvent | null>(null);
const busy = ref(false);

async function act(event: SaleEvent, action: string): Promise<void> {
    if (action === 'edit') {
        await router.push({ name: 'sale-event', params: { id: event.id } });
    } else if (action === 'up' || action === 'down') {
        move(event, action === 'up' ? -1 : 1);
    } else if (action === 'preview') {
        await openPreview(event.id);
    } else if (action === 'delete') {
        deleting.value = event;
    } else if (action === 'toggle') {
        try {
            // A save sends every item, so the event is loaded first.
            const detail = await saleEventsApi.show(event.id);
            const saved = await saleEventsApi.update(event.id, {
                ...saleEventPayloadOf(detail),
                is_active: !detail.is_active,
            });
            toast.show(
                t(saved.is_active ? 'admin.discounts.events.enabled' : 'admin.discounts.events.disabled', {
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
    const event = deleting.value;

    if (!event) {
        return;
    }

    busy.value = true;

    try {
        await saleEventsApi.remove(event.id);
        toast.show(t('admin.discounts.events.deleted', { name: event.name }));
        deleting.value = null;
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.discounts.title') }}</h1>
                <p class="max-w-3xl text-sm text-fg-muted">{{ t('admin.discounts.events.intro') }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="router.push({ name: 'sale-event-create' })">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.discounts.events.new') }}
            </AppButton>
        </div>

        <DiscountTabs active="events" />

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.discounts.events.search')" />
            <div class="w-full sm:w-auto sm:min-w-[28rem]">
                <SegmentedControl v-model="status" :label="t('admin.discounts.statusLabel')" :options="statusOptions" />
            </div>
        </div>
        <p v-if="canManage && events && events.length > 1" class="text-[13px] text-fg-muted">
            {{ t('admin.discounts.events.orderHint') }}
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
            :class="['rounded-2xl border border-line bg-surface transition-opacity', loading && events && 'opacity-60']"
        >
            <div v-if="!events" aria-hidden="true" class="flex flex-col gap-px overflow-hidden rounded-2xl">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-18 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="filtered.length === 0"
                :icon="filtering ? 'search' : 'tag'"
                :title="filtering ? t('admin.discounts.events.noResults') : t('admin.discounts.events.empty')"
                :description="filtering ? undefined : t('admin.discounts.events.emptyText')"
            >
                <AppButton v-if="canManage && !filtering" size="md" @click="router.push({ name: 'sale-event-create' })">
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.discounts.events.new') }}
                </AppButton>
            </EmptyState>

            <VueDraggable
                v-else
                v-model="events"
                tag="ul"
                handle=".drag-handle"
                ghost-class="event-drop-ghost"
                :animation="150"
                :disabled="!canManage || filtering"
                class="flex flex-col divide-y divide-line"
                @update="onDrop"
            >
                <li
                    v-for="event in filtered"
                    :key="event.id"
                    :class="[
                        'flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 hover:bg-surface-muted/40 sm:flex-nowrap',
                        !event.is_active && 'opacity-70',
                    ]"
                >
                    <button
                        v-if="canManage && !filtering && events.length > 1"
                        type="button"
                        class="drag-handle flex h-10 w-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        :aria-label="t('admin.discounts.events.dragHandle', { name: event.name })"
                    >
                        <AppIcon name="grip" :size="18" />
                    </button>
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-[13px] font-bold text-fg-muted"
                        :title="t('admin.discounts.events.priority', { n: formatNumber(rank(event)) })"
                        >{{ formatNumber(rank(event)) }}</span
                    >
                    <span class="flex min-w-0 grow basis-48 flex-col gap-0.5">
                        <RouterLink
                            :to="{ name: 'sale-event', params: { id: event.id } }"
                            class="truncate rounded font-semibold text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        >
                            {{ event.name }}
                        </RouterLink>
                        <span class="truncate text-xs text-fg-muted">{{ counts(event) }}</span>
                    </span>
                    <span
                        :class="[
                            'inline-flex h-6.5 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold whitespace-nowrap',
                            event.has_landing_page
                                ? 'bg-primary-soft text-primary'
                                : 'border border-dashed border-line-strong text-fg-muted',
                        ]"
                    >
                        <AppIcon name="globe" :size="14" />
                        {{
                            event.has_landing_page
                                ? t('admin.discounts.events.landingChip')
                                : t('admin.discounts.events.noLanding')
                        }}
                    </span>
                    <span class="shrink-0 text-xs text-fg-muted sm:w-64 sm:text-end">{{
                        t('admin.discounts.window.between', {
                            start: formatDateTime(event.starts_at),
                            end: formatDateTime(event.ends_at),
                        })
                    }}</span>
                    <AppBadge :tone="stateTone(event.state)" class="shrink-0">{{
                        t(`admin.discounts.states.${event.state}`)
                    }}</AppBadge>
                    <ActionMenu
                        :label="t('admin.discounts.events.actionsFor', { name: event.name })"
                        :items="menuItems(event)"
                        @select="act(event, $event)"
                    />
                </li>
            </VueDraggable>
        </section>

        <AppDialog
            :open="deleting !== null"
            :title="t('admin.discounts.events.deleteTitle', { name: deleting?.name ?? '' })"
            icon="trash"
            tone="danger"
            :busy="busy"
            @close="deleting = null"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.events.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="busy" @click="remove">{{
                    t('admin.discounts.events.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="busy" @click="deleting = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>

<style scoped>
.event-drop-ghost {
    opacity: 0.4;
}
</style>
