<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { mediaApi } from '../api';
import MediaCard from '../components/MediaCard.vue';
import MediaDetailsDrawer from '../components/MediaDetailsDrawer.vue';
import MediaFolderNav, { type MediaPlace } from '../components/MediaFolderNav.vue';
import { useMediaUploadStore } from '../store';
import type { MediaAsset, MediaFilters, MediaFolder, MediaType } from '../types';

const PER_PAGE = 40;

const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const uploads = useMediaUploadStore();
const canUpload = computed(() => auth.can(Permission.UploadMedia));
const canManage = computed(() => auth.can(Permission.ManageMedia));

// ---- filters (in the URL) ----

const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

function updateQuery(changes: Record<string, string | undefined>): void {
    void router.replace({ query: { ...route.query, ...changes } });
}

const place = computed<MediaPlace>(() => {
    const value = queryString('folder');

    return value === '' ? 'all' : value === 'none' || value === 'trash' ? value : Number(value);
});
const search = computed({ get: () => queryString('q'), set: (q: string) => updateQuery({ q: q || undefined }) });
const type = computed<MediaType | 'all'>(() =>
    ['image', 'document', 'video'].includes(queryString('type')) ? (queryString('type') as MediaType) : 'all',
);
const used = computed({
    get: () => queryString('used') || 'all',
    set: (value: string) => updateQuery({ used: value === 'all' ? undefined : value }),
});
const sort = computed({
    get: () => queryString('sort') || 'newest',
    set: (value: string) => updateQuery({ sort: value === 'newest' ? undefined : value }),
});

const typeChips = computed(() =>
    (['all', 'image', 'video', 'document'] as const).map((value) => ({
        value,
        label: t(`admin.media.typeFilter.${value}`),
    })),
);
const usedOptions = computed(() => [
    { value: 'all', label: t('admin.media.usedFilter.all') },
    { value: '1', label: t('admin.media.usedFilter.used') },
    { value: '0', label: t('admin.media.usedFilter.unused') },
]);
const sortOptions = computed(() =>
    (['newest', 'oldest', 'name', 'size'] as const).map((value) => ({ value, label: t(`admin.media.sort.${value}`) })),
);

function selectPlace(next: MediaPlace): void {
    selected.value = new Set();
    updateQuery({ folder: next === 'all' ? undefined : String(next) });
}

// ---- folders ----

const folders = ref<MediaFolder[]>([]);
const currentFolder = computed(() =>
    typeof place.value === 'number' ? folders.value.find((folder) => folder.id === place.value) : undefined,
);

async function loadFolders(): Promise<void> {
    folders.value = await mediaApi.folders().catch(() => folders.value);
}

// ---- files ----

