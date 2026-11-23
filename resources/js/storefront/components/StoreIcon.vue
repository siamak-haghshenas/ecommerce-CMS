<script setup lang="ts">
/** The template's inline stroke icons (decorative: always aria-hidden). */
export type StoreIconName =
    | 'bolt'
    | 'search'
    | 'user'
    | 'bag'
    | 'menu'
    | 'chevron-next'
    | 'chevron-prev'
    | 'plus'
    | 'home'
    | 'grid'
    | 'truck'
    | 'card'
    | 'return'
    | 'shield';

defineProps<{ name: StoreIconName; filled?: boolean; strokeWidth?: number }>();

const paths: Record<StoreIconName, string[]> = {
    bolt: ['M13 2 4 14h7l-1 8 9-12h-7z'],
    search: ['M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14z', 'm20 20-3.5-3.5'],
    user: ['M12 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8z', 'M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6'],
    bag: ['M5 7h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z', 'M9 7a3 3 0 0 1 6 0'],
    menu: ['M4 6h16M4 12h16M4 18h16'],
    // In a right-to-left page "next" points left.
    'chevron-next': ['M15 6l-6 6 6 6'],
    'chevron-prev': ['M9 6l6 6-6 6'],
    plus: ['M12 5v14M5 12h14'],
    home: ['M4 10l8-6 8 6v10h-5v-6H9v6H4z'],
    grid: ['M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
    truck: ['M3 7h11v9H3zM14 10h4l3 3v3h-7zM7 19.3a1.8 1.8 0 1 0 0-3.6M17 19.3a1.8 1.8 0 1 0 0-3.6'],
    card: ['M5 6h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zM3 10h18M7 15h4'],
    return: ['M4 12a8 8 0 1 0 2.3-5.7M4 4v4h4'],
    shield: ['M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6zM9 12l2 2 4-4'],
};
</script>

<template>
    <svg
        aria-hidden="true"
        viewBox="0 0 24 24"
        :fill="filled ? 'currentColor' : 'none'"
        :stroke="filled ? 'none' : 'currentColor'"
        :stroke-width="strokeWidth ?? 1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="shrink-0"
    >
        <path v-for="path in paths[name]" :key="path" :d="path" />
    </svg>
</template>
