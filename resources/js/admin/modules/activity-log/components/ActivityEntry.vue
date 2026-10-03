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
    'category.created': { icon: 'plus', tone: 'primary' },
    'category.updated': { icon: 'pen', tone: 'neutral' },
    'category.moved': { icon: 'folderMove', tone: 'neutral' },
    'category.reordered': { icon: 'swap', tone: 'neutral' },
    'category.deleted': { icon: 'trash', tone: 'danger' },
    'brand.created': { icon: 'plus', tone: 'primary' },
    'brand.updated': { icon: 'pen', tone: 'neutral' },
    'brand.reordered': { icon: 'swap', tone: 'neutral' },
    'brand.deleted': { icon: 'trash', tone: 'danger' },
    'attribute.created': { icon: 'plus', tone: 'primary' },
    'attribute.updated': { icon: 'pen', tone: 'neutral' },
    'attribute.reordered': { icon: 'swap', tone: 'neutral' },
    'attribute.deleted': { icon: 'trash', tone: 'danger' },
    'option.created': { icon: 'plus', tone: 'primary' },
    'option.updated': { icon: 'pen', tone: 'neutral' },
    'option.reordered': { icon: 'swap', tone: 'neutral' },
    'option.deleted': { icon: 'trash', tone: 'danger' },
    'category.attributes_changed': { icon: 'sliders', tone: 'neutral' },
    'product.created': { icon: 'plus', tone: 'primary' },
    'product.updated': { icon: 'pen', tone: 'neutral' },
    'product.deleted': { icon: 'trash', tone: 'danger' },
    'product.restored': { icon: 'refresh', tone: 'primary' },
    'tag.renamed': { icon: 'pen', tone: 'neutral' },
    'tag.merged': { icon: 'swap', tone: 'neutral' },
    'tag.deleted': { icon: 'trash', tone: 'danger' },
    'settings.updated': { icon: 'settings', tone: 'neutral' },
};

/** 'staff.role_changed' → 'staffRoleChanged' (the i18n key). */
const eventKey = computed(() => props.activity.event.replace(/[._](\w)/g, (_, letter: string) => letter.toUpperCase()));
const look = computed(() => ICONS[props.activity.event] ?? { icon: 'history' as const, tone: 'neutral' as const });
/** Customers and products open their page from the log. */
const subjectLink = computed(() => {
    const subject = props.activity.subject;

    if (!subject || subject.is_anonymised) {
        return null;
    }

    if (props.activity.event.startsWith('customer.')) {
        return { name: 'customer', params: { id: subject.id } };
    }

    return subject.type === 'product' ? { name: 'product', params: { id: subject.id } } : null;
});

/** Shown when there is no subject: a deleted category keeps its name, a top-level reorder has no parent. */
const missingSubject = computed(() => {
    const { event, properties } = props.activity;

    if (event === 'category.reordered') {
        return t('admin.activity.topLevel');
    }

    if (/^(category|brand|attribute|option|product|tag)\./.test(event) && typeof properties.name === 'string') {
        return properties.name;
    }

    return t('admin.activity.someone');
});

const roleName = (role: unknown): string =>
    typeof role === 'string' && role in ROLE_KEYS
        ? t(`admin.roleNames.${ROLE_KEYS[role as StaffRole]}`)
        : String(role ?? '');

/** Extra detail from the (personal-data-free) properties. */
const detail = computed<string | null>(() => {
    const properties = props.activity.properties;

    if (props.activity.event.endsWith('.updated') && Array.isArray(properties.fields)) {
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
                        v-if="activity.subject && subjectLink"
                        :to="subjectLink"
                        class="font-semibold text-primary hover:text-primary-hover"
                    >
                        {{ activity.subject.name }}
                    </RouterLink>
                    <b v-else>{{ activity.subject?.name ?? missingSubject }}</b>
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
