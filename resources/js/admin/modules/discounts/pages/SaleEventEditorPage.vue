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
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import RichTextEditor from '../../editor/components/RichTextEditor.vue';
import { htmlToText } from '../../editor/richText';
import MediaImageField from '../../media/components/MediaImageField.vue';
import type { MediaPicture } from '../../media/types';
import { fromLocalInput, toLocalInput } from '../../products/variants';
import { slugify } from '../../seo/analysis';
import SeoPanel from '../../seo/components/SeoPanel.vue';
import { saleEventsApi } from '../api';
import EventItemsSection from '../components/EventItemsSection.vue';
import { type EventRow, mapItemErrors, rowFromItem, rowProblem, toItemPayload } from '../saleEvents';
import type { SaleEventDetail, SaveSaleEventPayload } from '../types';
import { useLandingPreview } from '../useLandingPreview';

/**
 * Create (`id` null) or edit a sale event: its dates, its variants with their own discounts, and
 * the optional landing page. Saving reprices the event's products.
 */
const props = defineProps<{ id: number | null }>();

const { t } = useI18n();
const { formatNumber, formatDateTime, formatRelativeTime } = useLocale();
const toast = useToast();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageDiscounts));
const { previewing, openPreview } = useLandingPreview();

const FIELDS = [
    'name',
    'starts_at',
    'ends_at',
    'is_active',
    'items',
    'has_landing_page',
    'slug',
    'landing_title',
    'landing_description',
    'banner_id',
    'meta_title',
    'meta_description',
    'focus_keyphrase',
    'robots_noindex',
    'canonical_url',
    'og_title',
    'og_description',
    'og_image_id',
] as const;
const form = useApiForm(FIELDS);

const saleEvent = ref<SaleEventDetail | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
/** Where the event stands in the priority list (1 = highest) and how many events there are. */
const priority = ref<{ rank: number; total: number } | null>(null);

const fields = reactive({
    name: '',
    startsAt: '',
    endsAt: '',
    isActive: true,
    hasLandingPage: false,
    slug: '',
    landingTitle: '',
    landingDescription: '',
    metaTitle: '',
    metaDescription: '',
    focusKeyphrase: '',
    robotsNoindex: false,
    canonicalUrl: '',
    ogTitle: '',
    ogDescription: '',
});
const banner = ref<MediaPicture | null>(null);
const ogImage = ref<MediaPicture | null>(null);
const rows = ref<EventRow[]>([]);
const slugEdited = ref(false);

function fill(loaded: SaleEventDetail | null): void {
    saleEvent.value = loaded;
    fields.name = loaded?.name ?? '';
    fields.startsAt = toLocalInput(loaded?.starts_at ?? null);
    fields.endsAt = toLocalInput(loaded?.ends_at ?? null);
    fields.isActive = loaded?.is_active ?? true;
    fields.hasLandingPage = loaded?.has_landing_page ?? false;
    fields.slug = loaded?.slug ?? '';
    fields.landingTitle = loaded?.landing_title ?? '';
    fields.landingDescription = loaded?.landing_description ?? '';
    fields.metaTitle = loaded?.meta_title ?? '';
    fields.metaDescription = loaded?.meta_description ?? '';
    fields.focusKeyphrase = loaded?.focus_keyphrase ?? '';
    fields.robotsNoindex = loaded?.robots_noindex ?? false;
    fields.canonicalUrl = loaded?.canonical_url ?? '';
    fields.ogTitle = loaded?.og_title ?? '';
    fields.ogDescription = loaded?.og_description ?? '';
    banner.value = loaded?.banner ?? null;
    ogImage.value = loaded?.og_image ?? null;
    rows.value = (loaded?.items ?? []).map((item) => rowFromItem(item, toLocalInput));
    slugEdited.value = (loaded?.slug ?? null) !== null;
}

