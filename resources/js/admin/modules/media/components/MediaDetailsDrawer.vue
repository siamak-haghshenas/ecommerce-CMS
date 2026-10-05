<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { mediaApi } from '../api';
import { byteSize } from '../files';
import type { MediaAsset, MediaFolder, MediaUsage } from '../types';
import MediaPickerDialog from './MediaPickerDialog.vue';

/**
 * A file's details, sliding in from the side: preview, information, title / alt text (and a
 * video's cover), where it is used, and its actions.
 */
const props = defineProps<{
    assetId: number | null;
    folders: MediaFolder[];
    canUpload: boolean;
    canManage: boolean;
}>();

const emit = defineEmits<{
    close: [];
    changed: [asset: MediaAsset];
    delete: [asset: MediaAsset];
    restore: [asset: MediaAsset];
}>();

const { t } = useI18n();
const { formatNumber, formatDateTime, formatDate } = useLocale();
const toast = useToast();
const closeButton = useTemplateRef('closeButton');

const asset = ref<MediaAsset | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const title = ref('');
const alt = ref('');
const cover = ref<{ id: number; thumb: string | null } | null>(null);
const saving = ref(false);
const saveErrors = ref<Record<string, string>>({});
const pickingCover = ref(false);

const TRASH_DAYS = 30;

async function load(id: number): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        asset.value = await mediaApi.show(id);
        title.value = asset.value.title ?? '';
        alt.value = asset.value.alt ?? '';
        cover.value = asset.value.cover;
        saveErrors.value = {};
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.assetId,
    async (id) => {
        if (id === null) {
            return;
        }

        await load(id);
        await nextTick();
        closeButton.value?.focus();
    },
    { immediate: true },
);

const editable = computed(() => props.canUpload && !!asset.value && !asset.value.deleted_at);
const dirty = computed(
    () =>
        !!asset.value &&
        (title.value.trim() !== (asset.value.title ?? '') ||
            alt.value.trim() !== (asset.value.alt ?? '') ||
            (cover.value?.id ?? null) !== (asset.value.cover?.id ?? null)),
);

async function save(): Promise<void> {
    if (!asset.value) {
        return;
    }

    saving.value = true;
    saveErrors.value = {};

    try {
        asset.value = await mediaApi.update(asset.value.id, {
            title: title.value.trim() || null,
            alt: alt.value.trim() || null,
            cover_id: cover.value?.id ?? null,
        });
        toast.show(t('admin.media.details.saved'));
        emit('changed', asset.value);
    } catch (caught) {
        const failure = toApiError(caught);
        saveErrors.value = Object.fromEntries(
            Object.entries(failure.fieldErrors).map(([key, messages]) => [key, messages[0] ?? '']),
        );

        if (Object.keys(saveErrors.value).length === 0) {
            toast.show(failure.message, 'danger');
        }
    } finally {
        saving.value = false;
    }
}

async function move(folder: string): Promise<void> {
    if (!asset.value) {
        return;
    }

    try {
        await mediaApi.move([asset.value.id], folder === 'none' ? null : Number(folder));
        await load(asset.value.id);
        toast.show(t('admin.media.moved', { n: formatNumber(1) }));

        if (asset.value) {
            emit('changed', asset.value);
        }
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

async function copyUrl(): Promise<void> {
    const url = asset.value?.urls?.original;

    if (url) {
        await navigator.clipboard.writeText(new URL(url, location.origin).href);
        toast.show(t('admin.media.details.copied'));
    }
}

// ---- display ----

const preview = computed(() => asset.value?.urls?.large ?? asset.value?.urls?.original ?? null);
const size = computed(() => {
    if (!asset.value) {
        return '';
    }

    const { value, unit } = byteSize(asset.value.size);

    return `${formatNumber(value)} ${t(`admin.media.units.${unit}`)}`;
});
const folderOptions = computed(() => [
    { value: 'none', label: t('admin.media.folders.none') },
    ...props.folders.map((folder) => ({ value: String(folder.id), label: folder.name })),
]);
const purgeDate = computed(() =>
    asset.value?.deleted_at
        ? new Date(new Date(asset.value.deleted_at).getTime() + TRASH_DAYS * 86_400_000).toISOString()
        : null,
);

const usageLink = (usage: MediaUsage) =>
    usage.type === 'product' || usage.type === 'page'
        ? { name: usage.type, params: { id: usage.id } }
        : { name: usage.type === 'category' ? 'categories' : 'brands' };

// ---- the drawer: Esc closes it, focus returns to the grid ----

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.assetId !== null && !pickingCover.value) {
        emit('close');
    }
}

