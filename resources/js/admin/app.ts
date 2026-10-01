import '../../css/app.css';

import { createPinia } from 'pinia';
import { createApp } from 'vue';
import { applyDocumentLocale } from '@/shared/composables/useLocale';
import { applyDocumentTheme } from '@/shared/composables/useTheme';
import { i18n } from '@/shared/i18n';
import App from './App.vue';
import { router } from './router';

applyDocumentLocale(i18n.global.locale.value);
applyDocumentTheme();

// Pinia must be installed before the router, whose guards use the auth store.
createApp(App).use(createPinia()).use(i18n).use(router).mount('#app');