async function loadPriority(): Promise<void> {
    if (props.id === null) {
        priority.value = null;

        return;
    }

    try {
        const list = await saleEventsApi.list();
        priority.value = { rank: list.findIndex((event) => event.id === props.id) + 1, total: list.length };
    } catch {
        priority.value = null;
    }
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const [loaded] = await Promise.all([
            props.id === null ? Promise.resolve(null) : saleEventsApi.show(props.id),
            loadPriority(),
        ]);
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
    () => fields.name,
    (name) => {
        if (!slugEdited.value) {
            fields.slug = slugify(name);
        }
    },
);

watch(
    () => fields.hasLandingPage,
    (on) => {
        if (on && fields.slug === '') {
            fields.slug = slugify(fields.name);
        }
    },
);

function onSlugInput(value: string): void {
    fields.slug = value;
    slugEdited.value = true;
}

// ---- payload, unsaved changes ----

const optional = (value: string): string | null => (value.trim() === '' ? null : value.trim());

function payload(): SaveSaleEventPayload {
    return {
        name: fields.name.trim(),
        starts_at: fromLocalInput(fields.startsAt),
        ends_at: fromLocalInput(fields.endsAt),
        is_active: fields.isActive,
        items: rows.value.map((row) => toItemPayload(row, fromLocalInput)),
        has_landing_page: fields.hasLandingPage,
        // Without a landing page the slug isn't shown, so the saved one is kept as it is.
        slug: fields.hasLandingPage ? optional(fields.slug) : (saleEvent.value?.slug ?? null),
        landing_title: optional(fields.landingTitle),
        landing_description: optional(fields.landingDescription),
        banner_id: banner.value?.id ?? null,
        meta_title: optional(fields.metaTitle),
        meta_description: optional(fields.metaDescription),
        focus_keyphrase: optional(fields.focusKeyphrase),
        robots_noindex: fields.robotsNoindex,
        canonical_url: optional(fields.canonicalUrl),
        og_title: optional(fields.ogTitle),
        og_description: optional(fields.ogDescription),
        og_image_id: ogImage.value?.id ?? null,
    };
}

const snapshot = ref('');
const dirty = computed(
    () => canManage.value && !loading.value && snapshot.value !== '' && JSON.stringify(payload()) !== snapshot.value,
);

/** The editor tidies the HTML it loads: that is not a change by the admin, so the baseline takes it. */
function acceptNormalized(): void {
    if (snapshot.value === '') {
        return;
    }

    const baseline = JSON.parse(snapshot.value) as SaveSaleEventPayload;
    baseline.landing_description = payload().landing_description;
    snapshot.value = JSON.stringify(baseline);
}

// ---- errors ----

/** The rows as they were sent, so `items.3.*` errors find their variant. */
const submittedRows = ref<EventRow[]>([]);
const fieldErrors = computed(() => form.lastError.value?.fieldErrors ?? {});
const itemErrors = computed(() => mapItemErrors(submittedRows.value, fieldErrors.value));
const listError = computed(() => fieldErrors.value.items?.[0]);

/** Errors without a place on the page go in the alert at the top. */
const alertMessage = computed(() => {
    const keys = Object.keys(fieldErrors.value);
    const placed = (key: string): boolean =>
        (FIELDS as readonly string[]).includes(key) ||
        (/^items\.\d+\./.test(key) && submittedRows.value[Number(key.split('.')[1])] !== undefined);

    return keys.length > 0 && keys.every(placed) && form.lastError.value?.code === 'validation_failed'
        ? null
        : form.generalError.value;
});

// ---- saving, deleting ----

const itemsSection = ref<InstanceType<typeof EventItemsSection> | null>(null);
const eventWindow = computed(() => ({ startsAt: fields.startsAt, endsAt: fields.endsAt }));

