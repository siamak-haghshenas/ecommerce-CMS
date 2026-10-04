<script setup lang="ts">
import { ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { pictureOf, type MediaPicture } from '../types';
import MediaPickerDialog from './MediaPickerDialog.vue';

/**
 * One picture chosen from the media library (a category image, a brand logo). The form saves its id.
 */
const props = defineProps<{ label: string; hint?: string; error?: string; modelValue: MediaPicture | null }>();
const emit = defineEmits<{ 'update:modelValue': [picture: MediaPicture | null] }>();

const { t } = useI18n();
const id = useId();
const picking = ref(false);
</script>

<template>
    <div class="flex flex-col gap-2" role="group" :aria-labelledby="`${id}-label`">
        <span :id="`${id}-label`" class="text-sm font-semibold text-fg">
            {{ label }} <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
        </span>
        <div class="flex items-center gap-4">
            <button
                type="button"
                :aria-label="props.modelValue ? t('admin.media.field.change') : t('admin.media.field.choose')"
                :class="[
                    'flex size-24 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-2xl border bg-surface-muted text-fg-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                    error
                        ? 'border-2 border-danger'
                        : props.modelValue
                          ? 'border-line'
                          : 'border-dashed border-line-strong hover:border-primary hover:text-primary',
                ]"
                @click="picking = true"
            >
                <img
                    v-if="props.modelValue?.thumb ?? props.modelValue?.original"
                    :src="props.modelValue?.thumb ?? props.modelValue?.original"
                    :alt="props.modelValue?.alt ?? ''"
                    class="size-full object-contain"
                />
                <AppIcon v-else name="image" :size="28" />
            </button>
            <div class="flex flex-col items-start gap-2">
                <AppButton variant="secondary" size="md" type="button" @click="picking = true">
                    <AppIcon name="image" :size="16" />
                    {{ props.modelValue ? t('admin.media.field.change') : t('admin.media.field.choose') }}
                </AppButton>
                <button
                    v-if="props.modelValue"
                    type="button"
                    class="rounded text-sm text-danger hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="emit('update:modelValue', null)"
                >
                    {{ t('admin.media.field.remove') }}
                </button>
            </div>
        </div>
        <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>
        <p v-else-if="hint" class="text-[13px] leading-relaxed text-fg-muted">{{ hint }}</p>

        <MediaPickerDialog
            :open="picking"
            :title="label"
            type="image"
            @close="picking = false"
            @choose="
                emit('update:modelValue', $event[0] ? pictureOf($event[0]) : null);
                picking = false;
            "
        />
    </div>
</template>
