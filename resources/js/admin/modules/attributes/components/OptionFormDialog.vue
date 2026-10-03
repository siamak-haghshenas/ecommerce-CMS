<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { slugify } from '../../seo/analysis';
import { optionsApi } from '../api';
import type { Option, OptionDisplayType } from '../types';
import { toValueInputs, valueRow, type ValueRow } from '../values';
import ValuesEditor from './ValuesEditor.vue';

/** Creates (option null) or edits a variant option (Size, Color) with its values. */
const props = defineProps<{ open: boolean; option: Option | null }>();
const emit = defineEmits<{ close: []; saved: [option: Option, created: boolean] }>();

const { t } = useI18n();
const form = useApiForm(['name', 'slug', 'display_type', 'values'] as const);

const fields = reactive({ name: '', slug: '', displayType: 'text' as OptionDisplayType });
const values = ref<ValueRow[]>([]);
const slugEdited = ref(false);

const displayOptions = computed(() => [
    { value: 'text' as const, label: t('admin.attributes.displayText') },
    { value: 'color' as const, label: t('admin.attributes.displayColor') },
]);

const alertMessage = computed(() => {
    const keys = Object.keys(form.lastError.value?.fieldErrors ?? {});

    return keys.length > 0 && keys.every((key) => key.startsWith('values.')) ? null : form.generalError.value;
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        const option = props.option;
        form.clearErrors();
        fields.name = option?.name ?? '';
        fields.slug = option?.slug ?? '';
        fields.displayType = option?.display_type ?? 'text';
        values.value = option ? option.values.map((value) => valueRow(value)) : [valueRow()];
        slugEdited.value = option !== null;
    },
);

watch(
    () => fields.name,
    (name) => {
        if (!slugEdited.value) {
            fields.slug = slugify(name);
        }
    },
);

function onSlugInput(value: string): void {
    fields.slug = value;
    slugEdited.value = true;
}

async function save(): Promise<void> {
    const payload = {
        name: fields.name,
        slug: fields.slug,
        display_type: fields.displayType,
        values: toValueInputs(values.value, fields.displayType === 'color'),
    };
    const existing = props.option;

    const saved = await form.submit(() =>
        existing ? optionsApi.update(existing.id, payload) : optionsApi.create(payload),
    );

    if (saved) {
        emit('saved', saved, existing === null);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="option ? t('admin.attributes.editOption') : t('admin.attributes.newOption')"
        size="lg"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <form id="option-form" class="flex flex-col gap-4.5" novalidate @submit.prevent="save">
            <FormAlert v-if="alertMessage" :message="alertMessage" />

            <TextField
                v-model="fields.name"
                :label="t('admin.attributes.name')"
                :hint="t('admin.attributes.optionNameHint')"
                autocomplete="off"
                :error="form.errors.name"
            />
            <TextField
                :model-value="fields.slug"
                :label="t('admin.attributes.slug')"
                :hint="t('admin.attributes.slugHint')"
                dir="ltr"
                autocomplete="off"
                :error="form.errors.slug"
                @update:model-value="onSlugInput"
            />

            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold">{{ t('admin.attributes.display') }}</span>
                <SegmentedControl
                    v-model="fields.displayType"
                    :label="t('admin.attributes.display')"
                    :options="displayOptions"
                />
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.attributes.displayHint') }}</p>
            </div>

            <ValuesEditor
                v-model="values"
                :label="t('admin.attributes.values')"
                :with-color="fields.displayType === 'color'"
                :errors="form.lastError.value?.fieldErrors"
            />
        </form>

        <template #footer>
            <AppButton type="submit" form="option-form" size="md" :loading="form.processing.value">
                {{ option ? t('admin.attributes.save') : t('admin.attributes.create') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
