<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StoreIcon from '../components/StoreIcon.vue';
import type { HeroSlide } from '../content';
import { formatNumber } from '../money';

const props = defineProps<{ slides: HeroSlide[]; fallbackImage: { src: string; alt: string } | null }>();

const current = ref(0);
const paused = ref(false);
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let timer: ReturnType<typeof setInterval> | undefined;

const slide = computed(() => props.slides[current.value] ?? props.slides[0]);
const image = computed(() =>
    slide.value?.image ? { src: slide.value.image, alt: slide.value.title } : props.fallbackImage,
);

function go(step: number): void {
    const count = props.slides.length;
    current.value = (current.value + step + count) % count;
}

watch(
    () => props.slides.length,
    () => {
        current.value = 0;
    },
);

onMounted(() => {
    if (reducedMotion) {
        return;
    }

    timer = setInterval(() => {
        if (!paused.value && props.slides.length > 1) {
            go(1);
        }
    }, 6000);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section
        v-if="slide"
        aria-roledescription="carousel"
        aria-label="بنرهای ویژه"
        class="relative flex min-h-[220px] items-center gap-6 overflow-hidden rounded-2xl bg-surface p-5 pb-16 sm:p-8 sm:pb-16 lg:h-[400px] lg:px-14 lg:py-0"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
        @focusin="paused = true"
        @focusout="paused = false"
    >
        <div
            :key="current"
            aria-roledescription="slide"
            :aria-label="`${formatNumber(current + 1)} از ${formatNumber(slides.length)}`"
            class="flex min-w-0 flex-1 flex-col gap-2.5 lg:gap-3.5"
        >
            <span
                v-if="slide.badge"
                class="text-primary-ink self-start rounded-md bg-primary-soft px-2 py-0.5 text-[11px] font-semibold lg:px-3 lg:py-1 lg:text-[13px]"
            >
                {{ slide.badge }}
            </span>
            <h1 class="m-0 max-w-[12em] text-[22px] leading-[1.3] font-bold lg:text-[46px] lg:leading-[1.2]">
                {{ slide.title }}
            </h1>
            <p v-if="slide.text" class="text-muted m-0 hidden max-w-[30ch] text-[17px] sm:block">{{ slide.text }}</p>
            <RouterLink
                :to="slide.to"
                class="mt-2 self-start rounded-[10px] bg-primary px-4 py-2.5 text-[13px] font-semibold text-white hover:text-white lg:px-7 lg:py-[13px] lg:text-[15px]"
            >
                {{ slide.cta }}
            </RouterLink>
        </div>

        <img
            v-if="image"
            :src="image.src"
            :alt="image.alt"
            class="size-[130px] shrink-0 object-contain sm:size-[220px] lg:size-[340px]"
        />
        <span
            v-else
            aria-hidden="true"
            class="grid size-[130px] shrink-0 place-items-center rounded-2xl bg-bg text-primary sm:size-[220px] lg:size-[340px]"
        >
            <StoreIcon name="bolt" filled class="size-1/2" />
        </span>

        <template v-if="slides.length > 1">
            <div class="absolute start-5 bottom-6 flex gap-1.5 sm:start-8 lg:start-14 lg:bottom-5" aria-hidden="true">
                <span
                    v-for="(_, index) in slides"
                    :key="index"
                    class="h-1 rounded-sm transition-all"
                    :class="index === current ? 'w-6 bg-primary' : 'w-3 bg-line-strong'"
                />
            </div>
            <div class="absolute end-4 bottom-3 flex gap-2">
                <button
                    type="button"
                    aria-label="بنر قبلی"
                    class="text-ink grid size-11 place-items-center rounded-full border border-line bg-surface"
                    @click="go(-1)"
                >
                    <StoreIcon name="chevron-prev" :stroke-width="2" class="size-[18px]" />
                </button>
                <button
                    type="button"
                    aria-label="بنر بعدی"
                    class="text-ink grid size-11 place-items-center rounded-full border border-line bg-surface"
                    @click="go(1)"
                >
                    <StoreIcon name="chevron-next" :stroke-width="2" class="size-[18px]" />
                </button>
            </div>
        </template>
    </section>
</template>
