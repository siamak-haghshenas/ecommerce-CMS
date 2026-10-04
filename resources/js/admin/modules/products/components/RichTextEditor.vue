<script setup lang="ts">
import Image from '@tiptap/extension-image';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import TextField from '@/shared/ui/TextField.vue';
import type { ProductImage } from '../types';

/**
 * The product description editor (Tiptap). It produces HTML; the server cleans it on save,
 * so only the formatting offered here survives. Images come from the product gallery.
 */
withDefaults(defineProps<{ label: string; images?: ProductImage[]; error?: string }>(), {
    images: () => [],
});
const html = defineModel<string>({ required: true });

const { t } = useI18n();
const id = useId();

const editor = useEditor({
    content: html.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            code: false,
            codeBlock: false,
            link: { openOnClick: false, protocols: ['https', 'http', 'mailto'], autolink: true },
        }),
        Image,
    ],
    editorProps: {
        attributes: {
            id,
            class: 'rich-text min-h-48 px-4 py-3 text-[15px] leading-loose text-fg outline-none',
            'aria-labelledby': `${id}-label`,
            role: 'textbox',
            'aria-multiline': 'true',
        },
    },
    onUpdate: ({ editor: instance }) => {
        html.value = instance.isEmpty ? '' : instance.getHTML();
    },
});

