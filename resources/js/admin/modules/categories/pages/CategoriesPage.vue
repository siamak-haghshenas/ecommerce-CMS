<script setup lang="ts">
import { computed, provide, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { categoriesApi } from '../api';
import CategoryFormDialog from '../components/CategoryFormDialog.vue';
import CategoryRow from '../components/CategoryRow.vue';
import CategoryTreeList from '../components/CategoryTreeList.vue';
import DeleteCategoryDialog from '../components/DeleteCategoryDialog.vue';
import MoveCategoryDialog from '../components/MoveCategoryDialog.vue';
import { categoryTreeKey, type CategoryAction } from '../context';
import { ratingOf } from '../../seo/analysis';
import { childrenOf, flatten } from '../tree';
import type { Category, CategoryNode } from '../types';

const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const auth = useAdminAuthStore();

const canManage = computed(() => auth.can(Permission.ManageCategories));

const tree = ref<CategoryNode[] | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const search = ref('');
/** Show only categories whose stored SEO score is not green (or not analysed yet). */
const needsSeo = ref(false);
const dragging = ref(false);
/** Collapsed rather than expanded ids, so new categories show up open. */
const collapsed = reactive(new Set<number>());

const all = computed(() => flatten(tree.value ?? []));
const total = computed(() => all.value.length);

/** Searching or filtering shows a flat list of matches with their path; reordering is offered in the tree only. */
const results = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '' && !needsSeo.value) {
        return null;
    }

    return all.value.filter(
        ({ node }) =>
            (query === '' || node.name.toLowerCase().includes(query) || node.slug.includes(query)) &&
            (!needsSeo.value || ratingOf(node.seo_score) !== 'good'),
    );
});

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        tree.value = await categoriesApi.tree();
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void load();

function expandAll(): void {
    collapsed.clear();
}

function collapseAll(): void {
    for (const { node } of all.value) {
        if (node.children.length > 0) {
            collapsed.add(node.id);
        }
    }
}

/** Saves a new child order; the server's tree then replaces the local one. */
async function reorder(parentId: number | null, ids: number[]): Promise<void> {
    try {
        tree.value = await categoriesApi.reorder(parentId, ids);
        toast.show(t('admin.categories.orderSaved'));
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
        // The local tree already shows the drop; reload the real order.
        await load();
    }
}

function moveWithinSiblings(node: CategoryNode, offset: -1 | 1): void {
    const ids = childrenOf(tree.value ?? [], node.parent_id).map((sibling) => sibling.id);
    const index = ids.indexOf(node.id);
    const target = index + offset;

    if (index === -1 || target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target] as number, node.id];
    void reorder(node.parent_id, ids);
}

// ---- dialogs ----

const form = reactive({ open: false, categoryId: null as number | null, parentId: null as number | null });
const moving = ref<CategoryNode | null>(null);
const deleting = ref<CategoryNode | null>(null);

function openCreate(parentId: number | null = null): void {
    Object.assign(form, { open: true, categoryId: null, parentId });
}

