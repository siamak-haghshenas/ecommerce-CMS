<script setup lang="ts">
import { currencyLabel, formatAmount } from '../money';

/** An amount in Rials, shown in the display currency with its unit in small type. */
defineProps<{ rials: number; from?: boolean }>();
</script>

<template>
    <b class="font-bold whitespace-nowrap">
        <small v-if="from" class="text-[11px] font-normal">از </small>{{ formatAmount(rials) }}
        <small class="text-[11px] font-normal">{{ currencyLabel() }}</small>
    </b>
</template>
