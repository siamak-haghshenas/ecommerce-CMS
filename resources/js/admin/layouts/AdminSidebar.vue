<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router';
import AppAvatar from '@/shared/ui/AppAvatar.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import BrandMark from '@/shared/ui/BrandMark.vue';
import BylineDialog from '../modules/account/components/BylineDialog.vue';
import { useAdminAuthStore } from '../modules/auth/store';
import { useCommentsStore } from '../modules/comments/store';
import { type NavItem, navigation } from '../navigation';
import { Permission, ROLE_KEYS } from '../permissions';

/**
 * collapsed: an icon-only rail (desktop). Labels stay available to screen readers
 * and as tooltips; group titles become dividers.
 */
withDefaults(defineProps<{ inDrawer?: boolean; collapsed?: boolean }>(), { inDrawer: false, collapsed: false });
const emit = defineEmits<{ navigate: [] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();
const router = useRouter();
const route = useRoute();
const auth = useAdminAuthStore();
const loggingOut = ref(false);
const bylineOpen = ref(false);
const comments = useCommentsStore();

const moderates = computed(() => auth.can(Permission.ModerateComments));

// The pending badges follow new comments: checked in the background and on every page change.
onMounted(() => {
    if (moderates.value) {
        comments.watch();
    }
});

watch(
    () => route.fullPath,
    () => {
        if (moderates.value) {
            void comments.refresh();
        }
    },
);

/** The live count of an item with a badge; nothing while zero or unknown. */
function badgeOf(item: NavItem): number | null {
    const count =
        item.badge === 'pendingPostComments'
            ? comments.pending?.post
            : item.badge === 'pendingProductComments'
              ? comments.pending?.product
              : undefined;

    return count ? count : null;
}

/** A section is active on its own page and its sub-pages (a customer's detail is "Customers"); the dashboard only on itself. */
function isActive(to: RouteLocationRaw): boolean {
    const target = router.resolve(to);

    return target.path === '/' ? route.path === '/' : route.path.startsWith(target.path);
}

/** Built items the user may not open are hidden; unbuilt ("soon") items always show. */
const groups = computed(() =>
    navigation
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.to || !item.permission || auth.canAny(item.permission)),
        }))
        .filter((group) => group.items.length > 0),
);

const roleLabel = computed(() => {
    const role = auth.user?.roles[0];

    return role ? t(`admin.roleNames.${ROLE_KEYS[role]}`) : '';
});

async function logout(): Promise<void> {
    loggingOut.value = true;

    try {
        await auth.logout();
        await router.replace({ name: 'login' });
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden">
        <!-- In the drawer, the end padding leaves room for its close button. -->
        <div
            :class="[
                'flex h-16 shrink-0 items-center border-b border-line',
                collapsed ? 'justify-center' : ['ps-5', inDrawer ? 'pe-14' : 'pe-5'],
            ]"
        >
            <BrandMark :icon-only="collapsed" :subtitle="t('admin.badge')" />
        </div>

        <nav :aria-label="t('admin.nav.label')" class="grow overflow-x-hidden overflow-y-auto px-3 py-4">
            <div v-for="(group, index) in groups" :key="group.label" class="mb-4 last:mb-0">
                <p v-if="!collapsed" class="mb-1 truncate px-3 text-xs font-semibold whitespace-nowrap text-fg-muted">
                    {{ t(group.label) }}
                </p>
                <div v-else-if="index > 0" aria-hidden="true" class="mx-2 mb-3 h-px bg-line" />
                <ul class="flex flex-col gap-0.5">
                    <li v-for="item in group.items" :key="item.label">
                        <RouterLink v-if="item.to" v-slot="{ href, navigate }" :to="item.to" custom>
                            <a
                                :href="href"
                                :title="collapsed ? t(item.label) : undefined"
                                :aria-current="isActive(item.to) ? 'page' : undefined"
                                :class="[
                                    'flex h-11 items-center gap-3 rounded-[10px] text-[15px] whitespace-nowrap transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                    collapsed ? 'justify-center' : 'px-3',
                                    isActive(item.to)
                                        ? 'bg-primary-soft font-semibold text-fg'
                                        : 'text-fg-muted hover:bg-surface-muted hover:text-fg',
                                ]"
                                @click="
                                    navigate($event);
                                    emit('navigate');
                                "
                            >
                                <AppIcon :name="item.icon" :class="isActive(item.to) ? 'text-primary' : ''" />
                                <span :class="collapsed ? 'sr-only' : 'grow truncate'">{{ t(item.label) }}</span>
                                <span
                                    v-if="badgeOf(item) !== null"
                                    :class="[
                                        'rounded-full bg-warning-soft px-2 py-0.5 text-[11px] font-bold text-warning',
                                        collapsed && 'sr-only',
                                    ]"
                                    >{{ t('admin.nav.badge', { n: formatNumber(badgeOf(item) ?? 0) }) }}</span
                                >
                            </a>
                        </RouterLink>
                        <span
                            v-else
                            aria-disabled="true"
                            :title="collapsed ? `${t(item.label)} (${t('admin.nav.soon')})` : undefined"
                            :class="[
                                'flex h-11 cursor-default items-center gap-3 rounded-[10px] text-[15px] whitespace-nowrap text-fg-muted/70',
                                collapsed ? 'justify-center opacity-60' : 'px-3',
                            ]"
                        >
                            <AppIcon :name="item.icon" />
                            <template v-if="!collapsed">
                                <span class="grow truncate">{{ t(item.label) }}</span>
                                <span
                                    class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-fg-muted"
                                >
                                    {{ t('admin.nav.soon') }}
                                </span>
                            </template>
                            <span v-else class="sr-only">{{ t(item.label) }} ({{ t('admin.nav.soon') }})</span>
                        </span>
                    </li>
                </ul>
            </div>
        </nav>

        <div
            v-if="auth.user"
            :class="[
                'flex shrink-0 border-t border-line',
                collapsed ? 'flex-col items-center gap-2 py-3' : 'items-center gap-3 p-4',
            ]"
        >
            <span :title="collapsed ? `${auth.user.name} · ${roleLabel}` : undefined">
                <AppAvatar :name="auth.user.name" />
            </span>
            <div v-if="!collapsed" class="min-w-0 grow">
                <p class="truncate text-sm font-semibold text-fg">{{ auth.user.name }}</p>
                <p class="truncate text-xs text-fg-muted">{{ roleLabel }}</p>
            </div>
            <button
                type="button"
                :aria-label="t('admin.byline.open')"
                :title="t('admin.byline.open')"
                class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-[10px] text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                @click="bylineOpen = true"
            >
                <AppIcon name="pen" />
            </button>
            <button
                type="button"
                :aria-label="t('admin.logout')"
                :title="collapsed ? t('admin.logout') : undefined"
                :disabled="loggingOut"
                class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-[10px] text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                @click="logout"
            >
                <AppSpinner v-if="loggingOut" />
                <AppIcon v-else name="logout" flip-in-rtl />
            </button>
        </div>
        <BylineDialog :open="bylineOpen" @close="bylineOpen = false" />
    </div>
</template>
