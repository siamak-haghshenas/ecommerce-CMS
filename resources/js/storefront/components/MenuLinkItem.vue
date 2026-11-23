<script setup lang="ts">
import type { MenuLink } from '../links';

/** A CMS menu item: a storefront address, an external link, or plain text when it leads nowhere. */
defineProps<{ link: MenuLink }>();
</script>

<template>
    <RouterLink v-if="link.to !== null" :to="link.to" :target="link.newTab ? '_blank' : undefined">
        <slot>{{ link.label }}</slot>
    </RouterLink>
    <a
        v-else-if="link.href !== null"
        :href="link.href"
        :target="link.newTab ? '_blank' : undefined"
        rel="noopener noreferrer"
    >
        <slot>{{ link.label }}</slot>
    </a>
    <span v-else
        ><slot>{{ link.label }}</slot></span
    >
</template>
