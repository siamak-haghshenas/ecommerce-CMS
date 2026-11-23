<script setup lang="ts">
import { store } from '../content';
import { paths } from '../links';
import StoreIcon from './StoreIcon.vue';

withDefaults(defineProps<{ size?: 'md' | 'sm' }>(), { size: 'md' });
</script>

<template>
    <RouterLink :to="paths.home" class="flex shrink-0 items-center gap-2.5 text-white hover:text-white">
        <span
            class="grid place-items-center bg-primary"
            :class="size === 'md' ? 'size-10 rounded-[10px]' : 'size-9 rounded-[9px]'"
        >
            <StoreIcon name="bolt" filled :class="size === 'md' ? 'size-[22px]' : 'size-5'" />
        </span>
        <b class="font-bold" :class="size === 'md' ? 'text-[26px]' : 'text-[22px]'">{{ store.name }}</b>
        <span class="sr-only">، صفحه اصلی</span>
    </RouterLink>
</template>
