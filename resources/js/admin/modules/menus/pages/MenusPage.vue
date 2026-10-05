<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { menusApi } from '../api';
import MenuItemDialog from '../components/MenuItemDialog.vue';
import MenuItemRow from '../components/MenuItemRow.vue';
import { indented, moved, outdented, rowFromItem, toPayload, type MenuRow } from '../tree';
import type { MenuLocation, MenuTargets } from '../types';

/** The header and footer menus: a two-level tree, edited in place and saved with one Save. */
const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageMenus));

const location = computed<MenuLocation>(() => (route.query.location === 'footer' ? 'footer' : 'header'));
const locations = computed(() => [
    { value: 'header' as const, label: t('admin.menus.header') },
    { value: 'footer' as const, label: t('admin.menus.footer') },
]);

const rows = ref<MenuRow[]>([]);
const targets = ref<MenuTargets>({ pages: [], categories: [] });
const loading = ref(true);
const loadError = ref<string | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);
const snapshot = ref('');

const serialized = (): string => JSON.stringify(toPayload(rows.value));
const dirty = computed(() => !loading.value && snapshot.value !== '' && serialized() !== snapshot.value);
const itemCount = computed(() => rows.value.reduce((count, row) => count + 1 + row.children.length, 0));

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    saveError.value = null;

    try {
        const [items, loadedTargets] = await Promise.all([
            menusApi.show(location.value),
            canManage.value ? menusApi.targets() : Promise.resolve(targets.value),
        ]);
        rows.value = items.map(rowFromItem);
        targets.value = loadedTargets;
        snapshot.value = serialized();
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(location, load, { immediate: true });

// ---- editing ----

const dialogOpen = ref(false);
/** The row being edited, or null for a new one. */
const editing = ref<MenuRow | null>(null);
/** Where a new item goes: null = a top item, else the index of its parent. */
const addingUnder = ref<number | null>(null);

function addItem(parentIndex: number | null = null): void {
    editing.value = null;
    addingUnder.value = parentIndex;
    dialogOpen.value = true;
}

function onDialogDone(row: MenuRow): void {
    dialogOpen.value = false;
    const target = editing.value;

    if (target) {
        rows.value = rows.value.map((top) =>
            top.key === target.key
                ? row
                : { ...top, children: top.children.map((child) => (child.key === target.key ? row : child)) },
        );
    } else if (addingUnder.value !== null) {
        const parentIndex = addingUnder.value;
        rows.value = rows.value.map((top, index) =>
            index === parentIndex ? { ...top, children: [...top.children, row] } : top,
        );
    } else {
        rows.value = [...rows.value, row];
    }

    editing.value = null;
}

function actTop(index: number, action: string): void {
    const row = rows.value[index];

    if (!row) {
        return;
    }

    switch (action) {
        case 'edit':
            editing.value = row;
            dialogOpen.value = true;
            break;
        case 'addSub':
            addItem(index);
            break;
        case 'up':
        case 'down':
            rows.value = moved(rows.value, index, action === 'up' ? -1 : 1);
            break;
        case 'indent':
            rows.value = indented(rows.value, index);
            break;
        case 'remove':
            rows.value = rows.value.filter((_, i) => i !== index);
            break;
    }
}

function actSub(parentIndex: number, index: number, action: string): void {
    const parent = rows.value[parentIndex];
    const row = parent?.children[index];

    if (!parent || !row) {
        return;
    }

    const withChildren = (children: MenuRow[]): MenuRow[] =>
        rows.value.map((top, i) => (i === parentIndex ? { ...top, children } : top));

    switch (action) {
        case 'edit':
            editing.value = row;
            dialogOpen.value = true;
            break;
        case 'up':
        case 'down':
            rows.value = withChildren(moved(parent.children, index, action === 'up' ? -1 : 1));
            break;
        case 'outdent':
            rows.value = outdented(rows.value, parentIndex, index);
            break;
        case 'remove':
            rows.value = withChildren(parent.children.filter((_, i) => i !== index));
            break;
    }
}

function undo(): void {
    void load();
}

// ---- saving ----

async function save(): Promise<boolean> {
    saving.value = true;
    saveError.value = null;

    try {
        rows.value = (await menusApi.save(location.value, toPayload(rows.value))).map(rowFromItem);
        snapshot.value = serialized();
        toast.show(t('admin.menus.saved'));

        return true;
    } catch (caught) {
        saveError.value = toApiError(caught).message;
        toast.show(t('admin.menus.saveFailed'), 'danger');

        return false;
    } finally {
        saving.value = false;
    }
}

// ---- switching menus or leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    () =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value) {
                resolve(true);

                return;
            }

            leaveTarget.value = () => resolve(true);
            leaveCancel = () => resolve(false);
        }),
);

