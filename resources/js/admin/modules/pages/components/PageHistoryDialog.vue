<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { pagesApi } from '../api';
import type { PageRevision, PageRevisionSummary } from '../types';

/**
 * A page's saved versions, newest first. Choosing one shows it as it was saved; "Restore" hands it
 * to the editor, which loads it as unsaved changes (saving makes it a new version).
 */
const props = defineProps<{ open: boolean; pageId: number; canRestore: boolean }>();
const emit = defineEmits<{ close: []; restore: [revision: PageRevision] }>();

const KEPT = 20;

const { t } = useI18n();
const { formatDateTime, formatNumber } = useLocale();

const revisions = ref<PageRevisionSummary[] | null>(null);
const listError = ref<string | null>(null);
const selected = ref<PageRevision | null>(null);
const selectedId = ref<number | null>(null);
const loadingRevision = ref(false);
const revisionError = ref<string | null>(null);

async function loadList(): Promise<void> {
    revisions.value = null;
    listError.value = null;
    selected.value = null;
    selectedId.value = null;

    try {
        revisions.value = await pagesApi.revisions(props.pageId);

        if (revisions.value[0]) {
            void choose(revisions.value[0].id);
        }
    } catch (caught) {
        listError.value = toApiError(caught).message;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            void loadList();
        }
    },
);

async function choose(id: number): Promise<void> {
    selectedId.value = id;
    loadingRevision.value = true;
    revisionError.value = null;

    try {
        const loaded = await pagesApi.revision(props.pageId, id);

        // Ignore a slow answer for a version the admin has since moved away from.
        if (selectedId.value === id) {
            selected.value = loaded;
        }
    } catch (caught) {
        revisionError.value = toApiError(caught).message;
    } finally {
        loadingRevision.value = false;
    }
}

const hasSeoFields = computed(() => {
    const version = selected.value;

    return (
        (!!version &&
            [
                version.meta_title,
                version.meta_description,
                version.focus_keyphrase,
                version.og_title,
                version.og_description,
                version.og_image,
                version.canonical_url,
            ].some(Boolean)) ||
        !!version?.robots_noindex
    );
});

/** The newest version is what is saved now. */
const isCurrent = computed(() => selectedId.value !== null && selectedId.value === revisions.value?.[0]?.id);

function restore(): void {
    if (selected.value) {
        emit('restore', selected.value);
    }
}
</script>

