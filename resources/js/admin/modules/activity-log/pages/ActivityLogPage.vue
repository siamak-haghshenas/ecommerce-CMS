<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { activityLogApi } from '../api';
import ActivityEntry from '../components/ActivityEntry.vue';
import type { Activity, ActivityEvent } from '../types';

const EVENTS: ActivityEvent[] = [
    'customer.updated',
    'customer.activated',
    'customer.deactivated',
    'customer.anonymised',
    'staff.added',
    'staff.role_changed',
    'staff.activated',
    'staff.deactivated',
    'staff.removed',
    'category.created',
    'category.updated',
    'category.moved',
    'category.reordered',
    'category.deleted',
    'brand.created',
    'brand.updated',
    'brand.reordered',
    'brand.deleted',
    'attribute.created',
    'attribute.updated',
    'attribute.reordered',
    'attribute.deleted',
    'option.created',
    'option.updated',
    'option.reordered',
    'option.deleted',
    'category.attributes_changed',
    'product.created',
    'product.updated',
    'product.deleted',
    'product.restored',
    'page.created',
    'page.updated',
    'page.deleted',
    'page.restored',
    'menu.updated',
    'post.created',
    'post.updated',
    'post.deleted',
    'post.restored',
    'promotion.created',
    'promotion.updated',
    'promotion.deleted',
    'sale_event.created',
    'sale_event.updated',
    'sale_event.deleted',
    'sale_event.reordered',
    'coupon.created',
    'coupon.updated',
    'coupon.deleted',
    'tag.renamed',
    'tag.merged',
    'tag.deleted',
    'settings.updated',
    'media.uploaded',
    'media.updated',
    'media.moved',
    'media.deleted',
    'media.restored',
    'media_folder.created',
    'media_folder.renamed',
    'media_folder.deleted',
];

const { t } = useI18n();

const event = ref<ActivityEvent | 'all'>('all');
const page = ref(1);
const result = ref<Paginated<Activity> | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const eventOptions = computed(() => [
    { value: 'all' as const, label: t('admin.activity.all') },
    ...EVENTS.map((value) => ({
        value,
        label: t(
            `admin.activity.eventNames.${value.replace(/[._](\w)/g, (_, letter: string) => letter.toUpperCase())}`,
        ),
    })),
]);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        result.value = await activityLogApi.list({
            event: event.value === 'all' ? undefined : event.value,
            page: page.value,
        });
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(event, () => (page.value = 1));
watch([event, page], load, { immediate: true });
</script>

<template>
    <div class="mx-auto flex max-w-5xl flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold">{{ t('admin.activity.title') }}</h1>
            <p class="text-sm text-fg-muted">{{ t('admin.activity.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SelectField v-model="event" :label="t('admin.activity.event')" :options="eventOptions" />
        </div>

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <section v-else :aria-busy="loading" class="overflow-hidden rounded-2xl border border-line bg-surface">
            <div v-if="!result" aria-hidden="true" class="flex flex-col gap-px">
                <div
                    v-for="row in 6"
                    :key="row"
                    class="h-16 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>
            <EmptyState v-else-if="result.data.length === 0" icon="history" :title="t('admin.activity.empty')" />
            <template v-else>
                <ul class="divide-y divide-line">
                    <li v-for="activity in result.data" :key="activity.id"><ActivityEntry :activity="activity" /></li>
                </ul>
                <AppPagination :meta="result.meta" @change="page = $event" />
            </template>
        </section>

        <p class="flex items-center gap-2 text-[13px] text-fg-muted">
            <AppIcon name="lock" :size="16" />
            {{ t('admin.activity.privacy') }}
        </p>
    </div>
</template>