document.addEventListener('keydown', onKeydown);
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
        leave-to-class="opacity-0"
    >
        <div
            v-if="assetId !== null"
            class="fixed inset-0 z-40 bg-black/30 backdrop-blur-[1px]"
            aria-hidden="true"
            @click="emit('close')"
        />
    </Transition>
    <Transition
        enter-active-class="transition-transform duration-250 ease-out motion-reduce:transition-none"
        enter-from-class="translate-x-full rtl:-translate-x-full"
        leave-active-class="transition-transform duration-200 ease-in motion-reduce:transition-none"
        leave-to-class="translate-x-full rtl:-translate-x-full"
    >
        <aside
            v-if="assetId !== null"
            role="dialog"
            aria-modal="true"
            :aria-label="asset?.name ?? t('admin.media.details.title')"
            class="fixed inset-y-0 end-0 z-50 flex w-full max-w-md flex-col bg-surface shadow-[0_0_40px_rgb(0_0_0/0.2)]"
        >
            <header class="flex items-center gap-3 border-b border-line px-5 py-4">
                <h2 class="grow truncate text-base font-semibold" dir="auto">
                    {{ asset?.name ?? t('admin.media.details.title') }}
                </h2>
                <button
                    ref="closeButton"
                    type="button"
                    :aria-label="t('common.close')"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="emit('close')"
                >
                    <AppIcon name="close" :size="18" />
                </button>
            </header>

            <div v-if="loading && !asset" class="flex grow items-center justify-center"><AppSpinner /></div>
            <div v-else-if="error" class="p-5"><FormAlert :message="error" /></div>

            <div v-else-if="asset" class="flex grow flex-col gap-6 overflow-y-auto p-5">
                <!-- Preview -->
                <div class="flex min-h-48 items-center justify-center overflow-hidden rounded-2xl bg-surface-muted">
                    <img
                        v-if="asset.type === 'image' && preview"
                        :src="preview"
                        :alt="asset.alt ?? ''"
                        class="max-h-80 w-full object-contain"
                    />
                    <video
                        v-else-if="asset.type === 'video' && asset.urls"
                        :src="asset.urls.original"
                        :poster="cover?.thumb ?? undefined"
                        controls
                        preload="metadata"
                        class="max-h-80 w-full bg-black"
                    />
                    <a
                        v-else-if="asset.urls"
                        :href="asset.urls.original"
                        target="_blank"
                        rel="noopener"
                        class="flex flex-col items-center gap-2 p-8 text-danger hover:underline"
                    >
                        <AppIcon name="file" :size="48" />
                        <span class="text-sm font-medium">{{ t('admin.media.details.openPdf') }}</span>
                    </a>
                </div>

                <!-- In the trash -->
                <div v-if="asset.deleted_at" class="flex flex-col gap-3 rounded-xl bg-danger-soft p-4 text-sm">
                    <p class="text-danger">
                        {{ t('admin.media.details.inTrash', { date: formatDate(purgeDate ?? asset.deleted_at) }) }}
                    </p>
                    <AppButton
                        v-if="canManage"
                        variant="secondary"
                        size="md"
                        class="self-start"
                        @click="emit('restore', asset)"
                    >
                        <AppIcon name="refresh" :size="16" />
                        {{ t('admin.media.restore') }}
                    </AppButton>
                </div>

                <!-- Title, alt text, cover -->
                <form v-if="editable" class="flex flex-col gap-4" @submit.prevent="save">
                    <TextField
                        v-model="alt"
                        :label="t('admin.media.details.alt')"
                        :hint="t('admin.media.details.altHint')"
                        :error="saveErrors.alt"
                        optional
                    />
                    <TextField
                        v-model="title"
                        :label="t('admin.media.details.titleField')"
                        :error="saveErrors.title"
                        optional
                    />
                    <div v-if="asset.type === 'video'" class="flex flex-col gap-2">
                        <span class="text-sm font-semibold">{{ t('admin.media.details.cover') }}</span>
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-16 items-center justify-center overflow-hidden rounded-xl border border-line bg-surface-muted text-fg-muted"
                            >
                                <img v-if="cover?.thumb" :src="cover.thumb" alt="" class="size-full object-cover" />
                                <AppIcon v-else name="video" :size="22" />
                            </span>
                            <AppButton variant="secondary" size="md" type="button" @click="pickingCover = true">{{
                                cover ? t('admin.media.details.changeCover') : t('admin.media.details.chooseCover')
                            }}</AppButton>
                            <button
                                v-if="cover"
                                type="button"
                                class="text-sm text-danger hover:underline"
                                @click="cover = null"
                            >
                                {{ t('admin.media.details.removeCover') }}
                            </button>
                        </div>
                        <p v-if="saveErrors.cover_id" class="text-xs text-danger">{{ saveErrors.cover_id }}</p>
                    </div>
                    <AppButton type="submit" size="md" class="self-start" :disabled="!dirty" :loading="saving">{{
                        t('admin.media.details.save')
                    }}</AppButton>
                </form>
                <div v-else-if="asset.alt || asset.title" class="flex flex-col gap-1 text-sm">
                    <p v-if="asset.alt">
                        <span class="text-fg-muted">{{ t('admin.media.details.alt') }}:</span> {{ asset.alt }}
                    </p>
                    <p v-if="asset.title">
                        <span class="text-fg-muted">{{ t('admin.media.details.titleField') }}:</span> {{ asset.title }}
                    </p>
                </div>

                <!-- Information -->
                <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-fg-muted">{{ t('admin.media.details.type') }}</dt>
                    <dd>
                        {{ t(`admin.media.types.${asset.type}`) }}
                        <span class="text-xs text-fg-muted" dir="ltr">({{ asset.mime_type }})</span>
                    </dd>
                    <dt class="text-fg-muted">{{ t('admin.media.details.size') }}</dt>
                    <dd>{{ size }}</dd>
                    <template v-if="asset.width && asset.height">
                        <dt class="text-fg-muted">{{ t('admin.media.details.dimensions') }}</dt>
                        <dd dir="ltr" class="text-start">
                            {{ formatNumber(asset.width) }} × {{ formatNumber(asset.height) }}
                        </dd>
                    </template>
                    <dt class="text-fg-muted">{{ t('admin.media.details.uploaded') }}</dt>
                    <dd>
                        {{ asset.created_at ? formatDateTime(asset.created_at) : '—' }}
                        <template v-if="asset.uploaded_by"> · {{ asset.uploaded_by.name }}</template>
                    </dd>
                    <template v-if="!canManage || asset.deleted_at">
                        <dt class="text-fg-muted">{{ t('admin.media.details.folder') }}</dt>
                        <dd>{{ asset.folder?.name ?? t('admin.media.folders.none') }}</dd>
                    </template>
                </dl>

                <SelectField
                    v-if="canManage && !asset.deleted_at"
                    :model-value="asset.folder ? String(asset.folder.id) : 'none'"
                    :label="t('admin.media.details.folder')"
                    :options="folderOptions"
                    @update:model-value="move"
                />

                <!-- Where it's used -->
                <section class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold">{{ t('admin.media.details.usages') }}</h3>
                    <ul
                        v-if="asset.usages?.length"
                        class="flex flex-col divide-y divide-line rounded-xl border border-line"
                    >
                        <li v-for="usage in asset.usages" :key="`${usage.type}-${usage.id}-${usage.role}`">
                            <RouterLink
                                :to="usageLink(usage)"
                                class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-surface-muted"
                            >
                                <span class="rounded-md bg-surface-muted px-1.5 py-0.5 text-xs text-fg-muted">{{
                                    t(`admin.media.usageTypes.${usage.type}`)
                                }}</span>
                                <span class="truncate font-medium">{{ usage.name }}</span>
                                <span class="ms-auto text-xs text-fg-muted">{{
                                    t(`admin.media.usageRoles.${usage.role}`)
                                }}</span>
                            </RouterLink>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-fg-muted">{{ t('admin.media.details.notUsed') }}</p>
                    <p v-if="canManage && asset.usages?.length && !asset.deleted_at" class="text-xs text-fg-muted">
                        {{ t('admin.media.details.inUseNoDelete') }}
                    </p>
                </section>
            </div>

            <!-- Actions -->
            <footer
                v-if="asset && !asset.deleted_at && asset.urls"
                class="flex flex-wrap gap-2 border-t border-line px-5 py-3"
            >
                <AppButton variant="secondary" size="md" @click="copyUrl">
                    <AppIcon name="copy" :size="16" />
                    {{ t('admin.media.details.copyUrl') }}
                </AppButton>
                <a
                    :href="asset.urls.original"
                    :download="asset.name"
                    class="inline-flex h-11 items-center gap-2 rounded-[10px] border border-line-strong px-4 text-sm font-semibold hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                >
                    <AppIcon name="download" :size="16" />
                    {{ t('admin.media.details.download') }}
                </a>
                <span class="grow" />
                <AppButton
                    v-if="canManage"
                    variant="danger-outline"
                    size="md"
                    :disabled="!!asset.usages?.length"
                    :title="asset.usages?.length ? t('admin.media.details.inUseNoDelete') : undefined"
                    @click="emit('delete', asset)"
                >
                    <AppIcon name="trash" :size="16" />
                    {{ t('admin.media.delete') }}
                </AppButton>
            </footer>
        </aside>
    </Transition>

    <MediaPickerDialog
        :open="pickingCover"
        :title="t('admin.media.details.chooseCover')"
        type="image"
        @close="pickingCover = false"
        @choose="
            cover = { id: $event[0]!.id, thumb: $event[0]?.urls?.thumb ?? null };
            pickingCover = false;
        "
    />
</template>
