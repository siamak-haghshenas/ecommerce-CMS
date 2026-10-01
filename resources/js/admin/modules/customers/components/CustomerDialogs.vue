<script setup lang="ts">
import type { CustomerDialog } from '../composables/useCustomerActions';
import type { Customer } from '../types';
import AnonymiseCustomerDialog from './AnonymiseCustomerDialog.vue';
import DeactivateCustomerDialog from './DeactivateCustomerDialog.vue';
import EditCustomerDialog from './EditCustomerDialog.vue';

defineProps<{ dialog: CustomerDialog | null; customer: Customer | null }>();
const emit = defineEmits<{ close: []; finished: [customer: Customer] }>();
</script>

<template>
    <EditCustomerDialog
        :open="dialog === 'edit'"
        :customer="customer"
        @close="emit('close')"
        @saved="emit('finished', $event)"
    />
    <DeactivateCustomerDialog
        :open="dialog === 'deactivate'"
        :customer="customer"
        @close="emit('close')"
        @done="emit('finished', $event)"
    />
    <AnonymiseCustomerDialog
        :open="dialog === 'anonymise'"
        :customer="customer"
        @close="emit('close')"
        @done="emit('finished', $event)"
    />
</template>
