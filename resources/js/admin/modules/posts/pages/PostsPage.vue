<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import type { CategoryNode } from '../../categories/types';
import { ratingOf } from '../../seo/analysis';
import SeoScoreDot from '../../seo/components/SeoScoreDot.vue';
import { postsApi } from '../api';
import type { PostAuthor, PostFilters, PostListItem, PostState } from '../types';

type StateFilter = 'all' | PostState;
type Sort = 'updated' | 'newest' | 'title';

const { t } = useI18n();
const { formatNumber, formatRelativeTime } = useLocale();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManagePosts));

const result = ref<Paginated<PostListItem> | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

// Filters live in the URL so a filtered list can be bookmarked and survives back/forward.
const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

function updateQuery(changes: Record<string, string | undefined>, keepPage = false): void {
    void router.replace({ query: { ...route.query, ...changes, ...(keepPage ? {} : { page: undefined }) } });
}

function queryFilter<T extends string>(key: string, allowed: readonly T[], fallback: T) {
    return computed<T>({
        get: () => (allowed.includes(queryString(key) as T) ? (queryString(key) as T) : fallback),
        set: (value) => updateQuery({ [key]: value === fallback ? undefined : value }),
    });
}

const search = computed({ get: () => queryString('q'), set: (q: string) => updateQuery({ q: q || undefined }) });
const state = queryFilter<StateFilter>('status', ['all', 'draft', 'scheduled', 'published'], 'all');
const sort = queryFilter<Sort>('sort', ['updated', 'newest', 'title'], 'updated');
const needsSeo = computed({
    get: () => queryString('needs_seo') === '1',
    set: (on: boolean) => updateQuery({ needs_seo: on ? '1' : undefined }),
});
const trashed = computed({
    get: () => queryString('trashed') === '1',
    set: (on: boolean) => updateQuery({ trashed: on ? '1' : undefined }),
});
const page = computed(() => Math.max(1, Number(queryString('page')) || 1));
const category = computed({
    get: () => queryString('category') || 'all',
    set: (value: string) => updateQuery({ category: value === 'all' ? undefined : value }),
});
const author = computed({
    get: () => queryString('author') || 'all',
    set: (value: string) => updateQuery({ author: value === 'all' ? undefined : value }),
});
/** Set by the Tags page ("N posts"); shown as a removable chip. */
const tagId = computed(() => Number(queryString('tag')) || null);
const filtered = computed(
    () =>
        !!search.value ||
        state.value !== 'all' ||
        needsSeo.value ||
        category.value !== 'all' ||
        author.value !== 'all' ||
        tagId.value !== null,
);

const blogTree = ref<CategoryNode[]>([]);
const authors = ref<PostAuthor[]>([]);
const categoryOptions = computed(() => [
    { value: 'all', label: t('admin.posts.all') },
    ...flatten(blogTree.value).map((item) => ({
        value: String(item.node.id),
        label: `${'— '.repeat(item.depth)}${item.node.name}`,
    })),
]);
const authorOptions = computed(() => [
    { value: 'all', label: t('admin.posts.all') },
    ...authors.value.map((item) => ({ value: String(item.id), label: item.author_name ?? item.name })),
]);

// Reference data for the filters; the list works without it.
void Promise.all([categoriesApi.tree('blog'), postsApi.authors()])
    .then(([tree, staff]) => {
        blogTree.value = tree;
        authors.value = staff;
    })
    .catch(() => undefined);