// Content loaded or reset from outside (e.g. after loading the product).
watch(html, (value) => {
    if (editor.value && value !== editor.value.getHTML() && !(value === '' && editor.value.isEmpty)) {
        editor.value.commands.setContent(value, { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

type Tool = { key: string; label: string; icon?: string; text?: string; active: () => boolean; run: () => void };

const tools: Tool[] = [
    {
        key: 'h2',
        label: 'admin.products.editor.heading2',
        text: 'H2',
        active: () => !!editor.value?.isActive('heading', { level: 2 }),
        run: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
    },
    {
        key: 'h3',
        label: 'admin.products.editor.heading3',
        text: 'H3',
        active: () => !!editor.value?.isActive('heading', { level: 3 }),
        run: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
    },
    {
        key: 'bold',
        label: 'admin.products.editor.bold',
        text: 'B',
        active: () => !!editor.value?.isActive('bold'),
        run: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
        key: 'italic',
        label: 'admin.products.editor.italic',
        text: 'I',
        active: () => !!editor.value?.isActive('italic'),
        run: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
        key: 'underline',
        label: 'admin.products.editor.underline',
        text: 'U',
        active: () => !!editor.value?.isActive('underline'),
        run: () => editor.value?.chain().focus().toggleUnderline().run(),
    },
    {
        key: 'bullets',
        label: 'admin.products.editor.bulletList',
        text: '•',
        active: () => !!editor.value?.isActive('bulletList'),
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
    },
    {
        key: 'numbers',
        label: 'admin.products.editor.orderedList',
        text: '1.',
        active: () => !!editor.value?.isActive('orderedList'),
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
    },
    {
        key: 'quote',
        label: 'admin.products.editor.quote',
        text: '❝',
        active: () => !!editor.value?.isActive('blockquote'),
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
    },
];

// ---- link ----

const linkOpen = ref(false);
const linkUrl = ref('');

function openLink(): void {
    linkUrl.value = (editor.value?.getAttributes('link').href as string | undefined) ?? 'https://';
    linkOpen.value = true;
}

function applyLink(): void {
    const url = linkUrl.value.trim();
    const chain = editor.value?.chain().focus().extendMarkRange('link');

    if (url === '' || url === 'https://') {
        chain?.unsetLink().run();
    } else {
        chain?.setLink({ href: url }).run();
    }
    linkOpen.value = false;
}

// ---- image from the gallery ----

const imageOpen = ref(false);

function insertImage(image: ProductImage): void {
    editor.value
        ?.chain()
        .focus()
        .setImage({ src: image.medium ?? image.original, alt: image.alt ?? '' })
        .run();
    imageOpen.value = false;
}
</script>

<template>
    <div>
        <span :id="`${id}-label`" class="mb-2 block text-sm font-semibold text-fg">
            {{ label }} <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
        </span>
        <div
            :class="[
                'overflow-hidden rounded-xl border bg-surface focus-within:ring-4 focus-within:ring-primary-soft',
                error ? 'border-2 border-danger' : 'border-line-strong focus-within:border-primary',
            ]"
        >
            <div
                role="toolbar"
                :aria-label="t('admin.products.editor.toolbar')"
                class="flex flex-wrap items-center gap-1 border-b border-line bg-surface-muted/60 p-1.5"
            >
                <button
                    v-for="tool in tools"
                    :key="tool.key"
                    type="button"
                    :aria-label="t(tool.label)"
                    :title="t(tool.label)"
                    :aria-pressed="tool.active()"
                    :class="[
                        'flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-lg px-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                        tool.active()
                            ? 'bg-surface text-primary shadow-sm'
                            : 'text-fg-muted hover:bg-surface hover:text-fg',
                        tool.key === 'italic' && 'italic',
                        tool.key === 'underline' && 'underline',
                    ]"
                    @click="tool.run()"
                >
                    {{ tool.text }}
                </button>
                <span class="mx-1 h-6 w-px bg-line" aria-hidden="true" />
                <button
                    type="button"
                    :aria-label="t('admin.products.editor.link')"
                    :title="t('admin.products.editor.link')"
                    :aria-pressed="!!editor?.isActive('link')"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="openLink"
                >
                    <AppIcon name="externalLink" :size="16" />
                </button>
                <button
                    type="button"
                    :aria-label="t('admin.products.editor.image')"
                    :title="
                        images.length === 0 ? t('admin.products.editor.imageNone') : t('admin.products.editor.image')
                    "
                    :disabled="images.length === 0"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                    @click="imageOpen = true"
                >
                    <AppIcon name="image" :size="16" />
                </button>
            </div>
            <EditorContent :editor="editor" />
        </div>
        <p v-if="error" class="mt-2 text-[13px] text-danger">{{ error }}</p>

        <AppDialog :open="linkOpen" :title="t('admin.products.editor.linkTitle')" @close="linkOpen = false">
            <form id="link-form" novalidate @submit.prevent="applyLink">
                <TextField
                    v-model="linkUrl"
                    :label="t('admin.products.editor.linkUrl')"
                    dir="ltr"
                    :hint="t('admin.products.editor.linkHint')"
                />
            </form>
            <template #footer>
                <AppButton type="submit" form="link-form" size="md">{{
                    t('admin.products.editor.linkApply')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="linkOpen = false">{{ t('common.cancel') }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog :open="imageOpen" :title="t('admin.products.editor.imageTitle')" @close="imageOpen = false">
            <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <li v-for="image in images" :key="image.id">
                    <button
                        type="button"
                        class="block aspect-square w-full cursor-pointer overflow-hidden rounded-lg border border-line hover:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="insertImage(image)"
                    >
                        <img
                            :src="image.thumb ?? image.original"
                            :alt="image.alt ?? ''"
                            class="size-full object-contain"
                        />
                    </button>
                </li>
            </ul>
        </AppDialog>
    </div>
</template>

<style scoped>
/* Readable formatting inside the editor (Tailwind's preflight resets these). */
:deep(.rich-text h2) {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0.75rem 0 0.25rem;
}
:deep(.rich-text h3) {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0.75rem 0 0.25rem;
}
:deep(.rich-text h4) {
    font-weight: 700;
    margin: 0.5rem 0 0.25rem;
}
:deep(.rich-text ul) {
    list-style: disc;
    padding-inline-start: 1.5rem;
}
:deep(.rich-text ol) {
    list-style: decimal;
    padding-inline-start: 1.5rem;
}
:deep(.rich-text blockquote) {
    border-inline-start: 3px solid var(--color-line-strong);
    padding-inline-start: 0.75rem;
    color: var(--color-fg-muted);
}
:deep(.rich-text a) {
    color: var(--color-primary);
    text-decoration: underline;
}
:deep(.rich-text img) {
    max-width: 100%;
    max-height: 18rem;
    border-radius: 0.5rem;
}
:deep(.rich-text p) {
    margin: 0.25rem 0;
}
</style>
