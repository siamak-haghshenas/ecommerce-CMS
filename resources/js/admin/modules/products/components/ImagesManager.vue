<script setup lang="ts">
import { computed, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import MediaPickerDialog from '../../media/components/MediaPickerDialog.vue';
import { pictureOf, type MediaAsset } from '../../media/types';
import type { ProductImage } from '../types';

const MAX_IMAGES = 30;

/**
 * The product gallery: pictures chosen from the media library (or uploaded there from the picker),
 * saved with the product. Drag to reorder; the first image is the main image.
 */
const images = defineModel<ProductImage[]>({ required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();
const picking = ref(false);
const room = computed(() => MAX_IMAGES - images.value.length);

function add(assets: MediaAsset[]): void {
    const known = new Set(images.value.map((image) => image.id));
    const added = assets
        .filter((asset) => !known.has(asset.id))
        .map(pictureOf)
        .filter((picture): picture is ProductImage => picture !== null)
        .slice(0, room.value);

    images.value = [...images.value, ...added];
    picking.value = false;
}

function makeMain(image: ProductImage): void {
    images.value = [image, ...images.value.filter((item) => item.id !== image.id)];
}

function remove(image: ProductImage): void {
    images.value = images.value.filter((item) => item.id !== image.id);
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.products.images.hint') }}</p>

        <VueDraggable
            v-if="images.length"
            v-model="images"
            tag="ul"
            :animation="150"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
        >
            <li
                v-for="(image, index) in images"
                :key="image.id"
                class="group relative aspect-square cursor-grab overflow-hidden rounded-xl border border-line bg-surface-muted active:cursor-grabbing"
            >
                <img
                    :src="image.thumb ?? image.original"
                    :alt="image.alt ?? ''"
                    class="size-full object-contain"
                    loading="lazy"
                />
                <span
                    v-if="index === 0"
                    class="absolute start-2 top-2 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-on-primary"
                >
                    {{ t('admin.products.images.main') }}
                </span>
                <span
                    v-if="!image.alt"
                    class="absolute end-2 top-2 rounded-full bg-warning-soft px-2 py-0.5 text-[11px] font-semibold text-warning"
                    :title="t('admin.products.images.noAltHint')"
                    >{{ t('admin.products.images.noAlt') }}</span
                >
                <div class="absolute inset-x-2 bottom-2 flex justify-end gap-1.5">
                    <button
                        v-if="index > 0"
                        type="button"
                        :aria-label="t('admin.products.images.makeMain', { n: index + 1 })"
                        :title="t('admin.products.images.makeMain', { n: index + 1 })"
                        class="flex size-9 cursor-pointer items-center justify-center rounded-lg bg-surface/90 text-fg shadow hover:bg-surface focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="makeMain(image)"
                    >
                        <AppIcon name="arrowUp" :size="16" />
                    </button>
                    <button
                        type="button"
                        :aria-label="t('admin.products.images.remove', { n: index + 1 })"
                        :title="t('admin.products.images.remove', { n: index + 1 })"
                        class="flex size-9 cursor-pointer items-center justify-center rounded-lg bg-surface/90 text-danger shadow hover:bg-surface focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="remove(image)"
                    >
                        <AppIcon name="close" :size="16" />
                    </button>
                </div>
            </li>
        </VueDraggable>

        <div class="flex flex-wrap items-center gap-3">
            <AppButton variant="secondary" size="md" :disabled="room <= 0" @click="picking = true">
                <AppIcon name="image" :size="18" />
                {{ t('admin.products.images.add') }}
            </AppButton>
            <span class="text-[13px] text-fg-muted">{{
                t('admin.products.images.count', { n: formatNumber(images.length), max: formatNumber(MAX_IMAGES) })
            }}</span>
        </div>

        <MediaPickerDialog
            :open="picking"
            :title="t('admin.products.images.add')"
            type="image"
            multiple
            :exclude="images.map((image) => image.id)"
            @close="picking = false"
            @choose="add"
        />
    </div>
</template>