const assets = ref<MediaAsset[]>([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const loaded = ref(false);
const loadError = ref<string | null>(null);

function filters(): MediaFilters {
    return {
        q: search.value || undefined,
        type: type.value === 'all' ? undefined : type.value,
        folder: typeof place.value === 'number' || place.value === 'none' ? place.value : undefined,
        trashed: place.value === 'trash' ? 1 : undefined,
        used: used.value === 'all' ? undefined : (Number(used.value) as 0 | 1),
        sort: sort.value as MediaFilters['sort'],
        page: page.value,
        per_page: PER_PAGE,
    };
}

async function load(reset = true): Promise<void> {
    loading.value = true;
    loadError.value = null;

    if (reset) {
        page.value = 1;
    }

    try {
        const result = await mediaApi.list(filters());
        assets.value = reset ? result.data : [...assets.value, ...result.data];
        total.value = result.meta.total;
        lastPage.value = result.meta.last_page;
        loaded.value = true;
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

function loadMore(): void {
    page.value++;
    void load(false);
}

watch(
    () => [route.query.folder, route.query.q, route.query.type, route.query.used, route.query.sort],
    () => load(),
    { immediate: true },
);
void loadFolders();

// New files appear as their uploads finish.
watch(
    () => uploads.finished,
    () => {
        void load();
        void loadFolders();
    },
);

// ---- uploading: the button, or files dropped anywhere on the page ----

const fileInput = useTemplateRef('fileInput');
const dragging = ref(false);
let dragDepth = 0;

function upload(files: FileList | File[] | null): void {
    if (!files || files.length === 0 || !canUpload.value) {
        return;
    }

    uploads.add([...files], typeof place.value === 'number' ? place.value : null);
}

const hasFiles = (event: DragEvent): boolean => [...(event.dataTransfer?.types ?? [])].includes('Files');

function onDragEnter(event: DragEvent): void {
    if (canUpload.value && hasFiles(event)) {
        dragDepth++;
        dragging.value = true;
    }
}

function onDragLeave(): void {
    dragDepth = Math.max(0, dragDepth - 1);
    dragging.value = dragDepth > 0;
}

function onDragOver(event: DragEvent): void {
    if (canUpload.value && hasFiles(event)) {
        event.preventDefault();
    }
}

function onDrop(event: DragEvent): void {
    if (!hasFiles(event)) {
        return;
    }

    event.preventDefault();
    dragDepth = 0;
    dragging.value = false;
    upload(event.dataTransfer?.files ?? null);
}

window.addEventListener('dragenter', onDragEnter);
window.addEventListener('dragleave', onDragLeave);
window.addEventListener('dragover', onDragOver);
window.addEventListener('drop', onDrop);
onBeforeUnmount(() => {
    window.removeEventListener('dragenter', onDragEnter);
    window.removeEventListener('dragleave', onDragLeave);
    window.removeEventListener('dragover', onDragOver);
    window.removeEventListener('drop', onDrop);
});

// ---- selection (move / delete / restore several at once) ----

const selected = ref<Set<number>>(new Set());
let lastToggled: number | null = null;

function toggle(id: number, range: boolean): void {
    const next = new Set(selected.value);
    const on = !next.has(id);
    const order = assets.value.map((asset) => asset.id);
    const from = lastToggled === null ? -1 : order.indexOf(lastToggled);
    const to = order.indexOf(id);
    const ids = range && from !== -1 ? order.slice(Math.min(from, to), Math.max(from, to) + 1) : [id];

    for (const each of ids) {
        if (on) {
            next.add(each);
        } else {
            next.delete(each);
        }
    }

    lastToggled = id;
    selected.value = next;
}

// ---- details ----

const openId = computed(() => (Number(queryString('file')) > 0 ? Number(queryString('file')) : null));
const open = (asset: MediaAsset): void => updateQuery({ file: String(asset.id) });
const closeDetails = (): void => updateQuery({ file: undefined });

function onChanged(asset: MediaAsset): void {
    assets.value = assets.value.map((item) =>
        item.id === asset.id ? { ...item, ...asset, in_use: item.in_use } : item,
    );
    void loadFolders();
}

// ---- moving, deleting, restoring ----

const moveOpen = ref(false);
const moveTarget = ref('none');
const busy = ref(false);
const moveOptions = computed(() => [
    { value: 'none', label: t('admin.media.folders.none') },
    ...folders.value.map((folder) => ({ value: String(folder.id), label: folder.name })),
]);

async function moveSelected(): Promise<void> {
    busy.value = true;

    try {
        const moved = await mediaApi.move(
            [...selected.value],
            moveTarget.value === 'none' ? null : Number(moveTarget.value),
        );
        toast.show(t('admin.media.moved', { n: formatNumber(moved) }));
        moveOpen.value = false;
        selected.value = new Set();
        await Promise.all([load(), loadFolders()]);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

const deleting = ref<MediaAsset[] | null>(null);

function askDelete(items: MediaAsset[]): void {
    deleting.value = items;
}

async function confirmDelete(): Promise<void> {
    const items = deleting.value ?? [];
    busy.value = true;
    let removed = 0;
    let inUse = 0;

    for (const item of items) {
        try {
            await mediaApi.remove(item.id);
            removed++;
        } catch (caught) {
            const error = toApiError(caught);

            if (error.code === 'media_in_use') {
                inUse++;
            } else {
                toast.show(error.message, 'danger');
            }
        }
    }

    busy.value = false;
    deleting.value = null;
    selected.value = new Set();

    if (removed) {
        toast.show(t('admin.media.deleted', { n: formatNumber(removed) }));
    }

    if (inUse) {
        toast.show(t('admin.media.deleteInUse', { n: formatNumber(inUse) }), 'danger');
    }

    if (openId.value !== null && items.some((item) => item.id === openId.value) && removed) {
        closeDetails();
    }

    await Promise.all([load(), loadFolders()]);
}

async function restore(items: MediaAsset[]): Promise<void> {
    busy.value = true;

    try {
        for (const item of items) {
            await mediaApi.restore(item.id);
        }

        toast.show(t('admin.media.restored', { n: formatNumber(items.length) }));
        selected.value = new Set();

        if (openId.value !== null && items.some((item) => item.id === openId.value)) {
            closeDetails();
        }

        await Promise.all([load(), loadFolders()]);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

const selectedAssets = computed(() => assets.value.filter((asset) => selected.value.has(asset.id)));

// ---- folders: create, rename, delete ----

const folderDialog = ref<{ folder: MediaFolder | null } | null>(null);
const folderName = ref('');
const folderError = ref<string | undefined>(undefined);
const deletingFolder = ref<MediaFolder | null>(null);

function openFolderDialog(folder: MediaFolder | null): void {
    folderDialog.value = { folder };
    folderName.value = folder?.name ?? '';
    folderError.value = undefined;
}

async function saveFolder(): Promise<void> {
    const target = folderDialog.value?.folder ?? null;
    busy.value = true;
    folderError.value = undefined;

    try {
        const saved = target
            ? await mediaApi.renameFolder(target.id, folderName.value)
            : await mediaApi.createFolder(folderName.value);
        folderDialog.value = null;
        await loadFolders();

        if (!target) {
            selectPlace(saved.id);
        }
    } catch (caught) {
        const error = toApiError(caught);
        folderError.value = error.fieldErrors.name?.[0] ?? error.message;
    } finally {
        busy.value = false;
    }
}

async function deleteFolder(): Promise<void> {
    const folder = deletingFolder.value;

    if (!folder) {
        return;
    }

    busy.value = true;

    try {
        await mediaApi.deleteFolder(folder.id);
        deletingFolder.value = null;

        if (place.value === folder.id) {
            selectPlace('all');
        }

        await Promise.all([loadFolders(), load()]);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

// ---- page heading ----

const heading = computed(() =>
    place.value === 'trash'
        ? t('admin.media.folders.trash')
        : place.value === 'none'
          ? t('admin.media.folders.none')
          : (currentFolder.value?.name ?? t('admin.media.folders.all')),
);
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col gap-5">
        <!-- Title -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.media.title') }}</h1>
                <p class="text-sm text-fg-muted">{{ t('admin.media.subtitle') }}</p>
            </div>
            <AppButton v-if="canUpload" size="md" @click="fileInput?.click()">
                <AppIcon name="upload" :size="18" />
                {{ t('admin.media.upload') }}
            </AppButton>
            <input
                ref="fileInput"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf,video/mp4,video/webm"
                class="hidden"
                @change="
                    upload(($event.target as HTMLInputElement).files);
                    ($event.target as HTMLInputElement).value = '';
                "
            />
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <!-- Folders -->
            <aside class="lg:sticky lg:top-4 lg:self-start">
                <MediaFolderNav
                    :folders="folders"
                    :active="place"
                    :can-manage="canManage"
                    @select="selectPlace"
                    @create="openFolderDialog(null)"
                    @rename="openFolderDialog"
                    @remove="deletingFolder = $event"
                />
            </aside>

            <div class="flex min-w-0 flex-col gap-4">
                <!-- Search and filters -->
                <div class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="min-w-56 grow">
                            <SearchInput v-model="search" :label="t('admin.media.search')" />
                        </div>
                        <SelectField
                            v-if="place !== 'trash'"
                            v-model="used"
                            :label="t('admin.media.usedFilter.label')"
                            :options="usedOptions"
                        />
                        <SelectField v-model="sort" :label="t('admin.media.sort.label')" :options="sortOptions" />
                    </div>
                    <div role="group" :aria-label="t('admin.media.typeFilter.label')" class="flex flex-wrap gap-2">
                        <button
                            v-for="chip in typeChips"
                            :key="chip.value"
                            type="button"
                            :aria-pressed="type === chip.value"
                            :class="[
                                'flex h-9 cursor-pointer items-center rounded-full border px-3.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                type === chip.value
                                    ? 'border-fg bg-fg font-semibold text-bg'
                                    : 'border-line text-fg-muted hover:border-line-strong hover:text-fg',
                            ]"
                            @click="updateQuery({ type: chip.value === 'all' ? undefined : chip.value })"
                        >
                            {{ chip.label }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                    <h2 class="flex items-center gap-2 font-semibold">
                        {{ heading }}
                        <span class="text-sm font-normal text-fg-muted">{{
                            t('admin.media.count', { n: formatNumber(total) })
                        }}</span>
                    </h2>
                    <p v-if="place === 'trash'" class="text-xs text-fg-muted">{{ t('admin.media.trashHint') }}</p>
                </div>

                <!-- Selection actions -->
                <div
                    v-if="selected.size && canManage"
                    role="region"
                    :aria-label="t('admin.media.selection')"
                    class="flex flex-wrap items-center gap-2 rounded-xl border border-primary/40 bg-primary-soft/50 px-4 py-2.5"
                >
                    <span class="text-sm font-semibold">{{
                        t('admin.media.selected', { n: formatNumber(selected.size) })
                    }}</span>
                    <button type="button" class="text-sm text-primary hover:underline" @click="selected = new Set()">
                        {{ t('admin.media.clearSelection') }}
                    </button>
                    <span class="grow" />
                    <template v-if="place === 'trash'">
                        <AppButton variant="secondary" size="md" :loading="busy" @click="restore(selectedAssets)">
                            <AppIcon name="refresh" :size="16" />
                            {{ t('admin.media.restore') }}
                        </AppButton>
                    </template>
                    <template v-else>
                        <AppButton variant="secondary" size="md" @click="moveOpen = true">
                            <AppIcon name="folderMove" :size="16" />
                            {{ t('admin.media.moveTo') }}
                        </AppButton>
                        <AppButton variant="danger-outline" size="md" @click="askDelete(selectedAssets)">
                            <AppIcon name="trash" :size="16" />
                            {{ t('admin.media.delete') }}
                        </AppButton>
                    </template>
                </div>

                <!-- Grid -->
                <div v-if="loadError" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="grow"><FormAlert :message="loadError" /></div>
                    <AppButton variant="secondary" size="md" :loading="loading" @click="load()">{{
                        t('common.retry')
                    }}</AppButton>
                </div>
                <div
                    v-else-if="!loaded"
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5"
                    aria-hidden="true"
                >
                    <div
                        v-for="n in 10"
                        :key="n"
                        class="aspect-[4/5] animate-pulse rounded-2xl bg-surface-muted/70 motion-reduce:animate-none"
                    />
                </div>
                <template v-else-if="assets.length === 0">
                    <button
                        v-if="canUpload && place !== 'trash' && !search && type === 'all' && used === 'all'"
                        type="button"
                        class="flex cursor-pointer flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-line-strong bg-surface px-6 py-16 text-center hover:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="fileInput?.click()"
                    >
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-primary">
                            <AppIcon name="upload" :size="26" />
                        </span>
                        <span class="font-semibold">{{ t('admin.media.emptyTitle') }}</span>
                        <span class="max-w-md text-sm text-fg-muted">{{ t('admin.media.emptyText') }}</span>
                    </button>
                    <EmptyState
                        v-else
                        :icon="place === 'trash' ? 'trash' : 'search'"
                        :title="place === 'trash' ? t('admin.media.trashEmpty') : t('admin.media.noResults')"
                        class="rounded-2xl border border-line bg-surface"
                    />
                </template>
                <template v-else>
                    <ul
                        :aria-busy="loading"
                        :class="[
                            'grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-5',
                            loading && 'opacity-60',
                        ]"
                    >
                        <li v-for="asset in assets" :key="asset.id">
                            <MediaCard
                                :asset="asset"
                                :selected="selected.has(asset.id)"
                                :selectable="canManage"
                                @open="open(asset)"
                                @toggle="toggle(asset.id, $event)"
                            />
                        </li>
                    </ul>
                    <AppButton
                        v-if="page < lastPage"
                        variant="secondary"
                        size="md"
                        class="self-center"
                        :loading="loading"
                        @click="loadMore"
                    >
                        {{ t('admin.media.loadMore') }}
                    </AppButton>
                </template>
                <p v-if="canUpload && place !== 'trash'" class="text-center text-xs text-fg-muted">
                    {{ t('admin.media.limits') }}
                </p>
            </div>
        </div>

        <!-- Drop anywhere -->
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="dragging"
                class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center bg-primary/15 p-6 backdrop-blur-[2px]"
            >
                <div
                    class="flex flex-col items-center gap-3 rounded-3xl border-2 border-dashed border-primary bg-surface px-12 py-10 text-center shadow-xl"
                >
                    <AppIcon name="upload" :size="36" class="text-primary" />
                    <p class="text-lg font-semibold">{{ t('admin.media.dropTitle') }}</p>
                    <p class="text-sm text-fg-muted">
                        {{
                            currentFolder
                                ? t('admin.media.dropInto', { folder: currentFolder.name })
                                : t('admin.media.dropText')
                        }}
                    </p>
                </div>
            </div>
        </Transition>

        <MediaDetailsDrawer
            :asset-id="openId"
            :folders="folders"
            :can-upload="canUpload"
            :can-manage="canManage"
            @close="closeDetails"
            @changed="onChanged"
            @delete="askDelete([$event])"
            @restore="restore([$event])"
        />

        <!-- Move to a folder -->
        <AppDialog
            :open="moveOpen"
            :title="t('admin.media.moveTitle', { n: formatNumber(selected.size) })"
            icon="folderMove"
            :busy="busy"
            @close="moveOpen = false"
        >
            <SelectField v-model="moveTarget" :label="t('admin.media.details.folder')" :options="moveOptions" />
            <template #footer>
                <AppButton size="md" :loading="busy" @click="moveSelected">{{ t('admin.media.move') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="moveOpen = false">{{ t('common.cancel') }}</AppButton>
            </template>
        </AppDialog>

        <!-- Delete -->
        <AppDialog
            :open="deleting !== null"
            :title="t('admin.media.deleteTitle', { n: formatNumber(deleting?.length ?? 0) })"
            icon="trash"
            tone="danger"
            :busy="busy"
            @close="deleting = null"
        >
            <p class="text-sm leading-loose">{{ t('admin.media.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="busy" @click="confirmDelete">{{
                    t('admin.media.delete')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="deleting = null">{{ t('common.cancel') }}</AppButton>
            </template>
        </AppDialog>

        <!-- Create / rename a folder -->
        <AppDialog
            :open="folderDialog !== null"
            :title="folderDialog?.folder ? t('admin.media.folders.rename') : t('admin.media.folders.new')"
            icon="folder"
            :busy="busy"
            @close="folderDialog = null"
        >
            <form id="media-folder-form" @submit.prevent="saveFolder">
                <TextField v-model="folderName" :label="t('admin.media.folders.name')" :error="folderError" autofocus />
            </form>
            <template #footer>
                <AppButton
                    type="submit"
                    form="media-folder-form"
                    size="md"
                    :loading="busy"
                    :disabled="!folderName.trim()"
                    >{{ t('admin.media.folders.save') }}</AppButton
                >
                <AppButton variant="secondary" size="md" @click="folderDialog = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <!-- Delete a folder -->
        <AppDialog
            :open="deletingFolder !== null"
            :title="t('admin.media.folders.deleteTitle', { name: deletingFolder?.name ?? '' })"
            icon="folder"
            tone="danger"
            :busy="busy"
            @close="deletingFolder = null"
        >
            <p class="text-sm leading-loose">
                {{ t('admin.media.folders.deleteText', { n: formatNumber(deletingFolder?.files_count ?? 0) }) }}
            </p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="busy" @click="deleteFolder">{{
                    t('admin.media.folders.delete')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="deletingFolder = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
