<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import ActionMenu from '@/shared/ui/ActionMenu.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { words } from '../../seo/analysis';
import { tagsApi } from '../api';
import type { Tag } from '../types';

/** Tags are created from the product form; here they're renamed, merged and deleted. */
const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageProducts));

const tags = ref<Tag[] | null>(null);
const error = ref<string | null>(null);
const search = ref('');

async function load(): Promise<void> {
    error.value = null;

    try {
        tags.value = await tagsApi.list();
    } catch (caught) {
        error.value = toApiError(caught).message;
    }
}

void load();

const filtered = computed(() => {
    const query = words(search.value).join(' ');

    return (tags.value ?? []).filter((tag) => query === '' || words(tag.name).join(' ').includes(query));
});

// ---- dialogs ----

const dialog = ref<{ kind: 'rename' | 'merge' | 'delete'; tag: Tag } | null>(null);
const name = ref('');
const intoId = ref('');
const form = useApiForm(['name', 'into_tag_id'] as const);

function open(kind: 'rename' | 'merge' | 'delete', tag: Tag): void {
    form.clearErrors();
    name.value = tag.name;
    intoId.value = '';
    dialog.value = { kind, tag };
}

async function confirm(): Promise<void> {
    const current = dialog.value;

    if (!current) {
        return;
    }

    const done = await form.submit(async () => {
        if (current.kind === 'rename') {
            await tagsApi.rename(current.tag.id, name.value);
        } else if (current.kind === 'merge') {
            await tagsApi.merge(current.tag.id, Number(intoId.value));
        } else {
            await tagsApi.remove(current.tag.id);
        }

        return true;
    });

    if (done) {
        toast.show(t(`admin.tags.${current.kind}d`, { name: current.tag.name }));
        dialog.value = null;
        await load();
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold">{{ t('admin.tags.title') }}</h1>
            <p class="text-sm text-fg-muted">{{ t('admin.tags.intro') }}</p>
        </div>

        <SearchInput v-model="search" :label="t('admin.tags.search')" />

        <FormAlert v-if="error" :message="error" />

        <section v-else class="rounded-2xl border border-line bg-surface p-2">
            <div v-if="!tags" aria-hidden="true" class="flex flex-col gap-2 p-1">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-12 animate-pulse rounded-xl bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>
            <EmptyState
                v-else-if="filtered.length === 0"
                :icon="search ? 'search' : 'tag'"
                :title="search ? t('admin.tags.noResults') : t('admin.tags.empty')"
            />
            <ul v-else class="flex flex-col">
                <li
                    v-for="tag in filtered"
                    :key="tag.id"
                    class="flex min-h-14 items-center gap-3 rounded-xl px-3 hover:bg-surface-muted/60"
                >
                    <span class="grow font-semibold">{{ tag.name }}</span>
                    <span class="text-xs text-fg-muted" dir="ltr">{{ tag.slug }}</span>
                    <RouterLink
                        :to="{ name: 'products', query: { tag: String(tag.id) } }"
                        class="text-sm whitespace-nowrap text-fg-muted hover:text-primary"
                    >
                        {{ t('admin.tags.products', { n: formatNumber(tag.products_count ?? 0) }) }}
                    </RouterLink>
                    <RouterLink
                        v-if="tag.posts_count"
                        :to="{ name: 'posts', query: { tag: String(tag.id) } }"
                        class="text-sm whitespace-nowrap text-fg-muted hover:text-primary"
                    >
                        {{ t('admin.tags.posts', { n: formatNumber(tag.posts_count) }) }}
                    </RouterLink>
                    <ActionMenu
                        v-if="canManage"
                        :label="t('admin.tags.actionsFor', { name: tag.name })"
                        :items="[
                            { key: 'rename', label: t('admin.tags.rename'), icon: 'pen' },
                            { key: 'merge', label: t('admin.tags.merge'), icon: 'swap' },
                            {
                                key: 'delete',
                                label: t('admin.tags.delete'),
                                icon: 'trash',
                                danger: true,
                                separated: true,
                            },
                        ]"
                        @select="open($event as 'rename' | 'merge' | 'delete', tag)"
                    />
                </li>
            </ul>
        </section>

        <AppDialog
            :open="dialog !== null"
            :title="dialog ? t(`admin.tags.${dialog.kind}Title`, { name: dialog.tag.name }) : ''"
            :tone="dialog?.kind === 'delete' ? 'danger' : 'primary'"
            :busy="form.processing.value"
            @close="dialog = null"
        >
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
            <form id="tag-form" class="flex flex-col gap-4" novalidate @submit.prevent="confirm">
                <TextField
                    v-if="dialog?.kind === 'rename'"
                    v-model="name"
                    :label="t('admin.tags.name')"
                    :error="form.errors.name"
                />
                <template v-else-if="dialog?.kind === 'merge'">
                    <p class="text-sm leading-relaxed">{{ t('admin.tags.mergeText', { name: dialog.tag.name }) }}</p>
                    <label for="tag-into" class="text-sm font-semibold">{{ t('admin.tags.mergeInto') }}</label>
                    <select
                        id="tag-into"
                        v-model="intoId"
                        class="h-13 cursor-pointer rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft"
                    >
                        <option value="">{{ t('admin.tags.chooseTag') }}</option>
                        <option
                            v-for="tag in (tags ?? []).filter((item) => item.id !== dialog?.tag.id)"
                            :key="tag.id"
                            :value="String(tag.id)"
                        >
                            {{ tag.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.into_tag_id" class="text-[13px] text-danger">{{ form.errors.into_tag_id }}</p>
                </template>
                <p v-else class="text-sm leading-loose">{{ t('admin.tags.deleteText') }}</p>
            </form>
            <template #footer>
                <AppButton
                    type="submit"
                    form="tag-form"
                    :variant="dialog?.kind === 'delete' ? 'danger' : 'primary'"
                    size="md"
                    :loading="form.processing.value"
                    :disabled="dialog?.kind === 'merge' && intoId === ''"
                >
                    {{ dialog ? t(`admin.tags.${dialog.kind}Confirm`) : '' }}
                </AppButton>
                <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="dialog = null">
                    {{ t('common.cancel') }}
                </AppButton>
            </template>
        </AppDialog>
    </div>
</template>
