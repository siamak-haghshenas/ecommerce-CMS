<script setup lang="ts">
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

const { t } = useI18n();
const route = useRoute();
const appName = import.meta.env.VITE_APP_NAME;

// Re-runs when the route or the language changes.
watchEffect(() => {
    document.title = route.meta.title ? `${t(route.meta.title)} | ${appName}` : appName;
});
</script>

<template>
    <RouterView />
</template>
