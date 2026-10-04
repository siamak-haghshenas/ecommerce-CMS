<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { mediaApi } from '../api';
import { useMediaUploadStore } from '../store';
import type { MediaAsset, MediaType } from '../types';

/**
 * Choose files from the media library, or upload new ones (they are chosen as soon as they finish).
 * Used for product galleries, category images, brand logos and video covers.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        type?: MediaType;
        /** Choose several (in the order they are clicked). */
        multiple?: boolean;
        /** Already used: shown as such and not chosen again. */
        exclude?: number[];
    }>(),
    { type: undefined, multiple: false, exclude: () => [] },
);
const emit = defineEmits<{ close: []; choose: [assets: MediaAsset[]] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();
const auth = useAdminAuthStore();
const uploads = useMediaUploadStore();
const canUpload = computed(() => auth.can(Permission.UploadMedia));
const fileInput = useTemplateRef('fileInput');

const search = ref('');
const assets = ref<MediaAsset[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const error = ref<string | null>(null);
const chosen = ref<MediaAsset[]>([]);
/** Uploads started here: their files are chosen when they finish. */
const ownUploads = ref<Set<number>>(new Set());

async function load(reset = true): Promise<void> {
    loading.value = true;
    error.value = null;

    if (reset) {
        page.value = 1;
    }

    try {
        const result = await mediaApi.list({
            q: search.value || undefined,
            type: props.type,
            page: page.value,
            per_page: 30,
        });
        assets.value = reset ? result.data : [...assets.value, ...result.data];
        lastPage.value = result.meta.last_page;
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

function more(): void {
    page.value++;
    void load(false);
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            chosen.value = [];
            ownUploads.value = new Set();
            search.value = '';
            void load();
        }
    },
    { immediate: true },
);
watch(search, () => load());

const isChosen = (asset: MediaAsset): boolean => chosen.value.some((item) => item.id === asset.id);
const isExcluded = (asset: MediaAsset): boolean => props.exclude.includes(asset.id);

function toggle(asset: MediaAsset): void {
    if (isExcluded(asset)) {
        return;
    }

    if (!props.multiple) {
        chosen.value = [asset];
    } else if (isChosen(asset)) {
        chosen.value = chosen.value.filter((item) => item.id !== asset.id);
    } else {
        chosen.value = [...chosen.value, asset];
    }
}

function confirm(): void {
    if (chosen.value.length) {
        emit('choose', chosen.value);
    }
}

// ---- uploading from the picker ----

function upload(files: FileList | null): void {
    if (!files?.length) {
        return;
    }

    const before = new Set(uploads.items.map((item) => item.key));
    uploads.add([...files], null);
    const added = uploads.items.filter((item) => !before.has(item.key)).map((item) => item.key);
    ownUploads.value = new Set([...ownUploads.value, ...added]);
}

const pending = computed(() =>
    uploads.items.filter(
        (item) => ownUploads.value.has(item.key) && (item.status === 'queued' || item.status === 'uploading'),
    ),
);

// Finished uploads join the list and are chosen.
watch(
    () =>
        uploads.items
            .filter((item) => ownUploads.value.has(item.key) && item.asset)
            .map((item) => item.asset as MediaAsset),
    (finished) => {
        for (const asset of finished) {
            if (assets.value.some((item) => item.id === asset.id) || (props.type && asset.type !== props.type)) {
                continue;
            }

            assets.value = [asset, ...assets.value];
            chosen.value = props.multiple ? [...chosen.value, asset] : [asset];
        }
    },
);

const accept = computed(() =>
    props.type === 'image'
        ? 'image/jpeg,image/png,image/webp,image/avif,image/gif'
        : 'image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf,video/mp4,video/webm',
);
</script>

<template>
    <AppDialog :open="open" :title="title" icon="image" size="lg" @close="emit('close')">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <div class="min-w-48 grow">
                    <SearchInput v-model="search" :label="t('admin.media.search')" />
                </div>
                <AppButton v-if="canUpload" variant="secondary" size="md" @click="fileInput?.click()">
                    <AppIcon name="upload" :size="16" />
                    {{ t('admin.media.upload') }}
                </AppButton>
                <input
                    ref="fileInput"
                    type="file"
                    :multiple="multiple"
                    :accept="accept"
                    class="hidden"
                    @change="
                        upload(($event.target as HTMLInputElement).files);
                        ($event.target as HTMLInputElement).value = '';
                    "
                />
            </div>

            <p v-if="pending.length" class="flex items-center gap-2 text-sm text-fg-muted" aria-live="polite">
                <span
                    class="size-4 animate-spin rounded-full border-2 border-primary/30 border-t-primary motion-reduce:animate-none"
                    aria-hidden="true"
                />
                {{ t('admin.media.uploads.uploading', { n: formatNumber(pending.length) }) }}
            </p>

            <FormAlert v-if="error" :message="error" />
            <EmptyState
                v-else-if="!loading && assets.length === 0"
                icon="image"
                :title="t('admin.media.pickerEmpty')"
            />
            <ul
                v-else
                class="grid max-h-[50vh] grid-cols-3 gap-2 overflow-y-auto p-0.5 sm:grid-cols-4"
                :aria-busy="loading"
            >
                <li v-for="asset in assets" :key="asset.id">
                    <button
                        type="button"
                        :aria-pressed="isChosen(asset)"
                        :aria-label="
                            isExcluded(asset) ? t('admin.media.alreadyUsed', { name: asset.name }) : asset.name
                        "
                        :disabled="isExcluded(asset)"
                        :class="[
                            'relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-xl border bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                            isExcluded(asset)
                                ? 'cursor-not-allowed border-line opacity-40'
                                : isChosen(asset)
                                  ? 'cursor-pointer border-primary ring-2 ring-primary'
                                  : 'cursor-pointer border-line hover:border-line-strong',
                        ]"
                        @click="toggle(asset)"
                        @dblclick="!multiple && emit('choose', [asset])"
                    >
                        <img
                            v-if="asset.urls?.thumb"
                            :src="asset.urls.thumb"
                            :alt="asset.alt ?? ''"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                        <AppIcon v-else name="file" :size="28" class="text-fg-muted" />
                        <span
                            v-if="isChosen(asset)"
                            class="absolute end-1.5 top-1.5 flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary"
                        >
                            <template v-if="multiple">{{
                                formatNumber(chosen.findIndex((item) => item.id === asset.id) + 1)
                            }}</template>
                            <AppIcon v-else name="check" :size="14" />
                        </span>
                    </button>
                </li>
            </ul>
            <AppButton
                v-if="page < lastPage"
                variant="secondary"
                size="md"
                :loading="loading"
                class="self-center"
                @click="more"
            >
                {{ t('admin.media.loadMore') }}
            </AppButton>
        </div>
        <template #footer>
            <AppButton size="md" :disabled="chosen.length === 0" @click="confirm">{{
                multiple && chosen.length
                    ? t('admin.media.chooseCount', { n: formatNumber(chosen.length) })
                    : t('admin.media.choose')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