function switchTo(next: MenuLocation): void {
    if (next === location.value) {
        return;
    }

    const go = (): void =>
        void router.replace({ query: { ...route.query, location: next === 'header' ? undefined : next } });

    if (!dirty.value) {
        go();

        return;
    }

    leaveTarget.value = () => {
        snapshot.value = serialized();
        go();
    };
    leaveCancel = null;
}

async function saveAndLeave(): Promise<void> {
    if (await save()) {
        leave();
    }
}

function stay(): void {
    leaveCancel?.();
    leaveTarget.value = null;
}

function leave(): void {
    leaveTarget.value?.();
    leaveTarget.value = null;
}

const beforeUnload = (event: BeforeUnloadEvent): void => {
    if (dirty.value) {
        event.preventDefault();
    }
};
window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.menus.title') }}</h1>
                <p class="text-sm text-fg-muted">{{ t('admin.menus.intro') }}</p>
            </div>
            <AppButton v-if="canManage && !loading && !loadError" size="md" @click="addItem()">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.menus.addItem') }}
            </AppButton>
        </div>

        <div class="sm:w-80">
            <SegmentedControl
                :model-value="location"
                :label="t('admin.menus.locationLabel')"
                :options="locations"
                @update:model-value="switchTo"
            />
        </div>

        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <div v-else-if="loadError" class="flex flex-col gap-3">
            <FormAlert :message="loadError" />
            <div>
                <AppButton variant="secondary" size="md" @click="load">{{ t('common.retry') }}</AppButton>
            </div>
        </div>

        <template v-else>
            <FormAlert v-if="saveError" :message="saveError" />

            <section class="rounded-2xl border border-line bg-surface" :aria-label="t(`admin.menus.${location}`)">
                <EmptyState
                    v-if="rows.length === 0"
                    icon="menu"
                    :title="t('admin.menus.empty')"
                    :description="t('admin.menus.emptyText')"
                >
                    <AppButton v-if="canManage" size="md" @click="addItem()">
                        <AppIcon name="plus" :size="18" />
                        {{ t('admin.menus.addItem') }}
                    </AppButton>
                </EmptyState>

                <VueDraggable
                    v-else
                    v-model="rows"
                    tag="ul"
                    handle=".drag-handle"
                    group="menu-top"
                    ghost-class="menu-drop-ghost"
                    :animation="150"
                    :disabled="!canManage"
                    class="flex flex-col divide-y divide-line"
                >
                    <li v-for="(row, index) in rows" :key="row.key">
                        <MenuItemRow
                            :row="row"
                            :sub="false"
                            :can-manage="canManage"
                            :is-first="index === 0"
                            :is-last="index === rows.length - 1"
                            @act="actTop(index, $event)"
                        />
                        <VueDraggable
                            v-model="row.children"
                            tag="ul"
                            handle=".drag-handle"
                            group="menu-sub"
                            ghost-class="menu-drop-ghost"
                            :animation="150"
                            :disabled="!canManage"
                            :class="[
                                'flex flex-col divide-y divide-line',
                                row.children.length > 0 && 'border-t border-line',
                            ]"
                        >
                            <li v-for="(child, childIndex) in row.children" :key="child.key">
                                <MenuItemRow
                                    :row="child"
                                    sub
                                    :can-manage="canManage"
                                    :is-first="childIndex === 0"
                                    :is-last="childIndex === row.children.length - 1"
                                    @act="actSub(index, childIndex, $event)"
                                />
                            </li>
                        </VueDraggable>
                    </li>
                </VueDraggable>
            </section>

            <p v-if="rows.length > 0" class="text-[13px] leading-relaxed text-fg-muted">
                {{ t('admin.menus.hint', { n: formatNumber(itemCount) }) }}
            </p>
        </template>

        <div
            v-if="dirty && canManage"
            class="sticky bottom-0 z-30 -mx-4 flex flex-wrap items-center gap-3 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur sm:mx-0 sm:rounded-t-2xl sm:border-x"
        >
            <span class="grow text-sm font-semibold text-warning">{{ t('admin.menus.unsaved') }}</span>
            <AppButton variant="secondary" size="md" :disabled="saving" @click="undo">{{
                t('admin.menus.undo')
            }}</AppButton>
            <AppButton size="md" :loading="saving" @click="save">{{ t('admin.menus.save') }}</AppButton>
        </div>

        <MenuItemDialog
            v-if="canManage"
            :open="dialogOpen"
            :row="editing"
            :targets="targets"
            @close="dialogOpen = false"
            @done="onDialogDone"
        />

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.menus.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.menus.leaveText') }}</p>
            <template #footer>
                <AppButton size="md" :loading="saving" @click="saveAndLeave">{{
                    t('admin.menus.saveAndContinue')
                }}</AppButton>
                <AppButton variant="danger" size="md" :disabled="saving" @click="leave">{{
                    t('admin.menus.discard')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="saving" @click="stay">{{
                    t('admin.menus.stay')
                }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>

<style scoped>
:deep(.menu-drop-ghost) {
    opacity: 0.4;
}
</style>
