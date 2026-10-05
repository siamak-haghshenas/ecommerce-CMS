<script setup lang="ts">
import { computed, reactive, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { newKey, type MenuRow } from '../tree';
import type { MenuItemType, MenuTargets } from '../types';

/**
 * Add or edit one menu item: a page, a category or a custom link, with an optional label and
 * "open in a new tab". Nothing is saved until the menu's Save.
 */
const props = defineProps<{ open: boolean; row: MenuRow | null; targets: MenuTargets }>();
const emit = defineEmits<{ close: []; done: [row: MenuRow] }>();

const { t } = useI18n();
const id = useId();

const fields = reactive({
    type: 'page' as MenuItemType,
    pageId: null as number | null,
    categoryId: null as number | null,
    url: '',
    label: '',
    openInNewTab: false,
});
const errors = ref<{ target?: string; url?: string; label?: string }>({});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        const row = props.row;
        fields.type = row?.type ?? 'page';
        fields.pageId = row?.pageId ?? null;
        fields.categoryId = row?.categoryId ?? null;
        fields.url = row?.url ?? '';
        fields.label = row?.label ?? '';
        fields.openInNewTab = row?.openInNewTab ?? false;
        errors.value = {};
    },
);

const typeOptions = computed(() => [
    { value: 'page' as const, label: t('admin.menus.types.page') },
    { value: 'category' as const, label: t('admin.menus.types.category') },
    { value: 'url' as const, label: t('admin.menus.types.url') },
]);

/** Product categories, then blog categories (only the groups that have any). */
const categoryGroups = computed(() =>
    (['product', 'blog'] as const)
        .map((type) => ({ type, items: props.targets.categories.filter((category) => category.type === type) }))
        .filter((group) => group.items.length > 0),
);

