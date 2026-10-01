<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useLocale } from '../composables/useLocale';
import { useTheme } from '../composables/useTheme';
import AppIcon from './AppIcon.vue';

const { t } = useI18n();
const { toggleLocale } = useLocale();
const { isDark, toggleTheme } = useTheme();

const buttonClass =
    'flex h-11 min-w-11 cursor-pointer items-center justify-center rounded-[10px] border border-line bg-surface px-3 text-sm font-semibold text-fg transition-colors duration-150 hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none';
</script>

<template>
    <div class="flex gap-2">
        <button type="button" :aria-label="t('common.switchLanguage')" :class="buttonClass" @click="toggleLocale">
            {{ t('common.languageShort') }}
        </button>
        <button
            type="button"
            :aria-label="isDark ? t('common.lightMode') : t('common.darkMode')"
            :class="buttonClass"
            @click="toggleTheme"
        >
            <AppIcon :name="isDark ? 'sun' : 'moon'" />
        </button>
    </div>
</template>
