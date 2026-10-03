<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
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
import { attributesApi, optionsApi } from '../api';
import AttributeFormDialog from '../components/AttributeFormDialog.vue';
import CatalogItemRow, { type CatalogItemAction } from '../components/CatalogItemRow.vue';
import DeleteCatalogItemDialog from '../components/DeleteCatalogItemDialog.vue';
import OptionFormDialog from '../components/OptionFormDialog.vue';
import type { Attribute, Option } from '../types';

type Tab = 'attributes' | 'options';

const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const auth = useAdminAuthStore();
const route = useRoute();
const router = useRouter();

const canManage = computed(() => auth.can(Permission.ManageAttributes));

/** The tab lives in the URL (?tab=options), so it survives a reload and can be linked. */
const tab = computed<Tab>({
    get: () => (route.query.tab === 'options' ? 'options' : 'attributes'),
    set: (value) => void router.replace({ query: value === 'options' ? { tab: 'options' } : {} }),
});
const tabs = computed(() => [
    { value: 'attributes' as const, label: t('admin.attributes.tabAttributes') },
    { value: 'options' as const, label: t('admin.attributes.tabOptions') },
]);

const attributes = ref<Attribute[] | null>(null);
const options = ref<Option[] | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const search = ref('');

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        [attributes.value, options.value] = await Promise.all([attributesApi.list(), optionsApi.list()]);
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void load();

const query = computed(() => search.value.trim().toLowerCase());
const matches = (item: { name: string; slug: string }): boolean =>
    query.value === '' || item.name.toLowerCase().includes(query.value) || item.slug.includes(query.value);

const filteredAttributes = computed(() => (attributes.value ?? []).filter(matches));
const filteredOptions = computed(() => (options.value ?? []).filter(matches));

function attributeBadges(attribute: Attribute): string[] {
    return [
        t(`admin.attributes.types.${attribute.type}`) + (attribute.unit ? ` · ${attribute.unit}` : ''),
        ...(attribute.is_filterable ? [t('admin.attributes.filterableBadge')] : []),
    ];
}

// ---- ordering ----

async function saveOrder(kind: Tab, ids: number[]): Promise<void> {
    try {
        if (kind === 'attributes') {
            attributes.value = await attributesApi.reorder(ids);
        } else {
            options.value = await optionsApi.reorder(ids);
        }
        toast.show(t('admin.attributes.orderSaved'));
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
        await load();
    }
}

async function onDrop(kind: Tab): Promise<void> {
    // Let the list model catch up with the drop before reading the new order.
    await nextTick();
    const list: { id: number }[] = (kind === 'attributes' ? attributes.value : options.value) ?? [];
    void saveOrder(
        kind,
        list.map((item) => item.id),
    );
}

function move(kind: Tab, id: number, offset: -1 | 1): void {
    const list: { id: number }[] = (kind === 'attributes' ? attributes.value : options.value) ?? [];
    const ids = list.map((item) => item.id);
    const index = ids.indexOf(id);
    const target = index + offset;

    if (index === -1 || target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target] as number, id];
    void saveOrder(kind, ids);
}

// ---- dialogs ----

const attributeForm = ref<{ open: boolean; attribute: Attribute | null }>({ open: false, attribute: null });
const optionForm = ref<{ open: boolean; option: Option | null }>({ open: false, option: null });
const deleting = ref<{ kind: 'attribute' | 'option'; item: { id: number; name: string } } | null>(null);

function create(): void {
    if (tab.value === 'attributes') {
        attributeForm.value = { open: true, attribute: null };
    } else {
        optionForm.value = { open: true, option: null };
    }
}

function actOnAttribute(action: CatalogItemAction, attribute: Attribute): void {
    if (action === 'edit') {
        attributeForm.value = { open: true, attribute };
    } else if (action === 'delete') {
        deleting.value = { kind: 'attribute', item: attribute };
    } else {
        move('attributes', attribute.id, action === 'moveUp' ? -1 : 1);
    }
}

function actOnOption(action: CatalogItemAction, option: Option): void {
    if (action === 'edit') {
        optionForm.value = { open: true, option };
    } else if (action === 'delete') {
        deleting.value = { kind: 'option', item: option };
    } else {
        move('options', option.id, action === 'moveUp' ? -1 : 1);
    }
}

function onSaved(name: string, created: boolean): void {
    attributeForm.value.open = false;
    optionForm.value.open = false;
    toast.show(t(created ? 'admin.attributes.created' : 'admin.attributes.saved', { name }));
    void load();
}

function onDeleted(name: string): void {
    deleting.value = null;
    toast.show(t('admin.attributes.deleted', { name }));
    void load();
}

