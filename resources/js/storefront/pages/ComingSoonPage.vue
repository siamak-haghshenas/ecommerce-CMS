<script setup lang="ts">
import { paths } from '../links';

defineProps<{ title: string }>();
</script>

<template>
    <section class="max-w-store mx-auto px-4 pt-8 lg:px-6">
        <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface px-6 py-16 text-center">
            <h1 class="m-0 text-[22px] font-semibold">{{ title }}</h1>
            <p class="text-muted m-0">این صفحه به‌زودی آماده می‌شود.</p>
            <RouterLink
                :to="paths.home"
                class="mt-2 flex h-11 items-center rounded-[10px] bg-primary px-6 font-semibold text-white hover:text-white"
            >
                بازگشت به صفحه اصلی
            </RouterLink>
        </div>
    </section>
</template>
