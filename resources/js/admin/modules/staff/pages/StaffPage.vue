<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppAvatar from '@/shared/ui/AppAvatar.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { Permission, ROLE_KEYS, STAFF_ROLES, type StaffRole } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { staffApi } from '../api';
import AddStaffDialog from '../components/AddStaffDialog.vue';
import ChangeRoleDialog from '../components/ChangeRoleDialog.vue';
import ConfirmStaffDialog from '../components/ConfirmStaffDialog.vue';
import type { StaffMember } from '../types';

const { t } = useI18n();
const { localizeDigits, formatRelativeTime, formatNumber } = useLocale();
const toast = useToast();
const auth = useAdminAuthStore();

const canManage = computed(() => auth.can(Permission.ManageStaff));

const search = ref('');
const role = ref<StaffRole | 'all'>('all');
const status = ref<'all' | 'active' | 'inactive'>('all');
const page = ref(1);

const result = ref<Paginated<StaffMember> | null>(null);
/** Unfiltered, for the per-role counts on the summary cards (staff lists are small). */
const everyone = ref<StaffMember[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

const roleOptions = computed(() => [
    { value: 'all' as const, label: t('admin.staff.all') },
    ...STAFF_ROLES.map((value) => ({ value, label: t(`admin.roleNames.${ROLE_KEYS[value]}`) })),
]);
const statusOptions = computed(() => [
    { value: 'all' as const, label: t('admin.staff.all') },
    { value: 'active' as const, label: t('admin.staff.active') },
    { value: 'inactive' as const, label: t('admin.staff.inactive') },
]);

const roleCounts = computed(() =>
    Object.fromEntries(
        STAFF_ROLES.map((value) => [value, everyone.value.filter((member) => member.role === value).length]),
    ),
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        [result.value, everyone.value] = await Promise.all([
            staffApi.list({
                q: search.value || undefined,
                role: role.value === 'all' ? undefined : role.value,
                status: status.value === 'all' ? undefined : status.value,
                page: page.value,
            }),
            staffApi.list({ per_page: 100 }).then((all) => all.data),
        ]);
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch([search, role, status], () => {
    page.value = 1;
});
watch([search, role, status, page], load, { immediate: true });

// ---- actions ----

const adding = ref(false);
const roleTarget = ref<StaffMember | null>(null);
const confirmAction = ref<'deactivate' | 'remove' | null>(null);
const confirmTarget = ref<StaffMember | null>(null);

function isSelf(member: StaffMember): boolean {
    return member.id === auth.user?.id;
}

function menuItems(member: StaffMember): ActionMenuItem[] {
    return [
        { key: 'role', label: t('admin.staff.changeRole'), icon: 'swap' },
        member.is_active
            ? { key: 'deactivate', label: t('admin.staff.deactivate'), icon: 'ban' }
            : { key: 'activate', label: t('admin.staff.activate'), icon: 'check' },
        { key: 'remove', label: t('admin.staff.remove'), icon: 'userX', danger: true, separated: true },
    ];
}

async function activate(member: StaffMember): Promise<void> {
    try {
        const updated = await staffApi.setActive(member.id, true);
        toast.show(t('admin.staff.activated', { name: updated.name }));
        void load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

function onMenu(key: string, member: StaffMember): void {
    if (key === 'role') {
        roleTarget.value = member;
    } else if (key === 'activate') {
        void activate(member);
    } else if (key === 'deactivate' || key === 'remove') {
        confirmTarget.value = member;
        confirmAction.value = key;
    }
}

function onAdded(member: StaffMember): void {
    adding.value = false;
    toast.show(t('admin.staff.added', { name: member.name }));
    void load();
}

function onRoleChanged(member: StaffMember): void {
    roleTarget.value = null;
    toast.show(t('admin.staff.roleChanged', { name: member.name }));
    void load();
}

function onConfirmed(member: StaffMember): void {
    toast.show(
        t(confirmAction.value === 'remove' ? 'admin.staff.removed' : 'admin.staff.deactivated', { name: member.name }),
    );
    confirmAction.value = null;
    void load();
}
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.staff.title') }}</h1>
                <p class="text-sm text-fg-muted">{{ t('admin.staff.count', { n: formatNumber(everyone.length) }) }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="adding = true">
                <AppIcon name="userPlus" :size="18" />
                {{ t('admin.staff.add') }}
            </AppButton>
        </div>

        <p
            v-if="!canManage"
            role="note"
            class="flex items-center gap-2.5 rounded-xl bg-surface-muted px-4 py-3 text-sm text-fg-muted"
        >
            <AppIcon name="info" :size="18" />
            {{ t('admin.staff.readOnly') }}
        </p>

        <div class="grid gap-4 md:grid-cols-3">
            <article
                v-for="value in STAFF_ROLES"
                :key="value"
                class="flex flex-col gap-2 rounded-2xl border border-line bg-surface p-4"
            >
                <div class="flex items-center justify-between">
                    <h2 class="text-[15px] font-bold">{{ t(`admin.roleNames.${ROLE_KEYS[value]}`) }}</h2>
                    <span class="text-[13px] text-fg-muted">{{
                        t('admin.staff.members', { n: formatNumber(roleCounts[value] ?? 0) })
                    }}</span>
                </div>
                <p class="text-[13px] leading-relaxed text-fg-muted">
                    {{ t(`admin.roleDescriptions.${ROLE_KEYS[value]}`) }}
                </p>
            </article>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.staff.search')" />
            <SelectField v-model="role" :label="t('admin.staff.role')" :options="roleOptions" />
            <SegmentedControl v-model="status" :label="t('admin.staff.status')" :options="statusOptions" />
        </div>

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <section
            v-else
            :aria-busy="loading"
            :class="['rounded-2xl border border-line bg-surface transition-opacity', loading && result && 'opacity-60']"
        >
            <div v-if="!result" aria-hidden="true" class="flex flex-col gap-px overflow-hidden rounded-2xl">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-16 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState v-else-if="result.data.length === 0" icon="search" :title="t('admin.staff.empty')" />

            <template v-else>
                <table class="hidden w-full md:table">
                    <thead class="bg-bg text-xs font-semibold text-fg-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.staff.colMember') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.staff.colPhone') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.staff.colRole') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.staff.colStatus') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.staff.colLastLogin') }}</th>
                            <th v-if="canManage" scope="col" class="w-14 px-3 py-3">
                                <span class="sr-only">{{ t('admin.staff.colActions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="member in result.data" :key="member.id">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <AppAvatar :name="member.name" size="sm" />
                                    <span class="text-sm font-semibold">
                                        {{ member.name }}
                                        <span v-if="isSelf(member)" class="font-normal text-fg-muted">{{
                                            t('admin.you')
                                        }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span dir="ltr">{{ localizeDigits(member.phone_number) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <AppBadge :tone="member.role === 'super-admin' ? 'success' : 'neutral'">
                                    {{ t(`admin.roleNames.${ROLE_KEYS[member.role]}`) }}
                                </AppBadge>
                            </td>
                            <td class="px-4 py-3">
                                <AppBadge :tone="member.is_active ? 'success' : 'danger'">
                                    {{ member.is_active ? t('admin.staff.active') : t('admin.staff.inactive') }}
                                </AppBadge>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap text-fg-muted">
                                {{ member.last_login_at ? formatRelativeTime(member.last_login_at) : '—' }}
                            </td>
                            <td v-if="canManage" class="px-3 py-2">
                                <span
                                    v-if="isSelf(member)"
                                    class="block w-10 text-center text-fg-muted"
                                    :title="t('admin.staff.cannotChangeSelf')"
                                    >—</span
                                >
                                <ActionMenu
                                    v-else
                                    :label="t('admin.staff.actionsFor', { name: member.name })"
                                    :items="menuItems(member)"
                                    @select="onMenu($event, member)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul class="divide-y divide-line md:hidden">
                    <li v-for="member in result.data" :key="member.id" class="flex items-center gap-3 px-4 py-3.5">
                        <AppAvatar :name="member.name" />
                        <span class="min-w-0 grow">
                            <span class="block truncate font-semibold">
                                {{ member.name }}
                                <span v-if="isSelf(member)" class="font-normal text-fg-muted">{{
                                    t('admin.you')
                                }}</span>
                            </span>
                            <span class="mt-1 flex flex-wrap gap-1.5">
                                <AppBadge :tone="member.role === 'super-admin' ? 'success' : 'neutral'">
                                    {{ t(`admin.roleNames.${ROLE_KEYS[member.role]}`) }}
                                </AppBadge>
                                <AppBadge v-if="!member.is_active" tone="danger">{{
                                    t('admin.staff.inactive')
                                }}</AppBadge>
                            </span>
                        </span>
                        <ActionMenu
                            v-if="canManage && !isSelf(member)"
                            :label="t('admin.staff.actionsFor', { name: member.name })"
                            :items="menuItems(member)"
                            @select="onMenu($event, member)"
                        />
                    </li>
                </ul>

                <AppPagination :meta="result.meta" @change="page = $event" />
            </template>
        </section>

        <template v-if="canManage">
            <AddStaffDialog :open="adding" @close="adding = false" @added="onAdded" />
            <ChangeRoleDialog
                :open="roleTarget !== null"
                :member="roleTarget"
                @close="roleTarget = null"
                @done="onRoleChanged"
            />
            <ConfirmStaffDialog
                :action="confirmAction"
                :member="confirmTarget"
                @close="confirmAction = null"
                @done="onConfirmed"
            />
        </template>
    </div>
</template>