/** The same check as the server: an http/https address, or a path starting with "/". */
function isValidLink(url: string): boolean {
    if (url.startsWith('/')) {
        return !url.startsWith('//') && !/\s/.test(url);
    }

    try {
        const parsed = new URL(url);

        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
}

function submit(): void {
    const page = props.targets.pages.find((item) => item.id === fields.pageId);
    const category = props.targets.categories.find((item) => item.id === fields.categoryId);
    const url = fields.url.trim();
    errors.value = {};

    if (fields.type === 'page' && !page) {
        errors.value.target = t('admin.menus.dialog.choosePage');
    } else if (fields.type === 'category' && !category) {
        errors.value.target = t('admin.menus.dialog.chooseCategory');
    } else if (fields.type === 'url') {
        if (!isValidLink(url)) {
            errors.value.url = t('admin.menus.dialog.urlInvalid');
        }

        if (fields.label.trim() === '') {
            errors.value.label = t('admin.menus.dialog.labelRequired');
        }
    }

    if (Object.keys(errors.value).length > 0) {
        return;
    }

    const visible =
        fields.type === 'page' ? page?.state === 'published' : fields.type === 'category' ? category?.is_visible : true;

    emit('done', {
        key: props.row?.key ?? newKey(),
        id: props.row?.id ?? null,
        type: fields.type,
        label: fields.label.trim(),
        pageId: fields.type === 'page' ? (page?.id ?? null) : null,
        categoryId: fields.type === 'category' ? (category?.id ?? null) : null,
        categoryType: fields.type === 'category' ? (category?.type ?? null) : null,
        url: fields.type === 'url' ? url : '',
        openInNewTab: fields.openInNewTab,
        targetName: page?.title ?? category?.name ?? '',
        targetDetail: fields.type === 'url' ? url : (page?.slug ?? category?.slug ?? ''),
        // A guess until the menu is saved; the server says exactly why after saving.
        hiddenReason: visible
            ? null
            : fields.type === 'category'
              ? 'category_hidden'
              : page?.state === 'scheduled'
                ? 'page_scheduled'
                : 'page_draft',
        children: props.row?.children ?? [],
    });
}

const selectClass =
    'h-13 w-full cursor-pointer rounded-xl border bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <AppDialog
        :open="open"
        :title="row ? t('admin.menus.dialog.editTitle') : t('admin.menus.dialog.addTitle')"
        icon="menu"
        @close="emit('close')"
    >
        <form :id="`${id}-form`" class="flex flex-col gap-4.5" novalidate @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold">{{ t('admin.menus.dialog.type') }}</span>
                <SegmentedControl v-model="fields.type" :label="t('admin.menus.dialog.type')" :options="typeOptions" />
            </div>

            <div v-if="fields.type === 'page'">
                <label :for="`${id}-page`" class="mb-2 block text-sm font-semibold">{{
                    t('admin.menus.types.page')
                }}</label>
                <select
                    :id="`${id}-page`"
                    :value="fields.pageId === null ? '' : String(fields.pageId)"
                    :class="[selectClass, errors.target ? 'border-2 border-danger' : 'border-line-strong']"
                    @change="
                        fields.pageId =
                            ($event.target as HTMLSelectElement).value === ''
                                ? null
                                : Number(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option value="">{{ t('admin.menus.dialog.choosePage') }}</option>
                    <option v-for="page in targets.pages" :key="page.id" :value="String(page.id)">
                        {{ page.title
                        }}{{ page.state === 'published' ? '' : ` (${t(`admin.pages.state.${page.state}`)})` }}
                    </option>
                </select>
                <p v-if="errors.target" class="mt-2 text-[13px] text-danger">{{ errors.target }}</p>
                <p v-else-if="targets.pages.length === 0" class="mt-2 text-[13px] text-fg-muted">
                    {{ t('admin.menus.dialog.noPages') }}
                </p>
            </div>

            <div v-else-if="fields.type === 'category'">
                <label :for="`${id}-category`" class="mb-2 block text-sm font-semibold">{{
                    t('admin.menus.types.category')
                }}</label>
                <select
                    :id="`${id}-category`"
                    :value="fields.categoryId === null ? '' : String(fields.categoryId)"
                    :class="[selectClass, errors.target ? 'border-2 border-danger' : 'border-line-strong']"
                    @change="
                        fields.categoryId =
                            ($event.target as HTMLSelectElement).value === ''
                                ? null
                                : Number(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option value="">{{ t('admin.menus.dialog.chooseCategory') }}</option>
                    <optgroup
                        v-for="group in categoryGroups"
                        :key="group.type"
                        :label="t(`admin.menus.categoryGroups.${group.type}`)"
                    >
                        <option v-for="category in group.items" :key="category.id" :value="String(category.id)">
                            {{ '— '.repeat(category.depth) }}{{ category.name
                            }}{{ category.is_visible ? '' : ` (${t('admin.menus.hidden.category_hidden')})` }}
                        </option>
                    </optgroup>
                </select>
                <p v-if="errors.target" class="mt-2 text-[13px] text-danger">{{ errors.target }}</p>
            </div>

            <TextField
                v-else
                v-model="fields.url"
                :label="t('admin.menus.dialog.url')"
                :hint="t('admin.menus.dialog.urlHint')"
                placeholder="https://"
                dir="ltr"
                autocomplete="off"
                :error="errors.url"
            />

            <TextField
                v-model="fields.label"
                :label="t('admin.menus.dialog.label')"
                :hint="fields.type === 'url' ? undefined : t('admin.menus.dialog.labelHint')"
                :optional="fields.type !== 'url'"
                autocomplete="off"
                :error="errors.label"
            />

            <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                <input v-model="fields.openInNewTab" type="checkbox" class="size-4 accent-[var(--color-primary)]" />
                {{ t('admin.menus.dialog.newTab') }}
            </label>
        </form>

        <template #footer>
            <AppButton type="submit" :form="`${id}-form`" size="md">{{
                row ? t('admin.menus.dialog.apply') : t('admin.menus.dialog.add')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
