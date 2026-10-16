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
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import RichTextEditor from '../../editor/components/RichTextEditor.vue';
import { htmlToText } from '../../editor/richText';
import { categoriesApi } from '../../categories/api';
import type { CategoryNode } from '../../categories/types';
import MediaImageField from '../../media/components/MediaImageField.vue';
import CategoriesField from '../../products/components/CategoriesField.vue';
import TagsInput from '../../products/components/TagsInput.vue';
import type { MediaPicture } from '../../media/types';
import { fromLocalInput, toLocalInput } from '../../products/variants';
import { slugify } from '../../seo/analysis';
import SeoPanel from '../../seo/components/SeoPanel.vue';
import { postsApi } from '../api';
import VersionHistoryDialog from '../../pages/components/VersionHistoryDialog.vue';
import type { PageRevision } from '../../pages/types';
import type { Post, PostAuthor, PostAutosave, PostStatus, SavePostPayload } from '../types';

/** Create (`id` null) or edit a blog post. Every save keeps a version, shown under History. */
const props = defineProps<{ id: number | null }>();

/** Days a post stays in the trash (Post::TRASH_DAYS). */
const TRASH_DAYS = 30;
/** How often unsaved changes are autosaved. */
const AUTOSAVE_MS = 30_000;

const { t } = useI18n();
const { formatDate, formatDateTime, formatRelativeTime } = useLocale();
const toast = useToast();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManagePosts));

const FIELDS = [
    'title',
    'slug',
    'excerpt',
    'content',
    'status',
    'published_at',
    'meta_title',
    'meta_description',
    'focus_keyphrase',
    'image_id',
    'robots_noindex',
    'canonical_url',
    'og_title',
    'og_description',
    'og_image_id',
    'author_id',
    'comments_enabled',
    'category_ids',
    'primary_category_id',
    'tags',
] as const;
const form = useApiForm(FIELDS);

// ---- the post being edited ----

const post = ref<Post | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);

const fields = reactive({
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    status: 'draft' as PostStatus,
    publishedAt: '',
    metaTitle: '',
    metaDescription: '',
    focusKeyphrase: '',
    robotsNoindex: false,
    canonicalUrl: '',
    ogTitle: '',
    ogDescription: '',
    authorId: null as number | null,
    commentsEnabled: true,
    categoryIds: [] as number[],
    primaryCategoryId: null as number | null,
    tags: [] as string[],
});
const blogTree = ref<CategoryNode[]>([]);
const authors = ref<PostAuthor[]>([]);
const image = ref<MediaPicture | null>(null);
const ogImage = ref<MediaPicture | null>(null);
const slugEdited = ref(false);

function fill(loaded: Post | null): void {
    post.value = loaded;
    fields.title = loaded?.title ?? '';
    fields.slug = loaded?.slug ?? '';
    fields.excerpt = loaded?.excerpt ?? '';
    fields.content = loaded?.content ?? '';
    fields.status = loaded?.status ?? 'draft';
    fields.publishedAt = toLocalInput(loaded?.published_at ?? null);
    fields.metaTitle = loaded?.meta_title ?? '';
    fields.metaDescription = loaded?.meta_description ?? '';
    fields.focusKeyphrase = loaded?.focus_keyphrase ?? '';
    fields.robotsNoindex = loaded?.robots_noindex ?? false;
    fields.canonicalUrl = loaded?.canonical_url ?? '';
    fields.ogTitle = loaded?.og_title ?? '';
    fields.ogDescription = loaded?.og_description ?? '';
    fields.authorId = loaded?.author?.id ?? null;
    fields.commentsEnabled = loaded?.comments_enabled ?? true;
    fields.categoryIds = loaded?.categories.map((category) => category.id) ?? [];
    fields.primaryCategoryId = loaded?.categories.find((category) => category.is_primary)?.id ?? null;
    fields.tags = loaded ? [...loaded.tags] : [];
    image.value = loaded?.image ?? null;
    ogImage.value = loaded?.og_image ?? null;
    slugEdited.value = loaded !== null;
    pendingAutosave.value = loaded?.autosave ?? null;
}

/**
 * Loads a saved version or an autosave into the form as unsaved changes. Status and publish date
 * stay as they are.
 */
