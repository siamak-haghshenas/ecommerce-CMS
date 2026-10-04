<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { byteSize } from '../files';
import type { MediaAsset } from '../types';

/**
 * A file in the library grid: the picture (a video shows its cover), name and size. Selection is
 * shown with a check in the corner; clicking the card opens its details.
 */
const props = defineProps<{ asset: MediaAsset; selected: boolean; selectable: boolean }>();
const emit = defineEmits<{ open: []; toggle: [range: boolean] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const picture = computed(() =>
    props.asset.type === 'image'
        ? (props.asset.urls?.thumb ?? null)
        : props.asset.type === 'video'
          ? (props.asset.cover?.thumb ?? null)
          : null,
);
const size = computed(() => {
    const { value, unit } = byteSize(props.asset.size);

    return `${formatNumber(value)} ${t(`admin.media.units.${unit}`)}`;
});
const extension = computed(() => props.asset.name.split('.').pop()?.toUpperCase() ?? '');
</script>

<template>
    <div
        :class="[
            'group relative flex flex-col overflow-hidden rounded-2xl border bg-surface transition-shadow focus-within:ring-2 focus-within:ring-primary hover:shadow-[0_6px_20px_rgb(0_0_0/0.08)]',
            selected ? 'border-primary ring-2 ring-primary' : 'border-line',
        ]"
    >
        <button
            type="button"
            class="flex cursor-pointer flex-col text-start focus-visible:outline-none"
            :aria-label="t('admin.media.openFile', { name: asset.name })"
            @click="emit('open')"
        >
            <span class="relative flex aspect-square items-center justify-center overflow-hidden bg-surface-muted">
                <img
                    v-if="picture"
                    :src="picture"
                    :alt="asset.alt ?? ''"
                    loading="lazy"
                    class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none"
                />
                <span v-else-if="asset.type === 'document'" class="flex flex-col items-center gap-2 text-danger">
                    <AppIcon name="file" :size="40" />
                    <span class="rounded-md bg-danger px-1.5 py-0.5 text-[11px] font-bold text-on-danger">{{
                        extension
                    }}</span>
                </span>
                <AppIcon v-else :name="asset.type === 'video' ? 'video' : 'image'" :size="40" class="text-fg-muted" />

                <span
                    v-if="asset.type === 'video'"
                    class="absolute flex size-11 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm"
                    aria-hidden="true"
                >
                    <AppIcon name="play" :size="18" />
                </span>
                <span
                    v-if="asset.in_use"
                    class="absolute start-2 bottom-2 rounded-full bg-surface/90 px-2 py-0.5 text-[11px] font-semibold text-success shadow-sm backdrop-blur-sm"
                    >{{ t('admin.media.inUse') }}</span
                >
            </span>
            <span class="flex flex-col gap-0.5 px-3 py-2.5">
                <span class="truncate text-sm font-medium text-fg" :title="asset.name" dir="auto">{{
                    asset.name
                }}</span>
                <span class="text-xs text-fg-muted">
                    {{ size }}
                    <template v-if="asset.width && asset.height">
                        · <span dir="ltr">{{ formatNumber(asset.width) }}×{{ formatNumber(asset.height) }}</span>
                    </template>
                </span>
            </span>
        </button>

        <!-- Selection: visible on hover, focus or when selected -->
        <label
            v-if="selectable"
            :class="[
                'absolute end-2 top-2 flex size-7 cursor-pointer items-center justify-center rounded-lg shadow-sm transition-opacity',
                selected
                    ? 'bg-primary opacity-100'
                    : 'bg-surface/90 opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 pointer-coarse:opacity-100',
            ]"
        >
            <input
                type="checkbox"
                :checked="selected"
                :aria-label="t('admin.media.select', { name: asset.name })"
                class="sr-only"
                @click="emit('toggle', ($event as MouseEvent).shiftKey)"
            />
            <AppIcon v-if="selected" name="check" :size="16" class="text-on-primary" />
            <span v-else class="size-4 rounded border-2 border-line-strong" aria-hidden="true" />
        </label>
    </div>
</template>
