<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '../composables/useLocale';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';

/** Same limits as the API image uploads (category images, brand logos). */
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_MEGABYTES = 5;

/**
 * Picks one image (JPG, PNG or WebP). Nothing is uploaded here: the form uploads the chosen
 * file (or removes the current image) after saving the item.
 */
const props = defineProps<{
    label: string;
    currentUrl: string | null;
    error?: string;
    /** Replaces the default format and size hint. */
    hint?: string;
    /** contain: show the whole image (logos); cover: fill the preview (photos). */
    fit?: 'cover' | 'contain';
}>();
const file = defineModel<File | null>('file', { required: true });
const removed = defineModel<boolean>('removed', { required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();
const id = useId();
const input = useTemplateRef('input');
const localError = ref<string | null>(null);
const previewUrl = ref<string | null>(null);

watch(file, (selected) => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = selected ? URL.createObjectURL(selected) : null;
});

onBeforeUnmount(() => previewUrl.value && URL.revokeObjectURL(previewUrl.value));

const shownUrl = computed(() => previewUrl.value ?? (removed.value ? null : props.currentUrl));
const message = computed(() => localError.value ?? props.error);

function onPick(event: Event): void {
    const picked = (event.target as HTMLInputElement).files?.[0] ?? null;
    localError.value = null;

    if (!picked) {
        return;
    }

    if (!ACCEPTED_TYPES.includes(picked.type)) {
        localError.value = t('common.imageType');
    } else if (picked.size > MAX_MEGABYTES * 1024 * 1024) {
        localError.value = t('common.imageTooLarge', { size: formatNumber(MAX_MEGABYTES) });
    } else {
        file.value = picked;
        removed.value = false;
    }

    // Allow picking the same file again after removing it.
    (event.target as HTMLInputElement).value = '';
}

function remove(): void {
    file.value = null;
    removed.value = props.currentUrl !== null;
    localError.value = null;
}
</script>

<template>
    <div>
        <span :id="`${id}-label`" class="mb-2 block text-sm font-semibold text-fg">
            {{ label }} <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
        </span>
        <div class="flex items-center gap-4">
            <span
                class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-surface-muted text-fg-muted"
            >
                <img
                    v-if="shownUrl"
                    :src="shownUrl"
                    alt=""
                    :class="['size-full', fit === 'contain' ? 'object-contain p-1.5' : 'object-cover']"
                />
                <AppIcon v-else name="image" :size="26" />
            </span>
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap gap-2">
                    <input
                        :id="id"
                        ref="input"
                        type="file"
                        :accept="ACCEPTED_TYPES.join(',')"
                        class="sr-only"
                        :aria-labelledby="`${id}-label`"
                        @change="onPick"
                    />
                    <AppButton variant="secondary" size="md" @click="input?.click()">
                        <AppIcon name="upload" :size="18" />
                        {{ shownUrl ? t('common.imageReplace') : t('common.imageChoose') }}
                    </AppButton>
                    <AppButton v-if="shownUrl" variant="danger-outline" size="md" @click="remove">
                        {{ t('common.imageRemove') }}
                    </AppButton>
                </div>
                <p class="text-[13px] text-fg-muted">
                    {{ hint ?? t('common.imageHint', { size: formatNumber(MAX_MEGABYTES) }) }}
                </p>
            </div>
        </div>
        <p v-if="message" role="alert" class="mt-2 flex items-start gap-1.5 text-[13px] leading-relaxed text-danger">
            <AppIcon name="alert" :size="16" class="mt-0.5" />
            <span>{{ message }}</span>
        </p>
    </div>
</template>