function applyVersion(version: PageRevision | PostAutosave): void {
    fields.title = version.title ?? '';
    fields.slug = version.slug ?? '';
    fields.excerpt = version.excerpt ?? '';
    fields.content = version.content ?? '';
    fields.metaTitle = version.meta_title ?? '';
    fields.metaDescription = version.meta_description ?? '';
    fields.focusKeyphrase = version.focus_keyphrase ?? '';
    fields.robotsNoindex = version.robots_noindex;
    fields.canonicalUrl = version.canonical_url ?? '';
    fields.ogTitle = version.og_title ?? '';
    fields.ogDescription = version.og_description ?? '';
    image.value = version.image;
    ogImage.value = version.og_image;
    slugEdited.value = true;
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const [loaded, tree, staff] = await Promise.all([
            props.id === null ? Promise.resolve(null) : postsApi.show(props.id),
            categoriesApi.tree('blog'),
            postsApi.authors(),
        ]);
        blogTree.value = tree;
        authors.value = staff;
        fill(loaded);
        snapshot.value = JSON.stringify(payload());
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

watch(
    () => fields.title,
    (title) => {
        if (!slugEdited.value) {
            fields.slug = slugify(title);
        }
    },
);

function onSlugInput(value: string): void {
    fields.slug = value;
    slugEdited.value = true;
}

// ---- payload, unsaved changes ----

const optional = (value: string): string | null => (value.trim() === '' ? null : value.trim());

function payload(): SavePostPayload {
    return {
        title: fields.title.trim(),
        slug: fields.slug,
        excerpt: optional(fields.excerpt),
        content: optional(fields.content),
        status: fields.status,
        published_at: fields.status === 'published' ? fromLocalInput(fields.publishedAt) : null,
        meta_title: optional(fields.metaTitle),
        meta_description: optional(fields.metaDescription),
        focus_keyphrase: optional(fields.focusKeyphrase),
        image_id: image.value?.id ?? null,
        robots_noindex: fields.robotsNoindex,
        canonical_url: optional(fields.canonicalUrl),
        og_title: optional(fields.ogTitle),
        og_description: optional(fields.ogDescription),
        og_image_id: ogImage.value?.id ?? null,
        author_id: fields.authorId,
        comments_enabled: fields.commentsEnabled,
        category_ids: fields.categoryIds,
        primary_category_id: fields.primaryCategoryId,
        tags: fields.tags,
    };
}

const snapshot = ref('');

/** The editor tidies the HTML it loads: that is not a change by the admin, so the baseline takes it. */
function acceptNormalized(): void {
    if (snapshot.value === '') {
        return;
    }

    const baseline = JSON.parse(snapshot.value) as SavePostPayload;
    baseline.content = payload().content;
    snapshot.value = JSON.stringify(baseline);
}

const inTrash = computed(() => !!post.value?.deleted_at);
const editable = computed(() => canManage.value && !inTrash.value);
const dirty = computed(
    () => editable.value && !loading.value && snapshot.value !== '' && JSON.stringify(payload()) !== snapshot.value,
);

/** Errors without a field on the post go in the alert at the top. */
const alertMessage = computed(() => {
    const keys = Object.keys(form.lastError.value?.fieldErrors ?? {});
    const placed = (key: string): boolean => (FIELDS as readonly string[]).includes(key);

    return keys.length > 0 && keys.every(placed) && form.lastError.value?.code === 'validation_failed'
        ? null
        : form.generalError.value;
});

// ---- saving ----

async function save(): Promise<void> {
    const existing = post.value;
    const saved = await form.submit(() =>
        existing ? postsApi.update(existing.id, payload()) : postsApi.create(payload()),
    );

    if (!saved) {
        toast.show(t('admin.posts.saveFailed'), 'danger');

        return;
    }

    fill(saved);
    snapshot.value = JSON.stringify(payload());
    // The server deleted this staff member's autosave with the save.
    lastAutosaved = '';
    autosaveState.value = 'idle';
    toast.show(t(existing ? 'admin.posts.saved' : 'admin.posts.created', { name: saved.title }));

    if (!existing) {
        await router.replace({ name: 'post', params: { id: saved.id } });
    }
}

// ---- history ----

const historyOpen = ref(false);
const loadRevisions = () => postsApi.revisions(post.value?.id ?? 0);
const loadRevision = (revisionId: number) => postsApi.revision(post.value?.id ?? 0, revisionId);

function restoreRevision(revision: PageRevision): void {
    applyVersion(revision);
    historyOpen.value = false;
    toast.show(t('admin.posts.history.restored', { date: formatDateTime(revision.created_at ?? '') }));
}

// ---- autosave ----

/** The viewer's unsaved form from an earlier visit, offered as Restore / Discard. */
const pendingAutosave = ref<PostAutosave | null>(null);
const autosaveState = ref<'idle' | 'saving' | 'saved' | 'failed'>('idle');
const autosavedAt = ref<string | null>(null);
/** What was last autosaved, so an unchanged form isn't sent again. */
let lastAutosaved = '';

/** Sends the form when it has changes not yet autosaved (existing pages only). */
async function autosaveNow(): Promise<void> {
    const current = post.value;
    const body = payload();
    const serialized = JSON.stringify(body);

    if (
        !current ||
        !dirty.value ||
        form.processing.value ||
        autosaveState.value === 'saving' ||
        serialized === lastAutosaved
    ) {
        return;
    }

    autosaveState.value = 'saving';

    try {
        autosavedAt.value = (await postsApi.autosave(current.id, body)).saved_at;
        lastAutosaved = serialized;
        autosaveState.value = 'saved';
    } catch {
        autosaveState.value = 'failed';
    }
}

const autosaveTimer = window.setInterval(() => void autosaveNow(), AUTOSAVE_MS);
onBeforeUnmount(() => window.clearInterval(autosaveTimer));

function restoreAutosave(): void {
    if (pendingAutosave.value) {
        applyVersion(pendingAutosave.value);
        pendingAutosave.value = null;
        toast.show(t('admin.posts.autosave.restored'));
    }
}

async function discardAutosave(): Promise<void> {
    if (!post.value) {
        return;
    }

    try {
        await postsApi.discardAutosave(post.value.id);
        pendingAutosave.value = null;
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

// ---- preview ----

const previewing = ref(false);

/**
 * Opens the storefront's preview of the post as it is in the form (unsaved changes are autosaved
 * first). Without a storefront preview address, the token is copied instead.
 */
async function openPreview(): Promise<void> {
    if (!post.value) {
        return;
    }

    // Opened right away (inside the click), so browsers don't block it as a pop-up.
    const tab = window.open('', '_blank');
    previewing.value = true;

    try {
        await autosaveNow();
        const preview = await postsApi.preview(post.value.id);

        if (preview.url && tab) {
            tab.opener = null;
            tab.location.href = preview.url;
        } else {
            tab?.close();
            await navigator.clipboard?.writeText(preview.token).catch(() => undefined);
            toast.show(t(preview.url ? 'admin.posts.preview.blocked' : 'admin.posts.preview.copied'));
        }
    } catch (caught) {
        tab?.close();
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        previewing.value = false;
    }
}

// ---- delete / restore ----

const confirmDelete = ref(false);

async function remove(): Promise<void> {
    if (!post.value) {
        return;
    }

    try {
        await postsApi.remove(post.value.id);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');

        return;
    }

    snapshot.value = JSON.stringify(payload());
    toast.show(t('admin.posts.deleted', { name: post.value.title }));
    await router.push({ name: 'posts' });
}

async function restore(): Promise<void> {
    if (!post.value) {
        return;
    }

    try {
        fill(await postsApi.restore(post.value.id));
        snapshot.value = JSON.stringify(payload());
        toast.show(t('admin.posts.restored', { name: fields.title }));
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

const purgeDate = computed(() =>
    post.value?.deleted_at
        ? new Date(new Date(post.value.deleted_at).getTime() + TRASH_DAYS * 86_400_000).toISOString()
        : null,
);

// ---- leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    (to) =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value || to.name === 'post') {
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

// ---- post ----

const statusOptions = computed(() => [
    { value: 'draft' as const, label: t('admin.posts.status.draft') },
    { value: 'published' as const, label: t('admin.posts.status.published') },
]);
const isScheduled = computed(
    () => fields.status === 'published' && fields.publishedAt !== '' && new Date(fields.publishedAt) > new Date(),
);
const stateTone = computed(() =>
    post.value?.state === 'published' ? 'success' : post.value?.state === 'scheduled' ? 'warning' : 'neutral',
);

const cardClass = 'flex flex-col gap-4.5 rounded-2xl border border-line bg-surface p-5 sm:p-6';
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-wrap items-center gap-3">
            <RouterLink
                :to="{ name: 'posts' }"
                class="flex items-center gap-1.5 rounded-lg text-sm font-medium text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            >
                <AppIcon name="arrowForward" :size="16" flip-in-rtl class="rotate-180" />
                {{ t('admin.posts.backToList') }}
            </RouterLink>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                    <h1 class="truncate text-2xl font-bold">{{ post ? post.title : t('admin.posts.newTitle') }}</h1>
                    <AppBadge v-if="inTrash" tone="danger">{{ t('admin.posts.trashBadge') }}</AppBadge>
                    <AppBadge v-else-if="post" :tone="stateTone">{{ t(`admin.posts.state.${post.state}`) }}</AppBadge>
                </div>
                <p v-if="post?.updated_by && post.updated_at" class="text-xs text-fg-muted">
                    {{
                        t('admin.posts.lastEdited', {
                            name: post.updated_by.name,
                            time: formatRelativeTime(post.updated_at),
                        })
                    }}
                </p>
            </div>
            <div v-if="post" class="flex flex-wrap gap-2">
                <AppButton v-if="!inTrash" variant="secondary" size="md" :loading="previewing" @click="openPreview">
                    <AppIcon name="eye" :size="18" />
                    {{ t('admin.posts.preview.open') }}
                </AppButton>
                <AppButton variant="secondary" size="md" @click="historyOpen = true">
                    <AppIcon name="history" :size="18" />
                    {{ t('admin.posts.history.open') }}
                </AppButton>
                <AppButton v-if="canManage && inTrash" variant="secondary" size="md" @click="restore">
                    <AppIcon name="refresh" :size="18" />
                    {{ t('admin.posts.restore') }}
                </AppButton>
                <AppButton v-else-if="canManage" variant="danger-outline" size="md" @click="confirmDelete = true">
                    <AppIcon name="trash" :size="18" />
                    {{ t('admin.posts.delete') }}
                </AppButton>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <div v-else-if="loadError" class="flex flex-col gap-3">
            <FormAlert :message="loadError" />
            <div>
                <AppButton variant="secondary" size="md" @click="load">{{ t('common.retry') }}</AppButton>
            </div>
        </div>

        <form v-else id="post-form" class="flex min-w-0 flex-col gap-5" novalidate @submit.prevent="save">
            <FormAlert
                v-if="inTrash && purgeDate"
                :message="t('admin.posts.trashNotice', { date: formatDate(purgeDate) })"
            />
            <FormAlert v-if="alertMessage" :message="alertMessage" />

            <div
                v-if="pendingAutosave && editable"
                role="status"
                class="flex flex-wrap items-center gap-3 rounded-xl border border-warning/40 bg-warning-soft px-4 py-3 text-sm"
            >
                <AppIcon name="history" :size="18" class="shrink-0 text-warning" />
                <span class="grow">{{
                    t('admin.posts.autosave.found', {
                        time: pendingAutosave.saved_at ? formatDateTime(pendingAutosave.saved_at) : '',
                    })
                }}</span>
                <AppButton size="md" @click="restoreAutosave">{{ t('admin.posts.autosave.restore') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="discardAutosave">{{
                    t('admin.posts.autosave.discard')
                }}</AppButton>
            </div>

            <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
                <section :class="cardClass" aria-labelledby="post-content-title">
                    <h2 id="post-content-title" class="text-lg font-bold">
                        {{ t('admin.posts.sections.content') }}
                    </h2>
                    <TextField
                        v-model="fields.title"
                        :label="t('admin.posts.postTitle')"
                        autocomplete="off"
                        :error="form.errors.title"
                    />
                    <TextField
                        :model-value="fields.slug"
                        :label="t('admin.posts.slug')"
                        :hint="t('admin.posts.slugHint')"
                        dir="ltr"
                        autocomplete="off"
                        :error="form.errors.slug"
                        @update:model-value="onSlugInput"
                    />
                    <RichTextEditor
                        v-model="fields.content"
                        :label="t('admin.posts.content')"
                        optional
                        :error="form.errors.content"
                        @normalized="acceptNormalized"
                    />
                </section>

                <section :class="[cardClass, 'self-start']" aria-labelledby="post-summary-title">
                    <h2 id="post-summary-title" class="text-lg font-bold">
                        {{ t('admin.posts.sections.summary') }}
                    </h2>
                    <MediaImageField
                        v-model="image"
                        :label="t('admin.posts.image')"
                        :hint="t('admin.posts.imageHint')"
                        :error="form.errors.image_id"
                    />
                    <TextAreaField
                        v-model="fields.excerpt"
                        :label="t('admin.posts.excerpt')"
                        :hint="t('admin.posts.excerptHint')"
                        optional
                        :rows="4"
                        :maxlength="500"
                        :error="form.errors.excerpt"
                    />
                </section>
            </div>

            <section :class="cardClass" aria-labelledby="post-organization-title">
                <h2 id="post-organization-title" class="text-lg font-bold">
                    {{ t('admin.posts.sections.organization') }}
                </h2>
                <CategoriesField
                    v-model:ids="fields.categoryIds"
                    v-model:primary-id="fields.primaryCategoryId"
                    :tree="blogTree"
                    :hint="t('admin.posts.categoriesHint')"
                    :error="form.errors.category_ids ?? form.errors.primary_category_id"
                />
                <p v-if="blogTree.length === 0" class="text-[13px] text-fg-muted">
                    {{ t('admin.posts.noBlogCategories') }}
                    <RouterLink :to="{ name: 'categories', query: { type: 'blog' } }" class="text-primary underline">{{
                        t('admin.posts.manageBlogCategories')
                    }}</RouterLink>
                </p>
                <TagsInput v-model="fields.tags" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="post-author" class="mb-2 block text-sm font-semibold">{{
                            t('admin.posts.author')
                        }}</label>
                        <select
                            id="post-author"
                            :value="fields.authorId === null ? '' : String(fields.authorId)"
                            class="h-13 w-full cursor-pointer rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft"
                            @change="
                                fields.authorId =
                                    ($event.target as HTMLSelectElement).value === ''
                                        ? null
                                        : Number(($event.target as HTMLSelectElement).value)
                            "
                        >
                            <option v-if="!post" value="">{{ t('admin.posts.authorMe') }}</option>
                            <option v-for="staff in authors" :key="staff.id" :value="String(staff.id)">
                                {{ staff.name
                                }}{{ staff.has_byline ? ` (${staff.author_name})` : ` — ${t('admin.posts.noByline')}` }}
                            </option>
                        </select>
                        <p v-if="form.errors.author_id" class="mt-2 text-[13px] text-danger">
                            {{ form.errors.author_id }}
                        </p>
                        <p v-else class="mt-2 text-[13px] leading-relaxed text-fg-muted">
                            {{ t('admin.posts.authorHint') }}
                        </p>
                    </div>
                    <label
                        class="flex cursor-pointer items-start gap-2.5 self-start rounded-xl border border-line p-3.5 sm:mt-7"
                    >
                        <input
                            v-model="fields.commentsEnabled"
                            type="checkbox"
                            class="mt-1 size-4 accent-[var(--color-primary)]"
                        />
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ t('admin.posts.commentsEnabled') }}</span>
                            <span class="text-[13px] text-fg-muted">{{ t('admin.posts.commentsEnabledHint') }}</span>
                            <RouterLink
                                v-if="id !== null && auth.can(Permission.ModerateComments)"
                                :to="{ name: 'post-comments', query: { item: id, status: 'all' } }"
                                class="mt-1 self-start rounded text-[13px] font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                >{{ t('admin.posts.viewComments') }}</RouterLink
                            >
                        </span>
                    </label>
                </div>
            </section>

            <section :class="cardClass" aria-labelledby="post-social-title">
                <div class="flex flex-col gap-1">
                    <h2 id="post-social-title" class="text-lg font-bold">{{ t('admin.posts.social.title') }}</h2>
                    <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.posts.social.intro') }}</p>
                </div>
                <div class="grid gap-5 lg:grid-cols-[1fr_auto]">
                    <div class="flex min-w-0 flex-col gap-4.5">
                        <TextField
                            v-model="fields.ogTitle"
                            :label="t('admin.posts.social.ogTitle')"
                            :hint="t('admin.posts.social.ogTitleHint')"
                            :placeholder="fields.metaTitle || fields.title"
                            optional
                            autocomplete="off"
                            :error="form.errors.og_title"
                        />
                        <TextAreaField
                            v-model="fields.ogDescription"
                            :label="t('admin.posts.social.ogDescription')"
                            :hint="t('admin.posts.social.ogDescriptionHint')"
                            optional
                            :rows="3"
                            :maxlength="500"
                            :error="form.errors.og_description"
                        />
                    </div>
                    <MediaImageField
                        v-model="ogImage"
                        :label="t('admin.posts.social.ogImage')"
                        :hint="t('admin.posts.social.ogImageHint')"
                        :error="form.errors.og_image_id"
                    />
                </div>
                <TextField
                    v-model="fields.canonicalUrl"
                    :label="t('admin.posts.social.canonical')"
                    :hint="t('admin.posts.social.canonicalHint')"
                    placeholder="https://"
                    dir="ltr"
                    optional
                    autocomplete="off"
                    :error="form.errors.canonical_url"
                />
                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line p-3.5">
                    <input
                        v-model="fields.robotsNoindex"
                        type="checkbox"
                        class="mt-1 size-4 accent-[var(--color-primary)]"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span class="text-sm font-semibold">{{ t('admin.posts.social.noindex') }}</span>
                        <span class="text-[13px] text-fg-muted">{{ t('admin.posts.social.noindexHint') }}</span>
                    </span>
                </label>
            </section>

            <SeoPanel
                v-model:keyphrase="fields.focusKeyphrase"
                v-model:meta-title="fields.metaTitle"
                v-model:meta-description="fields.metaDescription"
                type="post"
                :name="fields.title"
                :slug="fields.slug"
                :description="htmlToText(fields.content)"
                :has-image="image !== null"
                :subject-id="post?.id ?? null"
                :keyphrase-used-by="[]"
                :errors="{
                    focus_keyphrase: form.errors.focus_keyphrase,
                    meta_title: form.errors.meta_title,
                    meta_description: form.errors.meta_description,
                }"
            />
        </form>

        <!-- Sticky save bar: status, publish date and Save -->
        <div
            v-if="!loading && !loadError && editable"
            class="sticky bottom-0 z-30 -mx-4 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur sm:mx-0 sm:rounded-t-2xl sm:border-x"
        >
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-64">
                    <SegmentedControl
                        v-model="fields.status"
                        :label="t('admin.posts.statusLabel')"
                        :options="statusOptions"
                    />
                </div>
                <div v-if="fields.status === 'published'" class="flex flex-wrap items-center gap-2">
                    <label for="post-published-at" class="text-xs text-fg-muted">{{
                        t('admin.posts.publishAt')
                    }}</label>
                    <input
                        id="post-published-at"
                        v-model="fields.publishedAt"
                        type="datetime-local"
                        :title="t('admin.posts.publishAtHint')"
                        class="h-10 rounded-lg border border-line-strong bg-surface px-2.5 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft"
                    />
                    <AppBadge v-if="isScheduled" tone="warning">
                        {{
                            t('admin.posts.scheduledFor', {
                                date: formatDateTime(fromLocalInput(fields.publishedAt) ?? ''),
                            })
                        }}
                    </AppBadge>
                </div>
                <span class="grow" />
                <span v-if="autosaveState === 'saving'" class="text-xs text-fg-muted">{{
                    t('admin.posts.autosave.saving')
                }}</span>
                <span v-else-if="autosaveState === 'failed'" class="text-xs text-danger">{{
                    t('admin.posts.autosave.failed')
                }}</span>
                <span v-else-if="autosaveState === 'saved' && dirty && autosavedAt" class="text-xs text-fg-muted">{{
                    t('admin.posts.autosave.saved', { time: formatDateTime(autosavedAt) })
                }}</span>
                <span v-if="dirty" class="text-xs text-warning">{{ t('admin.posts.unsaved') }}</span>
                <AppButton type="submit" form="post-form" size="md" :loading="form.processing.value">
                    {{ post ? t('admin.posts.save') : t('admin.posts.create') }}
                </AppButton>
            </div>
        </div>

        <VersionHistoryDialog
            v-if="post"
            :open="historyOpen"
            :load-revisions="loadRevisions"
            :load-revision="loadRevision"
            :can-restore="editable"
            @close="historyOpen = false"
            @restore="restoreRevision"
        />

        <AppDialog
            :open="confirmDelete"
            :title="t('admin.posts.deleteTitle', { name: post?.title ?? '' })"
            icon="trash"
            tone="danger"
            @close="confirmDelete = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.posts.deleteText', { days: TRASH_DAYS }) }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="remove">{{ t('admin.posts.deleteConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="confirmDelete = false">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.posts.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.posts.leaveText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="leave">{{ t('admin.posts.leaveConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="stay">{{ t('admin.posts.leaveStay') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
