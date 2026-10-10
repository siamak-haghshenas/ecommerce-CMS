<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import { fromLocalInput } from '../../products/variants';

/**
 * A narrower window for some variants, inside the event's dates. Empty fields (or "Use event
 * dates") follow the event's dates again.
 */
const props = defineProps<{
    open: boolean;
    count: number;
    /** `datetime-local` values. */
    startsAt: string;
    endsAt: string;
    eventStartsAt: string;
    eventEndsAt: string;
}>();
const emit = defineEmits<{ close: []; apply: [startsAt: string, endsAt: string] }>();

const { t } = useI18n();
const { formatNumber, formatDateTime } = useLocale();

const start = ref('');
const end = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) {
            start.value = props.startsAt;
            end.value = props.endsAt;
        }
    },
    { immediate: true },
);

const shown = (value: string): string => (value === '' ? '—' : formatDateTime(fromLocalInput(value) ?? ''));
const dateClass =
    'h-13 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.events.bulk.datesTitle', { n: formatNumber(count) })"
        icon="clock"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">
                {{
                    t('admin.discounts.events.bulk.datesIntro', {
                        start: shown(eventStartsAt),
                        end: shown(eventEndsAt),
                    })
                }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="item-dates-start" class="mb-2 block text-sm font-semibold">{{
                        t('admin.discounts.events.startsAt')
                    }}</label>
                    <input
                        id="item-dates-start"
                        v-model="start"
                        type="datetime-local"
                        :min="eventStartsAt || undefined"
                        :max="eventEndsAt || undefined"
                        :class="dateClass"
                    />
                </div>
                <div>
                    <label for="item-dates-end" class="mb-2 block text-sm font-semibold">{{
                        t('admin.discounts.events.endsAt')
                    }}</label>
                    <input
                        id="item-dates-end"
                        v-model="end"
                        type="datetime-local"
                        :min="eventStartsAt || undefined"
                        :max="eventEndsAt || undefined"
                        :class="dateClass"
                    />
                </div>
            </div>
            <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.discounts.events.bulk.datesHint') }}</p>
        </div>
        <template #footer>
            <AppButton size="md" @click="emit('apply', start, end)">{{
                t('admin.discounts.events.bulk.apply')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('apply', '', '')">{{
                t('admin.discounts.events.bulk.useEventDates')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
