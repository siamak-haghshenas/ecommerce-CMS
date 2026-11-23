<script setup lang="ts">
import { paths } from '../links';
import type { CategoryNode } from '../types';

defineProps<{ categories: CategoryNode[] }>();
</script>

<template>
    <nav aria-label="دسته‌بندی‌ها" class="rounded-[14px] bg-surface px-2 py-4 lg:rounded-2xl lg:p-6">
        <ul class="m-0 grid list-none grid-cols-4 gap-x-1 gap-y-3.5 p-0 text-center lg:grid-cols-8 lg:gap-3">
            <li v-for="category in categories" :key="category.id">
                <RouterLink
                    :to="paths.category(category.slug)"
                    class="text-ink flex flex-col items-center gap-1.5 text-xs lg:gap-2.5 lg:text-sm lg:font-medium"
                >
                    <span
                        class="grid size-[62px] place-items-center overflow-hidden rounded-full bg-bg lg:size-24"
                        aria-hidden="true"
                    >
                        <img
                            v-if="category.image"
                            :src="category.image.thumb"
                            alt=""
                            loading="lazy"
                            class="size-[46px] object-contain mix-blend-multiply lg:size-[70px]"
                        />
                        <b v-else class="text-muted-2 text-xl lg:text-3xl">{{ category.name.charAt(0) }}</b>
                    </span>
                    {{ category.name }}
                </RouterLink>
            </li>
        </ul>
    </nav>
</template>