const stateOptions = computed(() => [
    { value: 'all' as const, label: t('admin.posts.all') },
    { value: 'published' as const, label: t('admin.posts.state.published') },
    { value: 'scheduled' as const, label: t('admin.posts.state.scheduled') },
    { value: 'draft' as const, label: t('admin.posts.state.draft') },
]);
const sortOptions = computed(() => [
    { value: 'updated' as const, label: t('admin.posts.sort.updated') },
    { value: 'newest' as const, label: t('admin.posts.sort.newest') },
    { value: 'title' as const, label: t('admin.posts.sort.title') },
]);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    const filters: PostFilters = {
        q: search.value || undefined,
        status: state.value === 'all' ? undefined : state.value,
        needs_seo: needsSeo.value ? 1 : undefined,
        category_id: category.value === 'all' ? undefined : Number(category.value),
        author_id: author.value === 'all' ? undefined : Number(author.value),
        tag_id: tagId.value ?? undefined,
        trashed: trashed.value ? 1 : undefined,
        sort: sort.value,
        page: page.value,
    };

    try {
        result.value = await postsApi.list(filters);
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(() => route.query, load, { immediate: true });

async function restore(item: PostListItem): Promise<void> {
    try {
        await postsApi.restore(item.id);
        toast.show(t('admin.posts.restored', { name: item.title }));
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

const stateTone = (item: PostListItem): 'success' | 'neutral' | 'warning' =>
    item.state === 'published' ? 'success' : item.state === 'scheduled' ? 'warning' : 'neutral';

function seoLabel(score: number | null): string {
    const rating = ratingOf(score);

    return rating === null
        ? t('admin.seo.notAnalysed')
        : t('admin.seo.scoreLabel', { score: formatNumber(score ?? 0), rating: t(`admin.seo.rating.${rating}`) });
}

const toggleClass = (on: boolean): string[] => [
    'flex h-11 cursor-pointer items-center gap-2 rounded-[10px] border px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary bg-primary-soft text-fg' : 'border-line-strong bg-surface text-fg-muted hover:text-fg',
];
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.posts.title') }}</h1>
                <p class="text-sm text-fg-muted">
                    {{ t('admin.posts.intro') }}
                    · {{ t('admin.posts.count', { n: formatNumber(result?.meta.total ?? 0) }) }}
                </p>
            </div>
            <AppButton v-if="canManage" size="md" @click="router.push({ name: 'post-create' })">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.posts.new') }}
            </AppButton>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.posts.search')" />
            <div class="w-full sm:w-auto sm:min-w-96">
                <SegmentedControl v-model="state" :label="t('admin.posts.statusLabel')" :options="stateOptions" />
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <SelectField v-model="category" :label="t('admin.posts.category')" :options="categoryOptions" />
            <SelectField v-model="author" :label="t('admin.posts.author')" :options="authorOptions" />
            <SelectField v-model="sort" :label="t('admin.posts.sortLabel')" :options="sortOptions" />
            <button
                v-if="tagId"
                type="button"
                :class="toggleClass(true)"
                :aria-label="t('admin.posts.clearTagFilter')"
                @click="updateQuery({ tag: undefined })"
            >
                {{ t('admin.posts.tagFilter') }}
                <AppIcon name="close" :size="14" />
            </button>
            <button type="button" :aria-pressed="needsSeo" :class="toggleClass(needsSeo)" @click="needsSeo = !needsSeo">
                <span class="size-2.5 rounded-full bg-warning" aria-hidden="true" />
                {{ t('admin.seo.needsWork') }}
            </button>
            <button type="button" :aria-pressed="trashed" :class="toggleClass(trashed)" @click="trashed = !trashed">
                <AppIcon name="trash" :size="16" />
                {{ t('admin.posts.showTrash') }}
            </button>
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
                    v-for="row in 5"
                    :key="row"
                    class="h-18 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="result.data.length === 0"
                :icon="search ? 'search' : trashed ? 'trash' : 'file'"
                :title="
                    filtered
                        ? t('admin.posts.noResults')
                        : trashed
                          ? t('admin.posts.emptyTrash')
                          : t('admin.posts.empty')
                "
                :description="filtered || trashed ? undefined : t('admin.posts.emptyText')"
            >
                <AppButton
                    v-if="canManage && !filtered && !trashed"
                    size="md"
                    @click="router.push({ name: 'post-create' })"
                >
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.posts.new') }}
                </AppButton>
            </EmptyState>

            <template v-else>
                <ul class="divide-y divide-line">
                    <li
                        v-for="item in result.data"
                        :key="item.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 hover:bg-surface-muted/40 sm:flex-nowrap"
                    >
                        <span
                            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-surface-muted text-fg-muted"
                        >
                            <img
                                v-if="item.image?.thumb ?? item.image?.original"
                                :src="item.image?.thumb ?? item.image?.original"
                                alt=""
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <AppIcon v-else name="file" :size="20" />
                        </span>
                        <span class="flex min-w-0 grow basis-48 flex-col gap-0.5">
                            <span class="flex items-center gap-2">
                                <SeoScoreDot
                                    :rating="ratingOf(item.seo_score)"
                                    :label="seoLabel(item.seo_score)"
                                    size="sm"
                                    class="shrink-0"
                                />
                                <RouterLink
                                    :to="{ name: 'post', params: { id: item.id } }"
                                    :title="item.title"
                                    class="truncate rounded font-semibold text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                >
                                    {{ item.title }}
                                </RouterLink>
                            </span>
                            <span class="truncate text-xs text-fg-muted">
                                {{
                                    [item.primary_category?.name, item.author?.author_name ?? item.author?.name]
                                        .filter(Boolean)
                                        .join(' · ') || '—'
                                }}
                            </span>
                        </span>
                        <AppBadge v-if="item.deleted_at" tone="danger" class="shrink-0">{{
                            t('admin.posts.trashBadge')
                        }}</AppBadge>
                        <AppBadge v-else :tone="stateTone(item)" class="shrink-0">{{
                            t(`admin.posts.state.${item.state}`)
                        }}</AppBadge>
                        <span class="flex shrink-0 flex-col text-xs text-fg-muted sm:w-40 sm:text-end">
                            <span>{{ item.updated_at ? formatRelativeTime(item.updated_at) : '—' }}</span>
                            <span v-if="item.updated_by" class="truncate">{{
                                t('admin.posts.updatedBy', { name: item.updated_by.name })
                            }}</span>
                        </span>
                        <AppButton
                            v-if="item.deleted_at && canManage"
                            variant="secondary"
                            size="md"
                            class="shrink-0"
                            @click="restore(item)"
                        >
                            <AppIcon name="refresh" :size="16" />
                            {{ t('admin.posts.restore') }}
                        </AppButton>
                    </li>
                </ul>

                <AppPagination :meta="result.meta" @change="updateQuery({ page: String($event) }, true)" />
            </template>
        </section>
    </div>
</template>
