<script setup lang="ts">
import { computed } from 'vue';
import { Translation, useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppIcon, { type IconName } from '@/shared/ui/AppIcon.vue';
import { ROLE_KEYS, type StaffRole } from '../../../permissions';
import type { Activity, ActivityEvent } from '../types';

const props = withDefaults(defineProps<{ activity: Activity; compact?: boolean }>(), { compact: false });

const { t } = useI18n();
const { formatDateTime, formatRelativeTime, isRtl } = useLocale();

const ICONS: Record<ActivityEvent, { icon: IconName; tone: 'primary' | 'danger' | 'neutral' }> = {
    'customer.updated': { icon: 'pen', tone: 'neutral' },
    'customer.activated': { icon: 'check', tone: 'primary' },
    'customer.deactivated': { icon: 'ban', tone: 'danger' },
    'customer.anonymised': { icon: 'eraser', tone: 'danger' },
    'staff.added': { icon: 'userPlus', tone: 'primary' },
    'staff.role_changed': { icon: 'swap', tone: 'primary' },
    'staff.activated': { icon: 'check', tone: 'primary' },
    'staff.deactivated': { icon: 'ban', tone: 'danger' },
    'staff.removed': { icon: 'userX', tone: 'danger' },
};

/** 'staff.role_changed' → 'staffRoleChanged' (the i18n key). */
const eventKey = computed(() => props.activity.event.replace(/[._](\w)/g, (_, letter: string) => letter.toUpperCase()));
const look = computed(() => ICONS[props.activity.event] ?? { icon: 'history' as const, tone: 'neutral' as const });
const isCustomerEvent = computed(() => props.activity.event.startsWith('customer.'));

const roleName = (role: unknown): string =>
    typeof role === 'string' && role in ROLE_KEYS
        ? t(`admin.roleNames.${ROLE_KEYS[role as StaffRole]}`)
        : String(role ?? '');

/** Extra detail from the (personal-data-free) properties. */
const detail = computed<string | null>(() => {
    const properties = props.activity.properties;

    if (props.activity.event === 'customer.updated' && Array.isArray(properties.fields)) {
        const fields = properties.fields.map((field) => t(`admin.activity.fieldNames.${String(field)}`)).join('، ');

        return t('admin.activity.fields', { fields });
    }

    if (props.activity.event === 'staff.role_changed') {
        // The arrow points in the reading direction: from the old role to the new one.
        return `${roleName(properties.from)} ${isRtl.value ? '←' : '→'} ${roleName(properties.to)}`;
    }

    if (props.activity.event === 'staff.added') {
        const role = t('admin.activity.roleAdded', { role: roleName(properties.role) });

        return properties.promoted_customer === true ? `${role} · ${t('admin.activity.promoted')}` : role;
    }

    return null;
});
</script>

<template>
    <div :class="['flex items-start gap-3.5', compact ? 'py-2.5' : 'px-5 py-3.5']">
        <span
            :class="[
                'flex size-9 shrink-0 items-center justify-center rounded-full',
                {
                    'bg-primary-soft text-primary': look.tone === 'primary',
                    'bg-danger-soft text-danger': look.tone === 'danger',
                    'bg-surface-muted text-fg-muted': look.tone === 'neutral',
                },
            ]"
        >
            <AppIcon :name="look.icon" :size="18" />
        </span>
        <div class="flex min-w-0 grow flex-col gap-1.5">
            <Translation
                :keypath="`admin.activity.events.${eventKey}`"
                scope="global"
                tag="p"
                class="text-sm leading-relaxed text-fg"
            >
                <template #causer>
                    <b>{{ activity.causer?.name ?? t('admin.activity.someone') }}</b>
                </template>
                <template #subject>
                    <RouterLink
                        v-if="activity.subject && isCustomerEvent && !activity.subject.is_anonymised"
                        :to="{ name: 'customer', params: { id: activity.subject.id } }"
                        class="font-semibold text-primary hover:text-primary-hover"
                    >
                        {{ activity.subject.name }}
                    </RouterLink>
                    <b v-else>{{ activity.subject?.name ?? t('admin.activity.someone') }}</b>
                </template>
            </Translation>
            <span v-if="detail"
                ><AppBadge>{{ detail }}</AppBadge></span
            >
        </div>
        <time
            v-if="activity.created_at"
            :datetime="activity.created_at"
            :title="formatDateTime(activity.created_at)"
            class="shrink-0 text-xs text-fg-muted"
        >
            {{ formatRelativeTime(activity.created_at) }}
        </time>
    </div>
</template>
