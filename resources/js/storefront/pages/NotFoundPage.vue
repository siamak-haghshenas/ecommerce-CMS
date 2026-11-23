<script setup lang="ts">
import { paths } from '../links';
</script>

<template>
    <section class="max-w-store mx-auto px-4 pt-8 lg:px-6">
        <div class="flex flex-col items-center gap-3 rounded-2xl bg-surface px-6 py-16 text-center">
            <p class="m-0 text-7xl font-bold text-primary" aria-hidden="true">۴۰۴</p>
            <h1 class="m-0 text-[22px] font-semibold">این صفحه پیدا نشد</h1>
            <p class="text-muted m-0 max-w-[48ch]">
                نشانی را بررسی کنید، یا از صفحه اصلی و دسته‌بندی‌ها دوباره شروع کنید.
            </p>
            <div class="mt-3 flex flex-wrap justify-center gap-3">
                <RouterLink
                    :to="paths.home"
                    class="flex h-11 items-center rounded-[10px] bg-primary px-6 font-semibold text-white hover:text-white"
                >
                    صفحه اصلی
                </RouterLink>
                <RouterLink
                    :to="paths.categories"
                    class="text-ink flex h-11 items-center rounded-[10px] border border-line px-6 font-semibold"
                >
                    دسته‌بندی کالاها
                </RouterLink>
            </div>
        </div>
    </section>
</template>
