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
import { attributesApi } from '../api';
import { ATTRIBUTE_TYPES, type Attribute, type AttributeType } from '../types';
import { toValueInputs, valueRow, type ValueRow } from '../values';
import ValuesEditor from './ValuesEditor.vue';

/** Creates (attribute null) or edits an attribute with its values. */
const props = defineProps<{ open: boolean; attribute: Attribute | null }>();
const emit = defineEmits<{ close: []; saved: [attribute: Attribute, created: boolean] }>();

const { t } = useI18n();
const form = useApiForm(['name', 'slug', 'type', 'unit', 'is_filterable', 'values'] as const);

const fields = reactive({
    name: '',
    slug: '',
    type: 'select' as AttributeType,
    unit: '',
    filterable: 'yes' as 'yes' | 'no',
});
const values = ref<ValueRow[]>([]);
const slugEdited = ref(false);

const typeOptions = computed(() =>
    ATTRIBUTE_TYPES.map((value) => ({ value, label: t(`admin.attributes.types.${value}`) })),
);
const filterableOptions = computed(() => [
    { value: 'yes' as const, label: t('admin.attributes.filterableYes') },
    { value: 'no' as const, label: t('admin.attributes.filterableNo') },
]);

/** Row errors are shown on their rows; the alert covers everything else. */
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

        const attribute = props.attribute;
        form.clearErrors();
        fields.name = attribute?.name ?? '';
        fields.slug = attribute?.slug ?? '';
        fields.type = attribute?.type ?? 'select';
        fields.unit = attribute?.unit ?? '';
        fields.filterable = attribute?.is_filterable === false ? 'no' : 'yes';
        values.value = attribute ? (attribute.values ?? []).map((value) => valueRow(value)) : [valueRow()];
        slugEdited.value = attribute !== null;
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
        type: fields.type,
        unit: fields.type === 'number' && fields.unit.trim() !== '' ? fields.unit.trim() : null,
        is_filterable: fields.filterable === 'yes',
        values: fields.type === 'select' ? toValueInputs(values.value, false) : [],
    };
    const existing = props.attribute;

    const saved = await form.submit(() =>
        existing ? attributesApi.update(existing.id, payload) : attributesApi.create(payload),
    );

    if (saved) {
        emit('saved', saved, existing === null);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="attribute ? t('admin.attributes.editAttribute') : t('admin.attributes.newAttribute')"
        size="lg"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <form id="attribute-form" class="flex flex-col gap-4.5" novalidate @submit.prevent="save">
            <FormAlert v-if="alertMessage" :message="alertMessage" />

            <TextField
                v-model="fields.name"
                :label="t('admin.attributes.name')"
                :hint="t('admin.attributes.attributeNameHint')"
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
                <span class="text-sm font-semibold">{{ t('admin.attributes.type') }}</span>
                <SegmentedControl v-model="fields.type" :label="t('admin.attributes.type')" :options="typeOptions" />
                <p class="text-[13px] leading-relaxed text-fg-muted">
                    {{ t(`admin.attributes.typeHints.${fields.type}`) }}
                </p>
                <p
                    v-if="attribute && attribute.type === 'select' && fields.type !== 'select'"
                    role="note"
                    class="text-[13px] text-warning"
                >
                    {{ t('admin.attributes.typeChangeWarning') }}
                </p>
            </div>

            <TextField
                v-if="fields.type === 'number'"
                v-model="fields.unit"
                :label="t('admin.attributes.unit')"
                :hint="t('admin.attributes.unitHint')"
                optional
                autocomplete="off"
                :error="form.errors.unit"
            />

            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold">{{ t('admin.attributes.filterable') }}</span>
                <SegmentedControl
                    v-model="fields.filterable"
                    :label="t('admin.attributes.filterable')"
                    :options="filterableOptions"
                />
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.attributes.filterableHint') }}</p>
            </div>

            <ValuesEditor
                v-if="fields.type === 'select'"
                v-model="values"
                :label="t('admin.attributes.values')"
                :errors="form.lastError.value?.fieldErrors"
            />
        </form>

        <template #footer>
            <AppButton type="submit" form="attribute-form" size="md" :loading="form.processing.value">
                {{ attribute ? t('admin.attributes.save') : t('admin.attributes.create') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
