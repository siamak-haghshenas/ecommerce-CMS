<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';

/** One promotion row's start and end (`datetime-local` values), both required. */
const props = defineProps<{ open: boolean; name: string; startsAt: string; endsAt: string }>();
const emit = defineEmits<{ close: []; apply: [startsAt: string, endsAt: string] }>();

const { t } = useI18n();

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

const invalid = computed(() => start.value === '' || end.value === '' || end.value <= start.value);
const dateClass =
    'h-13 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.promotions.dates.title', { name })"
        icon="clock"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="promotion-dates-start" class="mb-2 block text-sm font-semibold">{{
                        t('admin.discounts.promotions.columns.startsAt')
                    }}</label>
                    <input id="promotion-dates-start" v-model="start" type="datetime-local" :class="dateClass" />
                </div>
                <div>
                    <label for="promotion-dates-end" class="mb-2 block text-sm font-semibold">{{
                        t('admin.discounts.promotions.columns.endsAt')
                    }}</label>
                    <input id="promotion-dates-end" v-model="end" type="datetime-local" :class="dateClass" />
                </div>
            </div>
            <p v-if="start !== '' && end !== '' && invalid" class="text-[13px] text-danger">
                {{ t('admin.discounts.promotions.problems.dates_order') }}
            </p>
        </div>
        <template #footer>
            <AppButton size="md" :disabled="invalid" @click="emit('apply', start, end)">{{
                t('admin.discounts.promotions.dates.apply')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