<template>
    <AppDialog :open="open" :title="t('admin.pages.history.title')" icon="history" size="lg" @close="emit('close')">
        <div class="flex flex-col gap-4">
            <p class="text-[13px] leading-relaxed text-fg-muted">
                {{ t('admin.pages.history.intro', { n: formatNumber(KEPT) }) }}
            </p>

            <FormAlert v-if="listError" :message="listError" />
            <div v-else-if="!revisions" class="flex justify-center py-10"><AppSpinner /></div>
            <EmptyState v-else-if="revisions.length === 0" icon="history" :title="t('admin.pages.history.empty')" />

            <div v-else class="grid gap-4 md:grid-cols-[220px_1fr]">
                <ul
                    :aria-label="t('admin.pages.history.list')"
                    class="flex max-h-44 flex-col gap-1 overflow-y-auto md:max-h-[60vh]"
                >
                    <li v-for="(revision, index) in revisions" :key="revision.id">
                        <button
                            type="button"
                            :aria-current="selectedId === revision.id ? 'true' : undefined"
                            :class="[
                                'flex w-full cursor-pointer flex-col gap-0.5 rounded-lg border px-3 py-2 text-start focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                selectedId === revision.id
                                    ? 'border-primary bg-primary-soft'
                                    : 'border-transparent hover:bg-surface-muted',
                            ]"
                            @click="choose(revision.id)"
                        >
                            <span class="flex items-center gap-2 text-sm font-semibold">
                                {{ revision.created_at ? formatDateTime(revision.created_at) : '—' }}
                                <AppBadge v-if="index === 0" tone="success">{{
                                    t('admin.pages.history.current')
                                }}</AppBadge>
                            </span>
                            <span class="truncate text-xs text-fg-muted">
                                {{ revision.saved_by?.name ?? t('admin.pages.history.someone') }}
                            </span>
                        </button>
                    </li>
                </ul>

                <section
                    :aria-busy="loadingRevision"
                    :class="[
                        'flex min-w-0 flex-col gap-4 rounded-xl border border-line p-4 transition-opacity',
                        loadingRevision && 'opacity-60',
                    ]"
                >
                    <FormAlert v-if="revisionError" :message="revisionError" />
                    <p v-else-if="!selected" class="text-sm text-fg-muted">{{ t('admin.pages.history.choose') }}</p>
                    <template v-else>
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-surface-muted text-fg-muted"
                                :title="selected.image ? undefined : t('admin.pages.history.noImage')"
                            >
                                <img
                                    v-if="selected.image?.thumb ?? selected.image?.original"
                                    :src="selected.image?.thumb ?? selected.image?.original"
                                    :alt="selected.image?.alt ?? ''"
                                    class="size-full object-cover"
                                />
                                <AppIcon v-else name="image" :size="20" />
                            </span>
                            <div class="flex min-w-0 flex-col gap-1">
                                <h3 class="text-lg font-bold break-words">{{ selected.title }}</h3>
                                <span class="truncate text-start text-xs text-fg-muted" dir="ltr"
                                    >/{{ selected.slug }}</span
                                >
                                <p v-if="selected.excerpt" class="text-sm text-fg-muted">{{ selected.excerpt }}</p>
                            </div>
                        </div>

                        <!-- The content was sanitized by the server when it was saved. -->
                        <!-- eslint-disable vue/no-v-html -->
                        <div
                            v-if="selected.content"
                            class="max-h-[40vh] overflow-y-auto rounded-lg bg-bg p-4 text-sm leading-loose break-words [&_a]:text-primary [&_a]:underline [&_blockquote]:border-s-4 [&_blockquote]:border-line-strong [&_blockquote]:ps-3 [&_h2]:mt-3 [&_h2]:text-lg [&_h2]:font-bold [&_h3]:mt-3 [&_h3]:font-bold [&_h4]:mt-2 [&_h4]:font-semibold [&_iframe]:aspect-video [&_iframe]:w-full [&_img]:my-2 [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-lg [&_ol]:list-decimal [&_ol]:ps-5 [&_p]:my-1.5 [&_table]:w-full [&_td]:border [&_td]:border-line [&_td]:p-1.5 [&_th]:border [&_th]:border-line [&_th]:p-1.5 [&_ul]:list-disc [&_ul]:ps-5 [&_video]:my-2 [&_video]:max-w-full [&_video]:rounded-lg"
                            v-html="selected.content"
                        />
                        <!-- eslint-enable vue/no-v-html -->
                        <p v-else class="text-sm text-fg-muted">{{ t('admin.pages.history.noContent') }}</p>

                        <dl v-if="hasSeoFields" class="flex flex-col gap-1 border-t border-line pt-3 text-[13px]">
                            <dt class="font-semibold">{{ t('admin.pages.history.seo') }}</dt>
                            <dd v-if="selected.focus_keyphrase" class="text-fg-muted">
                                {{ t('admin.seo.focusKeyphrase') }}: {{ selected.focus_keyphrase }}
                            </dd>
                            <dd v-if="selected.meta_title" class="text-fg-muted">
                                {{ t('admin.seo.metaTitle') }}: {{ selected.meta_title }}
                            </dd>
                            <dd v-if="selected.meta_description" class="text-fg-muted">
                                {{ t('admin.seo.metaDescription') }}: {{ selected.meta_description }}
                            </dd>
                            <dd v-if="selected.og_title" class="text-fg-muted">
                                {{ t('admin.pages.social.ogTitle') }}: {{ selected.og_title }}
                            </dd>
                            <dd v-if="selected.og_description" class="text-fg-muted">
                                {{ t('admin.pages.social.ogDescription') }}: {{ selected.og_description }}
                            </dd>
                            <dd v-if="selected.og_image" class="flex items-center gap-2 text-fg-muted">
                                {{ t('admin.pages.social.ogImage') }}:
                                <img
                                    :src="selected.og_image.thumb ?? selected.og_image.original"
                                    :alt="selected.og_image.alt ?? ''"
                                    class="size-8 rounded object-cover"
                                />
                            </dd>
                            <dd v-if="selected.canonical_url" class="truncate text-fg-muted">
                                {{ t('admin.pages.social.canonical') }}:
                                <span dir="ltr">{{ selected.canonical_url }}</span>
                            </dd>
                            <dd v-if="selected.robots_noindex">
                                <AppBadge tone="warning">{{ t('admin.pages.social.noindexBadge') }}</AppBadge>
                            </dd>
                        </dl>
                    </template>
                </section>
            </div>
        </div>

        <template #footer>
            <AppButton
                v-if="canRestore"
                size="md"
                :disabled="!selected || isCurrent || loadingRevision"
                :title="t('admin.pages.history.restoreHint')"
                @click="restore"
            >
                <AppIcon name="refresh" :size="18" />
                {{ t('admin.pages.history.restore') }}
            </AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.close') }}</AppButton>
        </template>
    </AppDialog>
</template>
