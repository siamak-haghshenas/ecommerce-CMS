<script setup lang="ts">
import type { PromoCard } from '../content';

defineProps<{ cards: PromoCard[] }>();
</script>

<template>
    <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-1 lg:grid-rows-2 lg:gap-4">
        <RouterLink
            v-for="(card, index) in cards.slice(0, 2)"
            :key="index"
            :to="card.to"
            class="flex min-h-24 items-center gap-4 overflow-hidden rounded-[14px] p-3.5 text-white hover:text-white lg:rounded-2xl lg:p-6"
            :class="index === 0 ? 'bg-ink' : 'bg-primary'"
        >
            <span class="flex min-w-0 flex-1 flex-col justify-between gap-1.5 self-stretch lg:justify-center">
                <b class="text-[15px] leading-[1.35] font-semibold lg:text-[22px]">{{ card.title }}</b>
                <span
                    v-if="card.text"
                    class="hidden text-sm lg:block"
                    :class="index === 0 ? 'text-line-strong' : 'text-primary-ring'"
                >
                    {{ card.text }}
                </span>
                <span
                    class="text-xs font-semibold lg:text-sm"
                    :class="index === 0 ? 'text-primary-on-dark' : 'underline'"
                >
                    {{ card.cta }}
                </span>
            </span>
            <img
                v-if="card.image"
                :src="card.image"
                alt=""
                class="hidden size-[130px] rounded-xl bg-surface object-contain lg:block"
            />
        </RouterLink>
    </div>
</template>
