<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { byteSize } from '../files';
import { useMediaUploadStore } from '../store';

/** The upload queue, floating at the bottom of the page while there are uploads. */
const uploads = useMediaUploadStore();
const { t } = useI18n();
const { formatNumber } = useLocale();
const collapsed = ref(false);

const failed = computed(() => uploads.items.filter((item) => item.status === 'error').length);
const title = computed(() =>
    uploads.active.length
        ? t('admin.media.uploads.uploading', { n: formatNumber(uploads.active.length) })
        : failed.value
          ? t('admin.media.uploads.failed', { n: formatNumber(failed.value) })
          : t('admin.media.uploads.done'),
);

function size(bytes: number): string {
    const { value, unit } = byteSize(bytes);

    return `${formatNumber(value)} ${t(`admin.media.units.${unit}`)}`;
}

const percent = (sent: number, total: number): number => (total === 0 ? 100 : Math.round((sent / total) * 100));
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
        enter-from-class="translate-y-4 opacity-0"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-to-class="translate-y-4 opacity-0"
    >
        <section
            v-if="uploads.items.length"
            :aria-label="t('admin.media.uploads.title')"
            class="fixed end-4 bottom-4 z-40 w-[calc(100%-2rem)] overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_16px_40px_rgb(0_0_0/0.18)] sm:w-96"
        >
            <header class="flex items-center gap-2 border-b border-line px-4 py-3">
                <span
                    v-if="uploads.active.length"
                    class="size-4 animate-spin rounded-full border-2 border-primary/30 border-t-primary motion-reduce:animate-none"
                    aria-hidden="true"
                />
                <AppIcon v-else-if="failed" name="alert" :size="18" class="text-danger" />
                <AppIcon v-else name="check" :size="18" class="text-success" />
                <h2 class="grow text-sm font-semibold" aria-live="polite">{{ title }}</h2>
                <button
                    type="button"
                    :aria-expanded="!collapsed"
                    :aria-label="collapsed ? t('admin.media.uploads.expand') : t('admin.media.uploads.collapse')"
                    class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg"
                    @click="collapsed = !collapsed"
                >
                    <AppIcon name="chevronDown" :size="16" :class="collapsed && 'rotate-180'" />
                </button>
                <button
                    v-if="!uploads.active.length"
                    type="button"
                    :aria-label="t('admin.media.uploads.clear')"
                    class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg"
                    @click="uploads.clearFinished()"
                >
                    <AppIcon name="close" :size="16" />
                </button>
            </header>
            <div
                v-if="uploads.active.length"
                class="h-1 bg-surface-muted"
                role="progressbar"
                :aria-valuenow="Math.round(uploads.progress * 100)"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t('admin.media.uploads.title')"
            >
                <div class="h-full bg-primary transition-[width]" :style="{ width: `${uploads.progress * 100}%` }" />
            </div>

            <ul v-show="!collapsed" class="max-h-72 divide-y divide-line overflow-y-auto">
                <li v-for="item in uploads.items" :key="item.key" class="flex items-center gap-3 px-4 py-2.5">
                    <span
                        :class="[
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            item.status === 'error'
                                ? 'bg-danger-soft text-danger'
                                : item.status === 'done'
                                  ? 'bg-success-soft text-success'
                                  : 'bg-surface-muted text-fg-muted',
                        ]"
                    >
                        <AppIcon
                            :name="item.status === 'error' ? 'alert' : item.status === 'done' ? 'check' : 'upload'"
                            :size="16"
                        />
                    </span>
                    <span class="flex min-w-0 grow flex-col gap-1">
                        <span class="truncate text-sm font-medium" :title="item.name" dir="auto">{{ item.name }}</span>
                        <span v-if="item.status === 'error'" class="text-xs text-danger">{{ item.error }}</span>
                        <span v-else-if="item.status === 'done'" class="text-xs text-fg-muted">{{
                            size(item.size)
                        }}</span>
                        <span v-else class="flex items-center gap-2">
                            <span class="h-1.5 grow overflow-hidden rounded-full bg-surface-muted">
                                <span
                                    class="block h-full rounded-full bg-primary transition-[width]"
                                    :style="{ width: `${percent(item.sent, item.size)}%` }"
                                />
                            </span>
                            <span class="w-9 text-end text-xs text-fg-muted tabular-nums">{{
                                t('admin.media.uploads.percent', { n: formatNumber(percent(item.sent, item.size)) })
                            }}</span>
                        </span>
                    </span>
                    <button
                        v-if="item.status === 'error'"
                        type="button"
                        :aria-label="t('admin.media.uploads.retry', { name: item.name })"
                        :title="t('common.retry')"
                        class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg"
                        @click="uploads.retry(item.key)"
                    >
                        <AppIcon name="refresh" :size="16" />
                    </button>
                    <button
                        v-if="item.status !== 'done'"
                        type="button"
                        :aria-label="t('admin.media.uploads.cancel', { name: item.name })"
                        :title="t('common.cancel')"
                        class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-danger-soft hover:text-danger"
                        @click="uploads.cancel(item.key)"
                    >
                        <AppIcon name="close" :size="16" />
                    </button>
                </li>
            </ul>
        </section>
    </Transition>
</template>