const removeItem = (id: number): Promise<void> =>
    deleting.value?.kind === 'option' ? optionsApi.remove(id) : attributesApi.remove(id);
</script>

<template>
    <div class="mx-auto flex max-w-5xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.attributes.title') }}</h1>
                <p class="max-w-2xl text-sm leading-relaxed text-fg-muted">{{ t('admin.attributes.intro') }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="create">
                <AppIcon name="plus" :size="18" />
                {{ tab === 'attributes' ? t('admin.attributes.newAttribute') : t('admin.attributes.newOption') }}
            </AppButton>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-72">
                <SegmentedControl v-model="tab" :label="t('admin.attributes.title')" :options="tabs" />
            </div>
            <SearchInput v-model="search" :label="t('admin.attributes.search')" />
        </div>

        <p class="text-sm leading-relaxed text-fg-muted">
            {{ tab === 'attributes' ? t('admin.attributes.attributesHint') : t('admin.attributes.optionsHint') }}
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
            :aria-label="tab === 'attributes' ? t('admin.attributes.tabAttributes') : t('admin.attributes.tabOptions')"
            :class="[
                'rounded-2xl border border-line bg-surface p-2 transition-opacity',
                loading && attributes && 'opacity-60',
            ]"
        >
            <div v-if="!attributes || !options" aria-hidden="true" class="flex flex-col gap-2 p-1">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-16 animate-pulse rounded-xl bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <template v-else-if="tab === 'attributes'">
                <EmptyState
                    v-if="filteredAttributes.length === 0"
                    :icon="query ? 'search' : 'sliders'"
                    :title="query ? t('admin.attributes.noResults') : t('admin.attributes.emptyAttributes')"
                    :description="query ? undefined : t('admin.attributes.emptyAttributesText')"
                />
                <VueDraggable
                    v-else
                    v-model="attributes"
                    tag="ul"
                    handle=".drag-handle"
                    :animation="150"
                    :disabled="!canManage || query !== ''"
                    class="flex flex-col"
                    @update="onDrop('attributes')"
                >
                    <li v-for="(attribute, index) in filteredAttributes" :key="attribute.id">
                        <CatalogItemRow
                            :name="attribute.name"
                            :slug="attribute.slug"
                            :badges="attributeBadges(attribute)"
                            :values="(attribute.values ?? []).map((value) => ({ value: value.value }))"
                            :meta="
                                t('admin.attributes.categoriesCount', {
                                    n: formatNumber(attribute.categories_count ?? 0),
                                })
                            "
                            :can-manage="canManage && query === ''"
                            :is-first="index === 0"
                            :is-last="index === filteredAttributes.length - 1"
                            @act="actOnAttribute($event, attribute)"
                        />
                    </li>
                </VueDraggable>
            </template>

            <template v-else>
                <EmptyState
                    v-if="filteredOptions.length === 0"
                    :icon="query ? 'search' : 'sliders'"
                    :title="query ? t('admin.attributes.noResults') : t('admin.attributes.emptyOptions')"
                    :description="query ? undefined : t('admin.attributes.emptyOptionsText')"
                />
                <VueDraggable
                    v-else
                    v-model="options"
                    tag="ul"
                    handle=".drag-handle"
                    :animation="150"
                    :disabled="!canManage || query !== ''"
                    class="flex flex-col"
                    @update="onDrop('options')"
                >
                    <li v-for="(option, index) in filteredOptions" :key="option.id">
                        <CatalogItemRow
                            :name="option.name"
                            :slug="option.slug"
                            :badges="[
                                option.display_type === 'color'
                                    ? t('admin.attributes.displayColor')
                                    : t('admin.attributes.displayText'),
                            ]"
                            :values="option.values.map((value) => ({ value: value.value, color: value.color_hex }))"
                            :can-manage="canManage && query === ''"
                            :is-first="index === 0"
                            :is-last="index === filteredOptions.length - 1"
                            @act="actOnOption($event, option)"
                        />
                    </li>
                </VueDraggable>
            </template>
        </section>

        <template v-if="canManage">
            <AttributeFormDialog
                :open="attributeForm.open"
                :attribute="attributeForm.attribute"
                @close="attributeForm.open = false"
                @saved="(item, created) => onSaved(item.name, created)"
            />
            <OptionFormDialog
                :open="optionForm.open"
                :option="optionForm.option"
                @close="optionForm.open = false"
                @saved="(item, created) => onSaved(item.name, created)"
            />
            <DeleteCatalogItemDialog
                :item="deleting?.item ?? null"
                :kind="deleting?.kind ?? 'attribute'"
                :remove="removeItem"
                @close="deleting = null"
                @deleted="onDeleted"
            />
        </template>
    </div>
</template>
