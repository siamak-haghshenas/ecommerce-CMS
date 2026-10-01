<script setup lang="ts">
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { ROLE_KEYS, STAFF_ROLES, type StaffRole } from '../../../permissions';

defineProps<{ error?: string }>();

/** Radio cards: each fixed role with what it can do. */
const model = defineModel<StaffRole>({ required: true });

const { t } = useI18n();
const name = useId();
</script>

<template>
    <fieldset class="flex flex-col gap-2.5">
        <legend class="mb-2 text-sm font-semibold">{{ t('admin.staff.role') }}</legend>
        <label
            v-for="role in STAFF_ROLES"
            :key="role"
            :class="[
                'flex cursor-pointer items-start gap-3 rounded-xl p-3.5 transition-colors focus-within:ring-2 focus-within:ring-primary',
                model === role
                    ? 'border-2 border-primary bg-primary-soft'
                    : 'border border-line-strong bg-surface hover:bg-surface-muted',
            ]"
        >
            <input v-model="model" type="radio" :name="name" :value="role" class="mt-1 size-4 accent-primary" />
            <span class="flex flex-col gap-1">
                <span class="text-[15px] font-bold">{{ t(`admin.roleNames.${ROLE_KEYS[role]}`) }}</span>
                <span class="text-[13px] leading-relaxed text-fg-muted">{{
                    t(`admin.roleDescriptions.${ROLE_KEYS[role]}`)
                }}</span>
            </span>
        </label>
        <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>
    </fieldset>
</template>