async function save(): Promise<void> {
    if (rows.value.some((row) => rowProblem(row, eventWindow.value) !== null)) {
        itemsSection.value?.showProblems();
        toast.show(t('admin.discounts.events.fixProblems'), 'danger');

        return;
    }

    const existing = saleEvent.value;
    const body = payload();
    submittedRows.value = rows.value;
    const saved = await form.submit(() =>
        existing ? saleEventsApi.update(existing.id, body) : saleEventsApi.create(body),
    );

    if (!saved) {
        if (itemErrors.value.size > 0) {
            itemsSection.value?.showProblems();
        }

        toast.show(t('admin.discounts.events.saveFailed'), 'danger');

        return;
    }

    fill(saved);
    submittedRows.value = [];
    snapshot.value = JSON.stringify(payload());
    toast.show(t(existing ? 'admin.discounts.events.saved' : 'admin.discounts.events.created', { name: saved.name }));

    if (!existing) {
        await router.replace({ name: 'sale-event', params: { id: saved.id } });
        await loadPriority();
    }
}

const confirmDelete = ref(false);

async function remove(): Promise<void> {
    if (!saleEvent.value) {
        return;
    }

    try {
        await saleEventsApi.remove(saleEvent.value.id);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');

        return;
    }

    snapshot.value = JSON.stringify(payload());
    toast.show(t('admin.discounts.events.deleted', { name: saleEvent.value.name }));
    await router.push({ name: 'sale-events' });
}

// ---- leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    (to) =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value || to.name === 'sale-event') {
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

// ---- page ----

const stateTone = computed(() =>
    saleEvent.value?.state === 'active' ? 'success' : saleEvent.value?.state === 'scheduled' ? 'warning' : 'neutral',
);
const stateDetail = computed(() => {
    const event = saleEvent.value;

    if (event?.state === 'scheduled') {
        return t('admin.discounts.events.startsIn', { time: formatRelativeTime(event.starts_at) });
    }

    return event?.state === 'active'
        ? t('admin.discounts.events.endsIn', { time: formatRelativeTime(event.ends_at) })
        : null;
});

const shownDate = (value: string): string => (value === '' ? '…' : formatDateTime(fromLocalInput(value) ?? ''));

const cardClass = 'flex flex-col gap-4.5 rounded-2xl border border-line bg-surface p-5 sm:p-6';
const dateClass =
    'h-13 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