async function toggleVisibility(node: CategoryNode): Promise<void> {
    try {
        // The update endpoint takes every field, so start from the full category.
        const category = await categoriesApi.show(node.id);
        await categoriesApi.update(node.id, {
            name: category.name,
            slug: category.slug,
            parent_id: category.parent_id,
            description: category.description,
            meta_title: category.meta_title,
            meta_description: category.meta_description,
            focus_keyphrase: category.focus_keyphrase,
            is_active: !category.is_active,
        });
        toast.show(
            t(category.is_active ? 'admin.categories.hiddenToast' : 'admin.categories.shownToast', { name: node.name }),
        );
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

function act(action: CategoryAction, node: CategoryNode): void {
    switch (action) {
        case 'edit':
            Object.assign(form, { open: true, categoryId: node.id, parentId: null });
            break;
        case 'addChild':
            if (canManage.value) {
                collapsed.delete(node.id);
                openCreate(node.id);
            }
            break;
        case 'moveUp':
            moveWithinSiblings(node, -1);
            break;
        case 'moveDown':
            moveWithinSiblings(node, 1);
            break;
        case 'moveTo':
            moving.value = node;
            break;
        case 'toggleVisibility':
            void toggleVisibility(node);
            break;
        case 'delete':
            deleting.value = node;
            break;
    }
}

function onSaved(category: Category, created: boolean): void {
    form.open = false;
    toast.show(t(created ? 'admin.categories.created' : 'admin.categories.saved', { name: category.name }));
    void load();
}

function onMoved(newTree: CategoryNode[], node: CategoryNode): void {
    moving.value = null;
    tree.value = newTree;
    toast.show(t('admin.categories.moved', { name: node.name }));
}

function onDeleted(node: CategoryNode): void {
    deleting.value = null;
    toast.show(t('admin.categories.deleted', { name: node.name }));
    void load();
}

provide(categoryTreeKey, {
    canManage,
    dragging,
    isCollapsed: (id) => collapsed.has(id),
    toggleCollapsed: (id) => (collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id)),
    act,
    reorder: (parentId, ids) => void reorder(parentId, ids),
});
</script>

<template>
    <div class="mx-auto flex max-w-5xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.categories.title') }}</h1>
                <p class="text-sm text-fg-muted">{{ t('admin.categories.count', { n: formatNumber(total) }) }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="openCreate()">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.categories.new') }}
            </AppButton>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.categories.search')" />
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
            <div v-if="tree && total > 0 && !results" class="flex gap-2">
                <AppButton variant="secondary" size="md" @click="expandAll">{{
                    t('admin.categories.expandAll')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="collapseAll">{{
                    t('admin.categories.collapseAll')
                }}</AppButton>
            </div>
        </div>

        <p
            v-if="canManage && tree && total > 1 && !results"
            class="hidden items-center gap-2.5 rounded-xl bg-surface-muted px-4 py-3 text-sm text-fg-muted lg:flex"
        >
            <AppIcon name="grip" :size="18" />
            {{ t('admin.categories.dragTip') }}
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
            :aria-label="t('admin.categories.title')"
            :class="[
                'rounded-2xl border border-line bg-surface p-2 transition-opacity',
                loading && tree && 'opacity-60',
            ]"
        >
            <div v-if="!tree" aria-hidden="true" class="flex flex-col gap-2 p-1">
                <div
                    v-for="row in 5"
                    :key="row"
                    class="h-14 animate-pulse rounded-xl bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="total === 0"
                icon="tag"
                :title="t('admin.categories.empty')"
                :description="t('admin.categories.emptyText')"
            >
                <AppButton v-if="canManage" size="md" @click="openCreate()">
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.categories.new') }}
                </AppButton>
            </EmptyState>

            <template v-else-if="results">
                <EmptyState
                    v-if="results.length === 0"
                    :icon="needsSeo && !search ? 'check' : 'search'"
                    :title="needsSeo && !search ? t('admin.seo.allGood') : t('admin.categories.noResults')"
                />
                <ul v-else class="flex flex-col">
                    <li v-for="item in results" :key="item.node.id">
                        <CategoryRow :node="item.node" :path="item.path" />
                    </li>
                </ul>
            </template>

            <CategoryTreeList v-else v-model:nodes="tree" :parent-id="null" :depth="0" />
        </section>

        <template v-if="canManage && tree">
            <CategoryFormDialog
                :open="form.open"
                :category-id="form.categoryId"
                :parent-id="form.parentId"
                :tree="tree"
                @close="form.open = false"
                @saved="onSaved"
            />
            <MoveCategoryDialog :node="moving" :tree="tree" @close="moving = null" @moved="onMoved" />
            <DeleteCategoryDialog :node="deleting" @close="deleting = null" @deleted="onDeleted" />
        </template>
    </div>
</template>
