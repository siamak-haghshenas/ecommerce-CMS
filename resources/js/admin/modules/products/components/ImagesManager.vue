<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import { productsApi } from '../api';
import type { ProductImage } from '../types';

const MAX_MEGABYTES = 8;
const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * The product gallery. Changes are saved at once (not with the form); the first image is the
 * main image. Images can only be added once the product exists.
 */
const props = defineProps<{ productId: number | null }>();
const images = defineModel<ProductImage[]>({ required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const input = useTemplateRef('input');
const uploading = ref(0);

async function upload(event: Event): Promise<void> {
    const files = [...((event.target as HTMLInputElement).files ?? [])];
    (event.target as HTMLInputElement).value = '';

    if (props.productId === null) {
        return;
    }

    for (const file of files) {
        if (!ACCEPTED.includes(file.type) || file.size > MAX_MEGABYTES * 1024 * 1024) {
            toast.show(
                t('admin.products.images.invalid', { name: file.name, size: formatNumber(MAX_MEGABYTES) }),
                'danger',
            );
            continue;
        }

        uploading.value++;

        try {
            images.value = await productsApi.uploadImage(props.productId, file);
        } catch (caught) {
            toast.show(toApiError(caught).message, 'danger');
        } finally {
            uploading.value--;
        }
    }
}

async function onDrop(): Promise<void> {
    await nextTick();

    if (props.productId === null) {
        return;
    }

    try {
        images.value = await productsApi.reorderImages(
            props.productId,
            images.value.map((image) => image.id),
        );
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

async function makeMain(image: ProductImage): Promise<void> {
    if (props.productId === null) {
        return;
    }

    const ids = [image.id, ...images.value.filter((item) => item.id !== image.id).map((item) => item.id)];

    try {
        images.value = await productsApi.reorderImages(props.productId, ids);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

async function remove(image: ProductImage): Promise<void> {
    if (props.productId === null) {
        return;
    }

    try {
        images.value = await productsApi.removeImage(props.productId, image.id);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <p
            v-if="productId === null"
            role="note"
            class="flex items-center gap-2.5 rounded-xl bg-surface-muted px-4 py-3 text-sm text-fg-muted"
        >
            <AppIcon name="info" :size="18" />
            {{ t('admin.products.images.saveFirst') }}
        </p>

        <template v-else>
            <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.products.images.hint') }}</p>

            <VueDraggable
                v-model="images"
                tag="ul"
                :animation="150"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                @update="onDrop"
            >
                <li
                    v-for="(image, index) in images"
                    :key="image.id"
                    class="group relative aspect-square cursor-grab overflow-hidden rounded-xl border border-line bg-surface-muted active:cursor-grabbing"
                >
                    <img :src="image.thumb" alt="" class="size-full object-contain" loading="lazy" />
                    <span
                        v-if="index === 0"
                        class="absolute start-2 top-2 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-on-primary"
                    >
                        {{ t('admin.products.images.main') }}
                    </span>
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
                            <AppIcon name="trash" :size="16" />
                        </button>
                    </div>
                </li>
            </VueDraggable>

            <div class="flex items-center gap-3">
                <input
                    ref="input"
                    type="file"
                    multiple
                    :accept="ACCEPTED.join(',')"
                    class="sr-only"
                    :aria-label="t('admin.products.images.add')"
                    @change="upload"
                />
                <AppButton variant="secondary" size="md" :loading="uploading > 0" @click="input?.click()">
                    <AppIcon name="upload" :size="18" />
                    {{ t('admin.products.images.add') }}
                </AppButton>
                <AppSpinner v-if="uploading > 0" />
                <span class="text-[13px] text-fg-muted">{{
                    t('admin.products.images.limits', { size: formatNumber(MAX_MEGABYTES) })
                }}</span>
            </div>
        </template>
    </div>
</template>