const sectionLinkClass =
    'rounded-full border border-line px-3.5 py-1.5 text-[13px] font-semibold text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none';
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <RouterLink
            :to="{ name: 'sale-events' }"
            class="flex w-fit items-center gap-1.5 rounded-lg text-sm font-medium text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
        >
            <AppIcon name="arrowForward" :size="16" flip-in-rtl class="rotate-180" />
            {{ t('admin.discounts.events.backToList') }}
        </RouterLink>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                    <h1 class="truncate text-2xl font-bold">
                        {{ saleEvent ? saleEvent.name : t('admin.discounts.events.newTitle') }}
                    </h1>
                    <AppBadge v-if="saleEvent" :tone="stateTone">{{
                        t(`admin.discounts.states.${saleEvent.state}`)
                    }}</AppBadge>
                </div>
                <p v-if="saleEvent" class="text-xs text-fg-muted">
                    <template v-if="stateDetail">{{ stateDetail }} · </template>
                    <template v-if="priority && priority.rank > 0">{{
                        t('admin.discounts.events.priorityOf', {
                            n: formatNumber(priority.rank),
                            total: formatNumber(priority.total),
                        })
                    }}</template>
                </p>
            </div>
            <div v-if="saleEvent" class="flex flex-wrap gap-2">
                <AppButton
                    v-if="saleEvent.has_landing_page"
                    variant="secondary"
                    size="md"
                    :loading="previewing"
                    :title="t('admin.discounts.events.landing.previewHint')"
                    @click="openPreview(saleEvent.id)"
                >
                    <AppIcon name="eye" :size="18" />
                    {{ t('admin.pages.preview.open') }}
                </AppButton>
                <AppButton v-if="canManage" variant="danger-outline" size="md" @click="confirmDelete = true">
                    <AppIcon name="trash" :size="18" />
                    {{ t('admin.discounts.events.delete') }}
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

        <form v-else id="sale-event-form" class="flex min-w-0 flex-col gap-5" novalidate @submit.prevent="save">
            <nav class="flex flex-wrap gap-2" :aria-label="t('admin.discounts.events.sectionsLabel')">
                <a href="#event-general-title" :class="sectionLinkClass">{{
                    t('admin.discounts.events.sections.general')
                }}</a>
                <a href="#event-products-title" :class="sectionLinkClass">{{
                    t('admin.discounts.events.sections.productsShort')
                }}</a>
                <a href="#event-landing-title" :class="sectionLinkClass">{{
                    t('admin.discounts.events.sections.landing')
                }}</a>
            </nav>

            <FormAlert v-if="alertMessage" :message="alertMessage" />

            <fieldset :disabled="!canManage" class="contents">
                <section :class="cardClass" aria-labelledby="event-general-title">
                    <h2 id="event-general-title" class="text-lg font-bold">
                        {{ t('admin.discounts.events.sections.general') }}
                    </h2>
                    <TextField
                        v-model="fields.name"
                        :label="t('admin.discounts.events.name')"
                        :hint="t('admin.discounts.events.nameHint')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="event-starts" class="mb-2 block text-sm font-semibold">{{
                                t('admin.discounts.events.startsAt')
                            }}</label>
                            <input
                                id="event-starts"
                                v-model="fields.startsAt"
                                type="datetime-local"
                                :class="[dateClass, form.errors.starts_at && 'border-2 border-danger']"
                            />
                            <p v-if="form.errors.starts_at" class="mt-2 text-[13px] text-danger">
                                {{ form.errors.starts_at }}
                            </p>
                        </div>
                        <div>
                            <label for="event-ends" class="mb-2 block text-sm font-semibold">{{
                                t('admin.discounts.events.endsAt')
                            }}</label>
                            <input
                                id="event-ends"
                                v-model="fields.endsAt"
                                type="datetime-local"
                                :min="fields.startsAt || undefined"
                                :class="[dateClass, form.errors.ends_at && 'border-2 border-danger']"
                            />
                            <p v-if="form.errors.ends_at" class="mt-2 text-[13px] text-danger">
                                {{ form.errors.ends_at }}
                            </p>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line p-3.5">
                        <input
                            v-model="fields.isActive"
                            type="checkbox"
                            class="mt-1 size-4 accent-[var(--color-primary)]"
                        />
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ t('admin.discounts.events.active') }}</span>
                            <span class="text-[13px] text-fg-muted">{{ t('admin.discounts.events.activeHint') }}</span>
                        </span>
                    </label>
                    <p class="flex items-start gap-2 text-[13px] text-fg-muted">
                        <AppIcon name="info" :size="16" class="mt-0.5 shrink-0" />
                        <span>
                            {{ t('admin.discounts.events.priorityHint') }}
                            <RouterLink
                                :to="{ name: 'sale-events' }"
                                class="font-semibold text-primary hover:underline"
                                >{{ t('admin.discounts.events.priorityLink') }}</RouterLink
                            >
                        </span>
                    </p>
                </section>

                <EventItemsSection
                    ref="itemsSection"
                    v-model="rows"
                    :event-id="saleEvent?.id ?? null"
                    :event-starts-at="fields.startsAt"
                    :event-ends-at="fields.endsAt"
                    :errors="itemErrors"
                    :list-error="listError"
                    :can-manage="canManage"
                />

                <section :class="cardClass" aria-labelledby="event-landing-title">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-col gap-1">
                            <h2 id="event-landing-title" class="text-lg font-bold">
                                {{ t('admin.discounts.events.sections.landing') }}
                            </h2>
                            <p class="max-w-3xl text-[13px] leading-relaxed text-fg-muted">
                                {{ t('admin.discounts.events.landing.intro') }}
                            </p>
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line p-3.5">
                        <input
                            v-model="fields.hasLandingPage"
                            type="checkbox"
                            class="mt-1 size-4 accent-[var(--color-primary)]"
                        />
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ t('admin.discounts.events.landing.switch') }}</span>
                            <span class="text-[13px] text-fg-muted">{{
                                t('admin.discounts.events.landing.switchHint')
                            }}</span>
                        </span>
                    </label>

                    <template v-if="fields.hasLandingPage">
                        <ol class="grid gap-2 sm:grid-cols-3">
                            <li class="flex flex-col gap-1 rounded-xl border border-line p-3">
                                <span class="text-xs font-semibold text-fg-muted">{{
                                    t('admin.discounts.events.landing.upcomingWhen', {
                                        start: shownDate(fields.startsAt),
                                    })
                                }}</span>
                                <AppBadge tone="warning" class="w-fit">{{
                                    t('admin.discounts.events.landing.status.upcoming')
                                }}</AppBadge>
                                <span class="text-[13px] leading-relaxed text-fg-muted">{{
                                    t('admin.discounts.events.landing.upcomingText')
                                }}</span>
                            </li>
                            <li class="flex flex-col gap-1 rounded-xl border border-line p-3">
                                <span class="text-xs font-semibold text-fg-muted">{{
                                    t('admin.discounts.window.between', {
                                        start: shownDate(fields.startsAt),
                                        end: shownDate(fields.endsAt),
                                    })
                                }}</span>
                                <AppBadge tone="success" class="w-fit">{{
                                    t('admin.discounts.events.landing.status.running')
                                }}</AppBadge>
                                <span class="text-[13px] leading-relaxed text-fg-muted">{{
                                    t('admin.discounts.events.landing.runningText')
                                }}</span>
                            </li>
                            <li class="flex flex-col gap-1 rounded-xl border border-line p-3">
                                <span class="text-xs font-semibold text-fg-muted">{{
                                    t('admin.discounts.events.landing.endedWhen', { end: shownDate(fields.endsAt) })
                                }}</span>
                                <AppBadge class="w-fit">{{
                                    t('admin.discounts.events.landing.status.ended')
                                }}</AppBadge>
                                <span class="text-[13px] leading-relaxed text-fg-muted">{{
                                    t('admin.discounts.events.landing.endedText')
                                }}</span>
                            </li>
                        </ol>

                        <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
                            <div class="flex min-w-0 flex-col gap-4.5">
                                <TextField
                                    v-model="fields.landingTitle"
                                    :label="t('admin.discounts.events.landing.title')"
                                    :hint="t('admin.discounts.events.landing.titleHint')"
                                    :placeholder="fields.name"
                                    optional
                                    autocomplete="off"
                                    :error="form.errors.landing_title"
                                />
                                <TextField
                                    :model-value="fields.slug"
                                    :label="t('admin.discounts.events.landing.slug')"
                                    :hint="t('admin.discounts.events.landing.slugHint')"
                                    dir="ltr"
                                    autocomplete="off"
                                    :error="form.errors.slug"
                                    @update:model-value="onSlugInput"
                                />
                            </div>
                            <MediaImageField
                                v-model="banner"
                                :label="t('admin.discounts.events.landing.banner')"
                                :hint="t('admin.discounts.events.landing.bannerHint')"
                                :error="form.errors.banner_id"
                            />
                        </div>
                        <RichTextEditor
                            v-model="fields.landingDescription"
                            :label="t('admin.discounts.events.landing.description')"
                            optional
                            :error="form.errors.landing_description"
                            @normalized="acceptNormalized"
                        />
                    </template>
                    <p v-else-if="saleEvent?.has_landing_page" class="text-[13px] text-warning">
                        {{ t('admin.discounts.events.landing.switchedOff') }}
                    </p>
                </section>

                <template v-if="fields.hasLandingPage">
                    <section :class="cardClass" aria-labelledby="event-social-title">
                        <div class="flex flex-col gap-1">
                            <h2 id="event-social-title" class="text-lg font-bold">
                                {{ t('admin.pages.social.title') }}
                            </h2>
                            <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.pages.social.intro') }}</p>
                        </div>
                        <div class="grid gap-5 lg:grid-cols-[1fr_auto]">
                            <div class="flex min-w-0 flex-col gap-4.5">
                                <TextField
                                    v-model="fields.ogTitle"
                                    :label="t('admin.pages.social.ogTitle')"
                                    :hint="t('admin.pages.social.ogTitleHint')"
                                    :placeholder="fields.metaTitle || fields.landingTitle || fields.name"
                                    optional
                                    autocomplete="off"
                                    :error="form.errors.og_title"
                                />
                                <TextAreaField
                                    v-model="fields.ogDescription"
                                    :label="t('admin.pages.social.ogDescription')"
                                    :hint="t('admin.pages.social.ogDescriptionHint')"
                                    optional
                                    :rows="3"
                                    :maxlength="500"
                                    :error="form.errors.og_description"
                                />
                            </div>
                            <MediaImageField
                                v-model="ogImage"
                                :label="t('admin.pages.social.ogImage')"
                                :hint="t('admin.discounts.events.landing.ogImageHint')"
                                :error="form.errors.og_image_id"
                            />
                        </div>
                        <TextField
                            v-model="fields.canonicalUrl"
                            :label="t('admin.pages.social.canonical')"
                            :hint="t('admin.pages.social.canonicalHint')"
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
                                <span class="text-sm font-semibold">{{ t('admin.pages.social.noindex') }}</span>
                                <span class="text-[13px] text-fg-muted">{{ t('admin.pages.social.noindexHint') }}</span>
                            </span>
                        </label>
                    </section>

                    <SeoPanel
                        v-model:keyphrase="fields.focusKeyphrase"
                        v-model:meta-title="fields.metaTitle"
                        v-model:meta-description="fields.metaDescription"
                        type="sale_event"
                        :name="fields.landingTitle || fields.name"
                        :slug="fields.slug"
                        :description="htmlToText(fields.landingDescription)"
                        :has-image="banner !== null"
                        :subject-id="saleEvent?.id ?? null"
                        :keyphrase-used-by="[]"
                        :errors="{
                            focus_keyphrase: form.errors.focus_keyphrase,
                            meta_title: form.errors.meta_title,
                            meta_description: form.errors.meta_description,
                        }"
                    />
                </template>
            </fieldset>
        </form>

        <div
            v-if="!loading && !loadError && canManage"
            class="sticky bottom-0 z-30 -mx-4 flex flex-wrap items-center gap-3 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur sm:mx-0 sm:rounded-t-2xl sm:border-x"
        >
            <span class="grow text-[13px] text-fg-muted">{{ t('admin.discounts.events.repriceNote') }}</span>
            <span v-if="dirty" class="text-xs text-warning">{{ t('admin.discounts.unsaved') }}</span>
            <AppButton type="submit" form="sale-event-form" size="md" :loading="form.processing.value">
                {{ saleEvent ? t('admin.discounts.events.save') : t('admin.discounts.events.create') }}
            </AppButton>
        </div>

        <AppDialog
            :open="confirmDelete"
            :title="t('admin.discounts.events.deleteTitle', { name: saleEvent?.name ?? '' })"
            icon="trash"
            tone="danger"
            @close="confirmDelete = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.events.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="remove">{{
                    t('admin.discounts.events.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="confirmDelete = false">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.discounts.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.leaveText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="leave">{{ t('admin.discounts.leaveConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="stay">{{ t('admin.discounts.leaveStay') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
