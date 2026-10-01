<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppBadge, { type BadgeTone } from '@/shared/ui/AppBadge.vue';
import type { Customer } from '../types';

const props = defineProps<{ customer: Customer }>();

const { t } = useI18n();

const status = computed<{ tone: BadgeTone; label: string }>(() => {
    if (props.customer.is_anonymised) {
        return { tone: 'outline', label: t('admin.customers.statusAnonymised') };
    }

    if (!props.customer.registration_completed) {
        return { tone: 'warning', label: t('admin.customers.statusPending') };
    }

    return props.customer.is_active
        ? { tone: 'success', label: t('admin.customers.statusActive') }
        : { tone: 'danger', label: t('admin.customers.statusInactive') };
});
</script>

<template>
    <AppBadge :tone="status.tone">{{ status.label }}</AppBadge>
</template>
