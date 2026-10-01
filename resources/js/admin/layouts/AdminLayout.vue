<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import AppIcon from '@/shared/ui/AppIcon.vue';
import PreferenceSwitches from '@/shared/ui/PreferenceSwitches.vue';
import ToastHost from '@/shared/ui/ToastHost.vue';
import { readStorage, writeStorage } from '@/shared/utils/storage';
import AdminSidebar from './AdminSidebar.vue';

const COLLAPSED_STORAGE_KEY = 'admin.sidebarCollapsed';

const { t } = useI18n();
const route = useRoute();

/**
 * Desktop (lg and up): the sidebar is open by default and collapses to an icon rail.
 * Smaller screens: it is a drawer over the page.
 */
const desktopQuery = window.matchMedia('(min-width: 1024px)');
const isDesktop = ref(desktopQuery.matches);
const collapsed = ref(readStorage('local', COLLAPSED_STORAGE_KEY) === '1');
const drawerOpen = ref(false);

const menuButton = useTemplateRef('menuButton');
const closeButton = useTemplateRef('closeButton');

const menuExpanded = computed(() => (isDesktop.value ? !collapsed.value : drawerOpen.value));

function onViewportChange(event: MediaQueryListEvent): void {
    isDesktop.value = event.matches;
    drawerOpen.value = false;
}

desktopQuery.addEventListener('change', onViewportChange);
onBeforeUnmount(() => desktopQuery.removeEventListener('change', onViewportChange));

function toggleMenu(): void {
    if (isDesktop.value) {
        collapsed.value = !collapsed.value;
        writeStorage('local', COLLAPSED_STORAGE_KEY, collapsed.value ? '1' : '0');
    } else {
        drawerOpen.value = true;
        void nextTick(() => closeButton.value?.focus());
    }
}

function closeDrawer(): void {
    if (!drawerOpen.value) {
        return;
    }

    drawerOpen.value = false;
    void nextTick(() => menuButton.value?.focus());
}

watch(() => route.fullPath, closeDrawer);
</script>

<template>
    <div class="flex min-h-dvh bg-bg">
        <aside
            :class="[
                'sticky top-0 hidden h-dvh shrink-0 border-e border-line bg-surface transition-[width] duration-200 ease-out lg:block',
                collapsed ? 'w-[76px]' : 'w-[272px]',
            ]"
        >
            <AdminSidebar :collapsed="collapsed" />
        </aside>

        <!-- Below lg the menu is a drawer that opens from the start edge over the page. -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="drawerOpen"
                class="fixed inset-0 z-40 bg-black/40 lg:hidden"
                aria-hidden="true"
                @click="closeDrawer"
            />
        </Transition>
        <Transition
            enter-active-class="transition-transform duration-200 ease-out"
            leave-active-class="transition-transform duration-150 ease-in"
            enter-from-class="-translate-x-full rtl:translate-x-full"
            leave-to-class="-translate-x-full rtl:translate-x-full"
        >
            <div
                v-if="drawerOpen"
                role="dialog"
                aria-modal="true"
                :aria-label="t('admin.nav.label')"
                class="fixed inset-y-0 start-0 z-50 w-[min(300px,85vw)] bg-surface shadow-xl lg:hidden"
                @keydown.esc="closeDrawer"
            >
                <button
                    ref="closeButton"
                    type="button"
                    :aria-label="t('admin.nav.close')"
                    class="absolute end-2 top-2.5 z-10 flex size-11 cursor-pointer items-center justify-center rounded-[10px] text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="closeDrawer"
                >
                    <AppIcon name="close" />
                </button>
                <AdminSidebar in-drawer @navigate="closeDrawer" />
            </div>
        </Transition>

        <div class="flex min-h-dvh min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-bg/85 px-4 backdrop-blur sm:px-8"
            >
                <button
                    ref="menuButton"
                    type="button"
                    :aria-label="menuExpanded ? t('admin.nav.close') : t('admin.nav.open')"
                    :aria-expanded="menuExpanded"
                    class="flex size-11 cursor-pointer items-center justify-center rounded-[10px] border border-line bg-surface text-fg hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="toggleMenu"
                >
                    <AppIcon name="menu" />
                </button>
                <p class="grow truncate text-lg font-bold">{{ route.meta.title ? t(route.meta.title) : '' }}</p>
                <PreferenceSwitches />
            </header>

            <main class="grow px-4 py-6 sm:px-8 sm:py-7">
                <RouterView />
            </main>
        </div>

        <ToastHost />
    </div>
</template>
