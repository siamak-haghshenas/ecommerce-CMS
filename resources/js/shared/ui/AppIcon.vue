<script setup lang="ts">
import { computed } from 'vue';

type Shape = { tag: 'path' | 'circle' | 'rect'; attrs: Record<string, string | number> };

const ICONS = {
    bag: [
        { tag: 'path', attrs: { d: 'M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z' } },
        { tag: 'path', attrs: { d: 'M3 6h18M16 10a4 4 0 0 1-8 0' } },
    ],
    phone: [
        { tag: 'rect', attrs: { x: 5, y: 2, width: 14, height: 20, rx: 2 } },
        { tag: 'path', attrs: { d: 'M12 18h.01' } },
    ],
    lock: [
        { tag: 'rect', attrs: { x: 3, y: 11, width: 18, height: 11, rx: 2 } },
        { tag: 'path', attrs: { d: 'M7 11V7a5 5 0 0 1 10 0v4' } },
    ],
    eye: [
        { tag: 'path', attrs: { d: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z' } },
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 3 } },
    ],
    eyeOff: [
        { tag: 'path', attrs: { d: 'M9.88 9.88a3 3 0 1 0 4.24 4.24' } },
        { tag: 'path', attrs: { d: 'M10.73 5.08A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68' } },
        { tag: 'path', attrs: { d: 'M6.61 6.61A13.5 13.5 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.39-1.61M2 2l20 20' } },
    ],
    moon: [{ tag: 'path', attrs: { d: 'M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z' } }],
    sun: [
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 4 } },
        {
            tag: 'path',
            attrs: {
                d: 'M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41',
            },
        },
    ],
    alert: [
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 10 } },
        { tag: 'path', attrs: { d: 'M12 8v4M12 16h.01' } },
    ],
    check: [{ tag: 'path', attrs: { d: 'M20 6 9 17l-5-5' } }],
    arrowForward: [{ tag: 'path', attrs: { d: 'M5 12h14M12 5l7 7-7 7' } }],
    shield: [{ tag: 'path', attrs: { d: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z' } }],
    message: [{ tag: 'path', attrs: { d: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z' } }],
    card: [
        { tag: 'rect', attrs: { x: 2, y: 5, width: 20, height: 14, rx: 2 } },
        { tag: 'path', attrs: { d: 'M2 10h20' } },
    ],
    package: [
        {
            tag: 'path',
            attrs: {
                d: 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z',
            },
        },
        { tag: 'path', attrs: { d: 'M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12' } },
    ],
    logout: [{ tag: 'path', attrs: { d: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9' } }],
    grid: [
        { tag: 'rect', attrs: { x: 3, y: 3, width: 7, height: 7, rx: 1.5 } },
        { tag: 'rect', attrs: { x: 14, y: 3, width: 7, height: 7, rx: 1.5 } },
        { tag: 'rect', attrs: { x: 3, y: 14, width: 7, height: 7, rx: 1.5 } },
        { tag: 'rect', attrs: { x: 14, y: 14, width: 7, height: 7, rx: 1.5 } },
    ],
    users: [
        { tag: 'circle', attrs: { cx: 9, cy: 8, r: 4 } },
        { tag: 'path', attrs: { d: 'M2 21a7 7 0 0 1 14 0M16 3.13a4 4 0 0 1 0 7.75M22 21a7 7 0 0 0-4-6.32' } },
    ],
    userPlus: [
        { tag: 'circle', attrs: { cx: 9, cy: 8, r: 4 } },
        { tag: 'path', attrs: { d: 'M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6' } },
    ],
    userX: [
        { tag: 'circle', attrs: { cx: 9, cy: 8, r: 4 } },
        { tag: 'path', attrs: { d: 'M2 21a7 7 0 0 1 14 0M17 8l5 5M22 8l-5 5' } },
    ],
    clock: [
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 10 } },
        { tag: 'path', attrs: { d: 'M12 6v6l4 2' } },
    ],
    shieldCheck: [
        { tag: 'path', attrs: { d: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z' } },
        { tag: 'path', attrs: { d: 'm9 12 2 2 4-4' } },
    ],
    cart: [
        { tag: 'circle', attrs: { cx: 8, cy: 21, r: 1 } },
        { tag: 'circle', attrs: { cx: 19, cy: 21, r: 1 } },
        { tag: 'path', attrs: { d: 'M2 2h3l2.7 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6' } },
    ],
    tag: [
        {
            tag: 'path',
            attrs: {
                d: 'M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z',
            },
        },
        { tag: 'circle', attrs: { cx: 7.5, cy: 7.5, r: 1.5 } },
    ],
    file: [
        { tag: 'path', attrs: { d: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z' } },
        { tag: 'path', attrs: { d: 'M14 2v6h6M8 13h8M8 17h5' } },
    ],
    pen: [{ tag: 'path', attrs: { d: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z' } }],
    image: [
        { tag: 'rect', attrs: { x: 3, y: 3, width: 18, height: 18, rx: 2 } },
        { tag: 'circle', attrs: { cx: 9, cy: 9, r: 2 } },
        { tag: 'path', attrs: { d: 'm21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21' } },
    ],
    settings: [
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 3 } },
        {
            tag: 'path',
            attrs: {
                d: 'M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1',
            },
        },
    ],
    history: [{ tag: 'path', attrs: { d: 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l4 2' } }],
    menu: [{ tag: 'path', attrs: { d: 'M4 6h16M4 12h16M4 18h16' } }],
    close: [{ tag: 'path', attrs: { d: 'M18 6 6 18M6 6l12 12' } }],
    search: [
        { tag: 'circle', attrs: { cx: 11, cy: 11, r: 7 } },
        { tag: 'path', attrs: { d: 'm21 21-4.3-4.3' } },
    ],
    chevronDown: [{ tag: 'path', attrs: { d: 'm6 9 6 6 6-6' } }],
    chevronNext: [{ tag: 'path', attrs: { d: 'm9 18 6-6-6-6' } }],
    more: [
        { tag: 'circle', attrs: { cx: 12, cy: 5, r: 1.5 } },
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 1.5 } },
        { tag: 'circle', attrs: { cx: 12, cy: 19, r: 1.5 } },
    ],
    mail: [
        { tag: 'rect', attrs: { x: 2, y: 4, width: 20, height: 16, rx: 2 } },
        { tag: 'path', attrs: { d: 'm22 7-10 6L2 7' } },
    ],
    globe: [
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 10 } },
        {
            tag: 'path',
            attrs: {
                d: 'M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z',
            },
        },
    ],
    ban: [
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 10 } },
        { tag: 'path', attrs: { d: 'm4.9 4.9 14.2 14.2' } },
    ],
    eraser: [
        {
            tag: 'path',
            attrs: { d: 'm7 21-4.3-4.3a1 1 0 0 1 0-1.4l10-10a1 1 0 0 1 1.4 0l5.6 5.6a1 1 0 0 1 0 1.4L11 21z' },
        },
        { tag: 'path', attrs: { d: 'M22 21H7M5 11l9 9' } },
    ],
    swap: [{ tag: 'path', attrs: { d: 'M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5' } }],
    info: [
        { tag: 'circle', attrs: { cx: 12, cy: 12, r: 10 } },
        { tag: 'path', attrs: { d: 'M12 16v-4M12 8h.01' } },
    ],
    trendUp: [{ tag: 'path', attrs: { d: 'm22 7-8.5 8.5-5-5L2 17M16 7h6v6' } }],
    trendDown: [{ tag: 'path', attrs: { d: 'm22 17-8.5-8.5-5 5L2 7M16 17h6v-6' } }],
    refresh: [{ tag: 'path', attrs: { d: 'M21 12a9 9 0 1 1-2.64-6.36L21 8M21 3v5h-5' } }],
    plus: [{ tag: 'path', attrs: { d: 'M12 5v14M5 12h14' } }],
    trash: [
        {
            tag: 'path',
            attrs: {
                d: 'M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6',
            },
        },
    ],
    grip: [
        { tag: 'circle', attrs: { cx: 9, cy: 6, r: 1 } },
        { tag: 'circle', attrs: { cx: 15, cy: 6, r: 1 } },
        { tag: 'circle', attrs: { cx: 9, cy: 12, r: 1 } },
        { tag: 'circle', attrs: { cx: 15, cy: 12, r: 1 } },
        { tag: 'circle', attrs: { cx: 9, cy: 18, r: 1 } },
        { tag: 'circle', attrs: { cx: 15, cy: 18, r: 1 } },
    ],
    arrowUp: [{ tag: 'path', attrs: { d: 'M12 19V5M5 12l7-7 7 7' } }],
    arrowDown: [{ tag: 'path', attrs: { d: 'M12 5v14M19 12l-7 7-7-7' } }],
    folderMove: [
        {
            tag: 'path',
            attrs: {
                d: 'M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
            },
        },
        { tag: 'path', attrs: { d: 'm12 10 3 3-3 3M9 13h6' } },
    ],
    subItem: [{ tag: 'path', attrs: { d: 'M6 4v8a4 4 0 0 0 4 4h8M14 12l4 4-4 4' } }],
    folder: [
        {
            tag: 'path',
            attrs: {
                d: 'M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
            },
        },
    ],
    folderPlus: [
        {
            tag: 'path',
            attrs: {
                d: 'M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z',
            },
        },
        { tag: 'path', attrs: { d: 'M12 10v6M9 13h6' } },
    ],
    video: [
        { tag: 'rect', attrs: { x: 2, y: 5, width: 14, height: 14, rx: 2 } },
        { tag: 'path', attrs: { d: 'm22 8-6 4 6 4z' } },
    ],
    play: [{ tag: 'path', attrs: { d: 'M7 4v16l13-8z' } }],
    download: [{ tag: 'path', attrs: { d: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3' } }],
    copy: [
        { tag: 'rect', attrs: { x: 9, y: 9, width: 13, height: 13, rx: 2 } },
        { tag: 'path', attrs: { d: 'M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1' } },
    ],
    upload: [{ tag: 'path', attrs: { d: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12' } }],
    sliders: [{ tag: 'path', attrs: { d: 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6' } }],
    award: [
        { tag: 'circle', attrs: { cx: 12, cy: 8, r: 6 } },
        { tag: 'path', attrs: { d: 'M15.5 13.2 17 22l-5-3-5 3 1.5-8.8' } },
    ],
    externalLink: [
        { tag: 'path', attrs: { d: 'M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' } },
    ],
    sparkles: [
        {
            tag: 'path',
            attrs: { d: 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z' },
        },
        { tag: 'path', attrs: { d: 'M19 15v4M17 17h4M5 3v3M3.5 4.5h3' } },
    ],
} as const satisfies Record<string, readonly Shape[]>;

export type IconName = keyof typeof ICONS;

const props = withDefaults(defineProps<{ name: IconName; size?: number; flipInRtl?: boolean }>(), {
    size: 20,
    flipInRtl: false,
});

const shapes = computed<readonly Shape[]>(() => ICONS[props.name]);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
        :class="['shrink-0', { 'rtl:-scale-x-100': flipInRtl }]"
    >
        <component :is="shape.tag" v-for="(shape, index) in shapes" :key="index" v-bind="shape.attrs" />
    </svg>
</template>
