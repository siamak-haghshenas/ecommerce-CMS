<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toApiError } from '@/shared/http/errors';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { attributesApi } from '../../attributes/api';
import type { Attribute, CategoryAttributes } from '../../attributes/types';

/**
 * The attributes a category's products use: inherited ones (from the chosen parent and its
 * ancestors, read-only) plus the category's own, which the admin picks and orders. Nothing is
 * saved here: the form saves the own list after saving the category.
 */
const props = defineProps<{ parentId: number | null; parentName: string | null }>();
const ownIds = defineModel<number[]>({ required: true });

const { t } = useI18n();
const id = useId();

const all = ref<Attribute[] | null>(null);
const parent = ref<CategoryAttributes | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const toAdd = ref('');

async function loadAll(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        all.value = await attributesApi.list();
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void loadAll();

// The parent's own and inherited attributes are what this category inherits (even before saving).
watch(
    () => props.parentId,
    async (parentId) => {
        parent.value = null;

        if (parentId === null) {
            return;
        }

        try {
            parent.value = await attributesApi.forCategory(parentId);
        } catch (caught) {
            error.value = toApiError(caught).message;
        }
    },
    { immediate: true },
);

const byId = computed(() => new Map((all.value ?? []).map((attribute) => [attribute.id, attribute])));

const inherited = computed(() => {
    if (!parent.value || props.parentId === null) {
        return [];
    }

    const fromParent = { id: props.parentId, name: props.parentName ?? '' };

    return [...parent.value.own.map((attribute) => ({ attribute, from: fromParent })), ...parent.value.inherited];
});
const inheritedIds = computed(() => new Set(inherited.value.map((item) => item.attribute.id)));

const own = computed(() =>
    ownIds.value.map((attributeId) => byId.value.get(attributeId)).filter((item): item is Attribute => !!item),
);

/** Attributes that can still be added: not own and not already inherited. */
const available = computed(() =>
    (all.value ?? []).filter(
        (attribute) => !ownIds.value.includes(attribute.id) && !inheritedIds.value.has(attribute.id),
    ),
);

function add(): void {
    const attributeId = Number(toAdd.value);

    if (attributeId > 0 && !ownIds.value.includes(attributeId)) {
        ownIds.value = [...ownIds.value, attributeId];
    }
    toAdd.value = '';
}

function remove(attributeId: number): void {
    ownIds.value = ownIds.value.filter((item) => item !== attributeId);
}

function move(index: number, offset: -1 | 1): void {
    const ids = [...ownIds.value];
    const target = index + offset;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target] as number, ids[index] as number];
    ownIds.value = ids;
}

const iconButton =
    'flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40';
</script>

<template>
    <section class="flex flex-col gap-3 rounded-xl border border-line p-4" :aria-labelledby="`${id}-title`">
        <div class="flex flex-col gap-1">
            <h3 :id="`${id}-title`" class="text-sm font-bold">{{ t('admin.categories.attributesTitle') }}</h3>
            <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.categories.attributesHint') }}</p>
        </div>

        <FormAlert v-if="error" :message="error" />

        <div v-if="loading" class="flex justify-center py-4"><AppSpinner /></div>

        <template v-else-if="all">
            <div v-if="inherited.length > 0" class="flex flex-col gap-2">
                <span class="text-xs font-semibold text-fg-muted">{{ t('admin.categories.attributesInherited') }}</span>
                <ul class="flex flex-wrap gap-1.5">
                    <li
                        v-for="item in inherited"
                        :key="item.attribute.id"
                        class="inline-flex h-7 items-center gap-1.5 rounded-full bg-surface-muted px-3 text-xs text-fg-muted"
                    >
                        <AppIcon name="lock" :size="12" />
                        {{ item.attribute.name }}
                        <span class="opacity-75"
                            >· {{ t('admin.categories.attributesFrom', { name: item.from.name }) }}</span
                        >
                    </li>
                </ul>
            </div>

            <div class="flex flex-col gap-2">
                <span class="text-xs font-semibold text-fg-muted">{{ t('admin.categories.attributesOwn') }}</span>
                <p v-if="own.length === 0" class="text-[13px] text-fg-muted">
                    {{ t('admin.categories.attributesNone') }}
                </p>
                <ol v-else class="flex flex-col divide-y divide-line rounded-lg border border-line">
                    <li
                        v-for="(attribute, index) in own"
                        :key="attribute.id"
                        class="flex items-center gap-2 px-3 py-1.5"
                    >
                        <span class="min-w-0 grow truncate text-sm font-medium">{{ attribute.name }}</span>
                        <AppBadge>{{ t(`admin.attributes.types.${attribute.type}`) }}</AppBadge>
                        <button
                            type="button"
                            :class="iconButton"
                            :disabled="index === 0"
                            :aria-label="t('admin.categories.attributeUp', { name: attribute.name })"
                            @click="move(index, -1)"
                        >
                            <AppIcon name="arrowUp" :size="16" />
                        </button>
                        <button
                            type="button"
                            :class="iconButton"
                            :disabled="index === own.length - 1"
                            :aria-label="t('admin.categories.attributeDown', { name: attribute.name })"
                            @click="move(index, 1)"
                        >
                            <AppIcon name="arrowDown" :size="16" />
                        </button>
                        <button
                            type="button"
                            :class="[iconButton, 'hover:bg-danger-soft hover:text-danger']"
                            :aria-label="t('admin.categories.attributeRemove', { name: attribute.name })"
                            @click="remove(attribute.id)"
                        >
                            <AppIcon name="close" :size="16" />
                        </button>
                    </li>
                </ol>
            </div>

            <div v-if="available.length > 0" class="flex flex-wrap items-center gap-2">
                <label :for="`${id}-add`" class="sr-only">{{ t('admin.categories.attributeAdd') }}</label>
                <select
                    :id="`${id}-add`"
                    v-model="toAdd"
                    class="h-11 min-w-0 grow cursor-pointer rounded-[10px] border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft"
                >
                    <option value="">{{ t('admin.categories.attributeChoose') }}</option>
                    <option v-for="attribute in available" :key="attribute.id" :value="String(attribute.id)">
                        {{ attribute.name }}
                    </option>
                </select>
                <AppButton variant="secondary" size="md" :disabled="toAdd === ''" @click="add">
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.categories.attributeAdd') }}
                </AppButton>
            </div>
            <p v-else-if="all.length === 0" class="text-[13px] text-fg-muted">
                {{ t('admin.categories.attributesNoneDefined') }}
                <RouterLink :to="{ name: 'attributes' }" class="font-semibold text-primary hover:text-primary-hover">{{
                    t('admin.categories.attributesManage')
                }}</RouterLink>
            </p>
        </template>
    </section>
</template>
