<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { contentApi } from '../api';
import { store } from '../content';
import { menuLink, type MenuLink } from '../links';
import MenuLinkItem from './MenuLinkItem.vue';
import StoreIcon from './StoreIcon.vue';

interface FooterGroup {
    title: MenuLink;
    links: MenuLink[];
}

/** Footer menu: top-level items are the column titles, their sub-items the links. */
const groups = ref<FooterGroup[]>([]);

onMounted(async () => {
    try {
        groups.value = (await contentApi.menu('footer')).map((item) => ({
            title: menuLink(item),
            links: item.children.map(menuLink),
        }));
    } catch {
        // The footer keeps its trust row and copyright line.
    }
});
</script>

<template>
    <footer class="bg-ink mt-10 text-line-strong">
        <div
            class="max-w-store border-line-dark mx-auto grid grid-cols-1 gap-4 border-b px-4 py-7 sm:grid-cols-2 lg:grid-cols-4 lg:px-6"
        >
            <div v-for="badge in store.trust" :key="badge.title" class="flex items-center gap-3">
                <StoreIcon :name="badge.icon" class="text-primary-on-dark size-8" />
                <span>
                    <b class="block text-sm font-semibold text-white">{{ badge.title }}</b>
                    <small class="text-[12.5px]">{{ badge.text }}</small>
                </span>
            </div>
        </div>

        <div
            v-if="groups.length > 0 || store.supportPhone"
            class="max-w-store mx-auto grid grid-cols-2 gap-6 px-4 py-8 text-sm lg:grid-cols-4 lg:px-6"
        >
            <div v-for="(group, index) in groups" :key="index" class="flex flex-col gap-2.5">
                <MenuLinkItem
                    v-if="group.links.length === 0"
                    :link="group.title"
                    class="text-[15px] font-bold text-white"
                />
                <template v-else>
                    <b class="text-[15px] text-white">{{ group.title.label }}</b>
                    <MenuLinkItem
                        v-for="(link, linkIndex) in group.links"
                        :key="linkIndex"
                        :link="link"
                        class="text-line-strong hover:text-white"
                    />
                </template>
            </div>
            <div v-if="store.supportPhone" class="flex flex-col gap-2.5">
                <b class="text-[15px] text-white">پشتیبانی</b>
                <a :href="`tel:${store.supportPhone}`" dir="ltr" class="self-start text-line-strong hover:text-white">
                    {{ store.supportPhone }}
                </a>
            </div>
        </div>

        <div class="border-line-dark border-t p-4 text-center text-[13px]">
            حقوق این وب‌سایت برای فروشگاه {{ store.name }} محفوظ است.
        </div>
    </footer>
</template>
