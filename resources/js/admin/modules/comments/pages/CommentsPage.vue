<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppBadge, { type BadgeTone } from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon, { type IconName } from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { commentsApi } from '../api';
import { pageSelection, REASON_MAX, REPLY_MAX, STATUSES, statusesFor, toggleId, togglePage } from '../comments';
import { useCommentsStore } from '../store';
import type { Comment, CommentableType, CommentList, CommentStatus, ModeratedStatus } from '../types';

/**
 * Content › Blog comments (`type` post) or Catalog › Product comments (`type` product): customers'
 * comments, pending first. Approve, reject (with a reason for staff), mark as spam, reply as the
 * store or delete, one comment or a selection. Status, search, one item and page live in the URL.
 */
const props = defineProps<{ type: CommentableType }>();

const { t } = useI18n();
const { formatNumber, formatDateTime, formatRelativeTime } = useLocale();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const badge = useCommentsStore();

const TABS = [...STATUSES, 'all'] as const;
type Tab = (typeof TABS)[number];

const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

const search = computed({
    get: () => queryString('q'),
    set: (q: string) => void router.replace({ query: { ...route.query, q: q || undefined, page: undefined } }),
});
const status = computed<Tab>({
    get: () => (TABS.includes(queryString('status') as Tab) ? (queryString('status') as Tab) : 'pending'),
    set: (value) =>
        void router.replace({
            query: { ...route.query, status: value === 'pending' ? undefined : value, page: undefined },
        }),
});
/** One post or product (`?item=5`, from its editor). */
const itemId = computed(() => Number(queryString('item')) || null);
const page = computed(() => Math.max(1, Number(queryString('page')) || 1));

function clearItem(): void {
    void router.replace({ query: { ...route.query, item: undefined, page: undefined } });
}

// ---- loading ----

const list = ref<CommentList | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const selected = ref<Set<number>>(new Set());

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        list.value = await commentsApi.list({
            q: search.value || undefined,
            status: status.value,
            type: props.type,
            item_id: itemId.value ?? undefined,
            page: page.value,
        });
        selected.value = new Set();
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(() => [props.type, search.value, status.value, itemId.value, page.value], load, { immediate: true });

function goTo(next: number): void {
    void router.replace({ query: { ...route.query, page: next > 1 ? String(next) : undefined } });
}

/** After a change: the list and the menu badge. */
async function reload(): Promise<void> {
    await Promise.all([load(), badge.refresh(true)]);
}

const filtering = computed(() => search.value !== '' || itemId.value !== null);
const itemTitle = computed(() => list.value?.data.find((comment) => comment.item?.id === itemId.value)?.item?.title);

/** The editor of the commented post or product, for staff who may open it. */
function itemLink(comment: Comment): { name: string; params: { id: number } } | null {
    const item = comment.item;

    if (!item) {
        return null;
    }

    const allowed = item.type === 'post' ? auth.can(Permission.ViewPosts) : auth.can(Permission.ViewProducts);

    return allowed ? { name: item.type, params: { id: item.id } } : null;
}

// ---- display ----

/** The message for one comment, or its `Many` form for several. */
const counted = (key: string, n: number): string => (n === 1 ? key : `${key}Many`);

const STATUS_TONE: Record<CommentStatus, BadgeTone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'neutral',
    spam: 'danger',
};

const ACTION_ICON: Record<ModeratedStatus, IconName> = { approved: 'check', rejected: 'ban', spam: 'alert' };

const chipClass = (on: boolean): string[] => [
    'inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary bg-primary-soft text-fg' : 'border-line text-fg-muted hover:text-fg',
];

const actionClass = (danger = false): string[] => [
    'inline-flex h-8 items-center gap-1.5 rounded-lg border border-line px-2.5 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    danger ? 'text-danger hover:bg-danger-soft' : 'text-fg hover:bg-surface-muted',
];

function moderationNote(comment: Comment): string | null {
    if (!comment.moderated_at) {
        return null;
    }

    return t(`admin.comments.moderatedBy.${comment.status}`, {
        name: comment.moderated_by?.name ?? t('admin.comments.someone'),
        date: formatDateTime(comment.moderated_at),
    });
}

// ---- selection ----

const pageIds = computed(() => list.value?.data.map((comment) => comment.id) ?? []);
const selection = computed(() => pageSelection(pageIds.value, selected.value));

// ---- moderation ----

const busy = ref(false);

