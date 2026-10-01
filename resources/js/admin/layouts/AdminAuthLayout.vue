<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import BrandMark from '@/shared/ui/BrandMark.vue';
import PreferenceSwitches from '@/shared/ui/PreferenceSwitches.vue';

const { t } = useI18n();
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-bg">
        <header class="flex h-18 items-center justify-between border-b border-line px-5 sm:px-8">
            <BrandMark compact>
                <span
                    class="rounded-full border border-line bg-surface-muted px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-fg-muted"
                >
                    {{ t('admin.badge') }}
                </span>
            </BrandMark>
            <PreferenceSwitches />
        </header>

        <main class="flex grow items-center justify-center px-5 py-10">
            <div class="w-full max-w-[420px] rounded-[20px] border border-line bg-surface p-6 sm:p-9">
                <RouterView />
            </div>
        </main>
    </div>
</template>
