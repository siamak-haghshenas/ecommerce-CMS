<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppIcon, { type IconName } from '@/shared/ui/AppIcon.vue';
import type { MediaFolder } from '../types';

/** The library's places: all files, no folder, each folder, and the trash. */
export type MediaPlace = 'all' | 'none' | 'trash' | number;

defineProps<{ folders: MediaFolder[]; active: MediaPlace; canManage: boolean }>();
const emit = defineEmits<{
    select: [place: MediaPlace];
    create: [];
    rename: [folder: MediaFolder];
    remove: [folder: MediaFolder];
}>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const folderMenu = (): ActionMenuItem[] => [
    { key: 'rename', label: t('admin.media.folders.rename'), icon: 'pen' },
    { key: 'remove', label: t('admin.media.folders.delete'), icon: 'trash', danger: true },
];

const itemClass = (on: boolean): string[] => [
    'flex h-10 w-full min-w-max cursor-pointer items-center gap-2.5 rounded-xl px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none lg:min-w-0',
    on ? 'bg-primary-soft font-semibold text-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg',
];

const fixed: { place: MediaPlace; icon: IconName; label: string }[] = [
    { place: 'all', icon: 'grid', label: 'admin.media.folders.all' },
    { place: 'none', icon: 'image', label: 'admin.media.folders.none' },
];
</script>

<template>
    <nav :aria-label="t('admin.media.folders.title')" class="flex flex-col gap-1">
        <div class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
            <button
                v-for="item in fixed"
                :key="String(item.place)"
                type="button"
                :aria-current="active === item.place ? 'page' : undefined"
                :class="itemClass(active === item.place)"
                @click="emit('select', item.place)"
            >
                <AppIcon :name="item.icon" :size="18" />
                {{ t(item.label) }}
            </button>

            <p class="mt-3 mb-1 hidden items-center justify-between px-3 text-xs font-semibold text-fg-muted lg:flex">
                {{ t('admin.media.folders.title') }}
                <button
                    v-if="canManage"
                    type="button"
                    :aria-label="t('admin.media.folders.new')"
                    :title="t('admin.media.folders.new')"
                    class="flex size-7 cursor-pointer items-center justify-center rounded-lg hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="emit('create')"
                >
                    <AppIcon name="plus" :size="16" />
                </button>
            </p>

            <div v-for="folder in folders" :key="folder.id" class="group relative flex items-center">
                <button
                    type="button"
                    :aria-current="active === folder.id ? 'page' : undefined"
                    :class="[...itemClass(active === folder.id), canManage && 'lg:pe-10']"
                    @click="emit('select', folder.id)"
                >
                    <AppIcon name="folder" :size="18" />
                    <span class="truncate">{{ folder.name }}</span>
                    <span class="ms-auto text-xs tabular-nums">{{ formatNumber(folder.files_count ?? 0) }}</span>
                </button>
                <div v-if="canManage" class="absolute end-1 hidden lg:block">
                    <ActionMenu
                        :label="t('admin.media.folders.actionsFor', { name: folder.name })"
                        :items="folderMenu()"
                        @select="$event === 'rename' ? emit('rename', folder) : emit('remove', folder)"
                    />
                </div>
            </div>

            <button
                v-if="canManage"
                type="button"
                class="flex h-10 min-w-max cursor-pointer items-center gap-2.5 rounded-xl border border-dashed border-line-strong px-3 text-sm text-fg-muted hover:text-fg lg:hidden"
                @click="emit('create')"
            >
                <AppIcon name="folderPlus" :size="18" />
                {{ t('admin.media.folders.new') }}
            </button>

            <span class="hidden lg:mt-3 lg:block lg:border-t lg:border-line" aria-hidden="true" />
            <button
                v-if="canManage"
                type="button"
                :aria-current="active === 'trash' ? 'page' : undefined"
                :class="itemClass(active === 'trash')"
                @click="emit('select', 'trash')"
            >
                <AppIcon name="trash" :size="18" />
                {{ t('admin.media.folders.trash') }}
            </button>
        </div>
    </nav>
</template>