async function moderate(ids: number[], next: ModeratedStatus, reason: string | null = null): Promise<boolean> {
    busy.value = true;

    try {
        if (ids.length === 1) {
            await commentsApi.moderate(ids[0] as number, next, reason);
        } else {
            await commentsApi.moderateMany(ids, next, reason);
        }

        toast.show(t(counted(`admin.comments.done.${next}`, ids.length), { n: formatNumber(ids.length) }));
        await reload();

        return true;
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');

        return false;
    } finally {
        busy.value = false;
    }
}

// Reject asks for an optional reason (staff only); the other statuses apply at once.
const rejecting = ref<number[] | null>(null);
const reason = ref('');

function choose(ids: number[], next: ModeratedStatus): void {
    if (next === 'rejected') {
        reason.value = ids.length === 1 ? (list.value?.data.find((c) => c.id === ids[0])?.rejection_reason ?? '') : '';
        rejecting.value = ids;
    } else {
        void moderate(ids, next);
    }
}

async function confirmReject(): Promise<void> {
    if (rejecting.value && (await moderate(rejecting.value, 'rejected', reason.value.trim() || null))) {
        rejecting.value = null;
    }
}

// ---- reply ----

const replying = ref<Comment | null>(null);
const replyText = ref('');
const replyError = ref<string | null>(null);

function openReply(comment: Comment): void {
    replying.value = comment;
    replyText.value = comment.reply ?? '';
    replyError.value = null;
}

async function saveReply(): Promise<void> {
    const comment = replying.value;

    if (!comment) {
        return;
    }

    busy.value = true;
    replyError.value = null;

    try {
        await commentsApi.reply(comment.id, replyText.value);
        toast.show(t('admin.comments.reply.saved'));
        replying.value = null;
        await load();
    } catch (caught) {
        const failure = toApiError(caught);
        replyError.value = failure.fieldErrors.reply?.[0] ?? failure.message;
    } finally {
        busy.value = false;
    }
}

