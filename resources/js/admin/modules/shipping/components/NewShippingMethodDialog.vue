<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon, { type IconName } from '@/shared/ui/AppIcon.vue';
import TextField from '@/shared/ui/TextField.vue';
import type { ShippingType } from '../types';

/**
 * "New shipping method": a name and how its fee is worked out; the editor opens with them.
 */
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; create: [name: string, type: ShippingType] }>();

const { t } = useI18n();

const name = ref('');
const type = ref<ShippingType>('weight');
const attempted = ref(false);

watch(
    () => props.open,
    (open) => {
        if (open) {
            name.value = '';
            type.value = 'weight';
            attempted.value = false;
        }
    },
);

const options = computed<{ value: ShippingType; icon: IconName }[]>(() => [
    { value: 'weight', icon: 'scale' },
    { value: 'volume', icon: 'box' },
    { value: 'courier', icon: 'truck' },
]);

const nameError = computed(() =>
    attempted.value && name.value.trim() === '' ? t('admin.shipping.problems.required') : undefined,
);

function submit(): void {
    attempted.value = true;

    if (name.value.trim() !== '') {
        emit('create', name.value.trim(), type.value);
    }
}
</script>

<template>
    <AppDialog :open="open" :title="t('admin.shipping.newDialog.title')" icon="truck" size="lg" @close="emit('close')">
        <form id="new-shipping-method" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
            <p class="text-sm text-fg-muted">{{ t('admin.shipping.newDialog.intro') }}</p>
            <TextField
                v-model="name"
                :label="t('admin.shipping.fields.name')"
                :placeholder="t('admin.shipping.newDialog.namePlaceholder')"
                autocomplete="off"
                autofocus
                :error="nameError"
            />
            <fieldset class="flex flex-col gap-2">
                <legend class="mb-2 text-sm font-semibold">{{ t('admin.shipping.sections.pricing') }}</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label
                        v-for="option in options"
                        :key="option.value"
                        :class="[
                            'flex cursor-pointer flex-col gap-2.5 rounded-2xl border p-4 focus-within:ring-2 focus-within:ring-primary',
                            type === option.value ? 'border-2 border-primary bg-primary-soft/40' : 'border-line',
                        ]"
                    >
                        <span class="flex items-center justify-between">
                            <span
                                class="flex size-10 items-center justify-center rounded-[10px] bg-surface-muted text-fg"
                            >
                                <AppIcon :name="option.icon" :size="20" />
                            </span>
                            <input
                                v-model="type"
                                type="radio"
                                name="shipping-type"
                                :value="option.value"
                                class="size-4.5 accent-[var(--color-primary)]"
                            />
                        </span>
                        <span class="text-[15px] font-bold">{{ t(`admin.shipping.types.${option.value}`) }}</span>
                        <span class="text-xs leading-relaxed text-fg-muted">{{
                            t(`admin.shipping.newDialog.describe.${option.value}`)
                        }}</span>
                        <span class="text-xs leading-relaxed text-primary">{{
                            t(`admin.shipping.newDialog.example.${option.value}`)
                        }}</span>
                    </label>
                </div>
            </fieldset>
            <p class="text-xs text-fg-muted">{{ t('admin.shipping.newDialog.startsOff') }}</p>
        </form>
        <template #footer>
            <AppButton type="submit" form="new-shipping-method" size="md">{{
                t('admin.shipping.newDialog.continue')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
