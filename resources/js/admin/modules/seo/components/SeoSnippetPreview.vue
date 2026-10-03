<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppIcon from '@/shared/ui/AppIcon.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';

/**
 * Roughly how a search engine shows the category. Long titles and descriptions are cut off,
 * like in real results. The storefront's domain is unknown here, so a placeholder is shown.
 */
const props = defineProps<{ title: string; description: string; path: string[]; slug: string }>();

const { t } = useI18n();
const device = ref<'desktop' | 'mobile'>('desktop');

const devices = computed(() => [
    { value: 'desktop' as const, label: t('admin.seo.desktop') },
    { value: 'mobile' as const, label: t('admin.seo.mobile') },
]);
const breadcrumb = computed(() => [t('admin.seo.domain'), ...props.path, props.slug || '…'].join(' › '));
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-semibold">{{ t('admin.seo.preview') }}</span>
            <div class="w-48">
                <SegmentedControl v-model="device" :label="t('admin.seo.preview')" :options="devices" />
            </div>
        </div>
        <div
            :class="[
                'flex flex-col gap-1 rounded-xl border border-line bg-surface p-4',
                device === 'mobile' ? 'max-w-[360px]' : 'max-w-[600px]',
            ]"
            :aria-label="t('admin.seo.previewLabel')"
            role="group"
        >
            <div class="flex items-center gap-2">
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-fg-muted"
                    aria-hidden="true"
                >
                    <AppIcon name="globe" :size="14" />
                </span>
                <span class="truncate text-xs text-fg-muted" dir="ltr">{{ breadcrumb }}</span>
            </div>
            <p
                dir="auto"
                :class="[
                    'text-lg leading-snug text-primary',
                    device === 'mobile' ? 'line-clamp-2' : 'line-clamp-1',
                    !title && 'text-fg-muted italic',
                ]"
            >
                {{ title || t('admin.seo.previewNoTitle') }}
            </p>
            <p
                dir="auto"
                :class="[
                    'text-sm leading-relaxed',
                    device === 'mobile' ? 'line-clamp-3' : 'line-clamp-2',
                    description ? 'text-fg' : 'text-fg-muted italic',
                ]"
            >
                {{ description || t('admin.seo.previewNoDescription') }}
            </p>
        </div>
    </div>
</template>