async function removeReply(comment: Comment): Promise<void> {
    busy.value = true;

    try {
        await commentsApi.removeReply(comment.id);
        toast.show(t('admin.comments.reply.removed'));
        replying.value = null;
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

// ---- delete ----

const deleting = ref<number[] | null>(null);

async function confirmDelete(): Promise<void> {
    const ids = deleting.value;

    if (!ids) {
        return;
    }

    busy.value = true;

    try {
        if (ids.length === 1) {
            await commentsApi.remove(ids[0] as number);
        } else {
            await commentsApi.removeMany(ids);
        }

        toast.show(t(counted('admin.comments.deleted', ids.length), { n: formatNumber(ids.length) }));
        deleting.value = null;
        await reload();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

const bulkIds = computed(() => [...selected.value]);
</script>

<template>
    <div class="mx-auto flex max-w-5xl flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold">{{ t(`admin.comments.title.${type}`) }}</h1>
            <p class="max-w-3xl text-sm text-fg-muted">{{ t(`admin.comments.intro.${type}`) }}</p>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-2" role="group" :aria-label="t('admin.comments.statusLabel')">
                <button
                    v-for="which in TABS"
                    :key="which"
                    type="button"
                    :class="chipClass(status === which)"
                    :aria-pressed="status === which"
                    @click="status = which"
                >
                    {{ t(`admin.comments.tabs.${which}`, { n: formatNumber(list?.meta.status_counts[which] ?? 0) }) }}
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-80">
                    <SearchInput v-model="search" :label="t('admin.comments.search')" />
                </div>
                <span
                    v-if="itemId !== null"
                    class="inline-flex h-9 max-w-full items-center gap-1 rounded-full border border-primary bg-primary-soft ps-3.5 text-[13px] font-semibold"
                >
                    <span class="truncate">{{
                        t('admin.comments.onItem', { title: itemTitle ?? `#${formatNumber(itemId)}` })
                    }}</span>
                    <button
                        type="button"
                        class="flex size-9 shrink-0 items-center justify-center rounded-full text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        :aria-label="t('admin.comments.clearItem')"
                        @click="clearItem"
                    >
                        <AppIcon name="close" :size="14" />
                    </button>
                </span>
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
            :class="[
                'overflow-hidden rounded-2xl border border-line bg-surface transition-opacity',
                loading && list && 'opacity-60',
            ]"
        >
            <div v-if="!list" aria-hidden="true" class="flex flex-col gap-px">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-28 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="list.data.length === 0"
                :icon="filtering ? 'search' : 'message'"
                :title="filtering ? t('admin.comments.noResults') : t(`admin.comments.empty.${status}`)"
            />

            <template v-else>
                <div class="flex flex-wrap items-center gap-3 border-b border-line bg-surface-muted/60 px-4.5 py-2.5">
                    <label class="flex cursor-pointer items-center gap-2.5 text-[13px] font-semibold">
                        <input
                            type="checkbox"
                            class="size-4 accent-[var(--color-primary)]"
                            :checked="selection === 'all'"
                            :indeterminate="selection === 'some'"
                            @change="selected = togglePage(selected, pageIds)"
                        />
                        {{
                            selected.size > 0
                                ? t('admin.comments.selected', { n: formatNumber(selected.size) })
                                : t('admin.comments.selectPage')
                        }}
                    </label>
                    <div v-if="selected.size > 0" class="flex flex-wrap gap-2">
                        <button
                            v-for="next in ['approved', 'rejected', 'spam'] as const"
                            :key="next"
                            type="button"
                            :class="actionClass()"
                            :disabled="busy"
                            @click="choose(bulkIds, next)"
                        >
                            <AppIcon :name="ACTION_ICON[next]" :size="15" />
                            {{ t(`admin.comments.actions.${next}`) }}
                        </button>
                        <button type="button" :class="actionClass(true)" :disabled="busy" @click="deleting = bulkIds">
                            <AppIcon name="trash" :size="15" />
                            {{ t('admin.comments.actions.delete') }}
                        </button>
                    </div>
                </div>

                <ul class="divide-y divide-line">
                    <li
                        v-for="comment in list.data"
                        :key="comment.id"
                        :class="['flex gap-3 px-4.5 py-4', selected.has(comment.id) && 'bg-primary-soft/30']"
                    >
                        <input
                            type="checkbox"
                            class="mt-1 size-4 shrink-0 accent-[var(--color-primary)]"
                            :checked="selected.has(comment.id)"
                            :aria-label="t('admin.comments.select', { name: comment.display_name })"
                            @change="selected = toggleId(selected, comment.id)"
                        />
                        <div class="flex min-w-0 grow flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px]">
                                <RouterLink
                                    v-if="auth.can(Permission.ViewCustomers)"
                                    :to="{ name: 'customer', params: { id: comment.customer_id } }"
                                    class="rounded font-semibold text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                    >{{ comment.display_name }}</RouterLink
                                >
                                <span v-else class="font-semibold">{{ comment.display_name }}</span>
                                <span class="text-fg-muted" aria-hidden="true">·</span>
                                <span v-if="comment.item" class="min-w-0 truncate text-fg-muted">
                                    {{ t(`admin.comments.on.${comment.item.type}`) }}
                                    <RouterLink
                                        v-if="itemLink(comment)"
                                        :to="itemLink(comment) ?? ''"
                                        class="rounded font-semibold text-fg-muted hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                        >{{ comment.item.title }}</RouterLink
                                    >
                                    <template v-else>{{ comment.item.title }}</template>
                                    <template v-if="comment.item.is_deleted">
                                        ({{ t('admin.comments.itemDeleted') }})</template
                                    >
                                </span>
                                <span class="text-fg-muted" aria-hidden="true">·</span>
                                <time
                                    v-if="comment.created_at"
                                    :datetime="comment.created_at"
                                    :title="formatDateTime(comment.created_at)"
                                    class="text-fg-muted"
                                    >{{ formatRelativeTime(comment.created_at) }}</time
                                >
                                <AppBadge :tone="STATUS_TONE[comment.status]" class="ms-auto">{{
                                    t(`admin.comments.status.${comment.status}`)
                                }}</AppBadge>
                            </div>

                            <p class="text-sm leading-relaxed break-words whitespace-pre-line">{{ comment.body }}</p>

                            <p v-if="moderationNote(comment)" class="text-xs text-fg-muted">
                                {{ moderationNote(comment) }}
                                <template v-if="comment.rejection_reason">
                                    · {{ t('admin.comments.reason', { reason: comment.rejection_reason }) }}
                                </template>
                            </p>

                            <div
                                v-if="comment.reply"
                                class="flex flex-col gap-1 rounded-xl border-s-4 border-primary bg-surface-muted/60 px-3.5 py-2.5"
                            >
                                <span class="text-xs font-semibold text-fg-muted">{{
                                    t('admin.comments.reply.label', {
                                        name: comment.replied_by?.name ?? t('admin.comments.someone'),
                                    })
                                }}</span>
                                <p class="text-[13px] leading-relaxed break-words whitespace-pre-line">
                                    {{ comment.reply }}
                                </p>
                                <span v-if="comment.status !== 'approved'" class="text-[11px] text-fg-muted">{{
                                    t('admin.comments.reply.hiddenUntilApproved')
                                }}</span>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="next in statusesFor(comment.status)"
                                    :key="next"
                                    type="button"
                                    :class="actionClass()"
                                    :disabled="busy"
                                    @click="choose([comment.id], next)"
                                >
                                    <AppIcon :name="ACTION_ICON[next]" :size="15" />
                                    {{ t(`admin.comments.actions.${next}`) }}
                                </button>
                                <button
                                    v-if="comment.status === 'rejected'"
                                    type="button"
                                    :class="actionClass()"
                                    :disabled="busy"
                                    @click="choose([comment.id], 'rejected')"
                                >
                                    <AppIcon name="pen" :size="15" />
                                    {{ t('admin.comments.actions.editReason') }}
                                </button>
                                <button
                                    type="button"
                                    :class="actionClass()"
                                    :disabled="busy"
                                    @click="openReply(comment)"
                                >
                                    <AppIcon name="message" :size="15" />
                                    {{
                                        comment.reply
                                            ? t('admin.comments.actions.editReply')
                                            : t('admin.comments.actions.reply')
                                    }}
                                </button>
                                <button
                                    type="button"
                                    :class="actionClass(true)"
                                    :disabled="busy"
                                    @click="deleting = [comment.id]"
                                >
                                    <AppIcon name="trash" :size="15" />
                                    {{ t('admin.comments.actions.delete') }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>

                <AppPagination v-if="list.meta.last_page > 1" :meta="list.meta" @change="goTo" />
            </template>
        </section>

        <AppDialog
            :open="rejecting !== null"
            :title="
                t(counted('admin.comments.rejectTitle', rejecting?.length ?? 1), {
                    n: formatNumber(rejecting?.length ?? 0),
                })
            "
            icon="ban"
            :busy="busy"
            @close="rejecting = null"
        >
            <TextAreaField
                v-model="reason"
                :label="t('admin.comments.reasonLabel')"
                :hint="t('admin.comments.reasonHint')"
                :maxlength="REASON_MAX"
                optional
            />
            <template #footer>
                <AppButton size="md" :loading="busy" @click="confirmReject">{{
                    t('admin.comments.actions.rejected')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="busy" @click="rejecting = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="replying !== null"
            :title="t('admin.comments.reply.title', { name: replying?.display_name ?? '' })"
            icon="message"
            size="lg"
            :busy="busy"
            @close="replying = null"
        >
            <div class="flex flex-col gap-4">
                <blockquote
                    v-if="replying"
                    class="rounded-xl bg-surface-muted/60 px-3.5 py-2.5 text-[13px] leading-relaxed whitespace-pre-line"
                >
                    {{ replying.body }}
                </blockquote>
                <TextAreaField
                    v-model="replyText"
                    :label="t('admin.comments.reply.fieldLabel')"
                    :hint="t('admin.comments.reply.hint')"
                    :error="replyError ?? undefined"
                    :maxlength="REPLY_MAX"
                    :rows="5"
                />
            </div>
            <template #footer>
                <AppButton size="md" :loading="busy" :disabled="replyText.trim() === ''" @click="saveReply">{{
                    t('admin.comments.reply.save')
                }}</AppButton>
                <AppButton
                    v-if="replying?.reply"
                    variant="danger-outline"
                    size="md"
                    :disabled="busy"
                    @click="replying && removeReply(replying)"
                    >{{ t('admin.comments.reply.remove') }}</AppButton
                >
                <AppButton variant="secondary" size="md" :disabled="busy" @click="replying = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="deleting !== null"
            :title="
                t(counted('admin.comments.deleteTitle', deleting?.length ?? 1), {
                    n: formatNumber(deleting?.length ?? 0),
                })
            "
            icon="trash"
            tone="danger"
            :busy="busy"
            @close="deleting = null"
        >
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.comments.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="busy" @click="confirmDelete">{{
                    t('admin.comments.actions.delete')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="busy" @click="deleting = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
