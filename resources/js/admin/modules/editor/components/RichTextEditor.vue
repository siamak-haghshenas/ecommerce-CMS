<script setup lang="ts">
import Editor from '@tinymce/tinymce-vue';
import type { Editor as TinyEditor } from 'tinymce';
import { computed, nextTick, ref, shallowRef, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useTheme } from '@/shared/composables/useTheme';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import TextField from '@/shared/ui/TextField.vue';
import MediaPickerDialog from '../../media/components/MediaPickerDialog.vue';
import type { MediaAsset } from '../../media/types';
import { embedHtml, embedUrl, imageHtml, videoHtml } from '../content';
import { htmlToText } from '../richText';
import { contentStyle } from '../tinymce';

/**
 * Rich text with TinyMCE. "basic": bold, italic, underline, links and lists (e.g. a short
 * description). "full": headings, colours, alignment and direction, lists, quotes, tables, links,
 * images and videos from the media library, Aparat / YouTube players, HTML source and fullscreen.
 * The server sanitizes whatever is saved.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        variant?: 'basic' | 'full';
        error?: string;
        hint?: string;
        optional?: boolean;
        /** Counts the text (not the markup) against this limit. */
        maxChars?: number;
    }>(),
    { variant: 'full', error: undefined, hint: undefined, optional: false, maxChars: undefined },
);

const html = defineModel<string>({ required: true });
/** After start-up, once TinyMCE has tidied the loaded HTML (not a change by the admin). */
const emit = defineEmits<{ normalized: [] }>();

const { t, locale } = useI18n();
const { isRtl, formatNumber } = useLocale();
const { theme, isDark } = useTheme();
const id = useId();
const editor = shallowRef<TinyEditor | null>(null);
const picking = ref<'image' | 'video' | null>(null);
const embedOpen = ref(false);
const embedLink = ref('');
const embedError = ref<string | undefined>(undefined);

const VIDEO_ICON =
    '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="14" height="14" rx="2"/><path d="m22 8-6 4 6 4z"/></svg>';

const full = computed(() => props.variant === 'full');

const init = computed(() => ({
    license_key: 'gpl',
    skin_url: 'default',
    skin: isDark.value ? 'oxide-dark' : 'oxide',
    content_css: isDark.value ? 'dark' : 'default',
    content_style: contentStyle,
    language: locale.value === 'fa' ? 'fa' : undefined,
    directionality: isRtl.value ? ('rtl' as const) : ('ltr' as const),
    iframe_aria_text: props.label,
    menubar: false,
    branding: false,
    promotion: false,
    statusbar: full.value,
    elementpath: false,
    resize: true,
    height: full.value ? 520 : 200,
    toolbar_mode: 'wrap' as const,
    // Keep Persian half-spaces and other characters as they are (no &zwnj; entities).
    entity_encoding: 'raw' as const,
    convert_urls: false,
    link_default_target: '_blank',
    plugins: full.value
        ? ['advlist', 'autolink', 'lists', 'link', 'table', 'code', 'fullscreen', 'directionality']
        : ['autolink', 'lists', 'link'],
    toolbar: full.value
        ? 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignright aligncenter alignleft alignjustify | rtl ltr | bullist numlist | blockquote hr | link table | libraryimage libraryvideo embedvideo | removeformat code fullscreen'
        : 'undo redo | bold italic underline | link | bullist numlist | removeformat',
    block_formats: `${t('admin.editor.paragraph')}=p; ${t('admin.editor.heading', { n: 2 })}=h2; ${t('admin.editor.heading', { n: 3 })}=h3; ${t('admin.editor.heading', { n: 4 })}=h4`,
    extended_valid_elements:
        'img[src|alt|width|height|data-media-id],video[src|poster|controls|preload|width|height|data-media-id],iframe[src|width|height|title|allowfullscreen]',
    table_default_styles: { width: '100%', 'border-collapse': 'collapse' },
    setup: (instance: TinyEditor) => {
        editor.value = instance;

        if (!full.value) {
            return;
        }

        instance.ui.registry.addIcon('library-video', VIDEO_ICON);
        instance.ui.registry.addButton('libraryimage', {
            icon: 'image',
            tooltip: t('admin.editor.libraryImage'),
            onAction: () => (picking.value = 'image'),
        });
        instance.ui.registry.addButton('libraryvideo', {
            icon: 'library-video',
            tooltip: t('admin.editor.libraryVideo'),
            onAction: () => (picking.value = 'video'),
        });
        instance.ui.registry.addButton('embedvideo', {
            icon: 'embed',
            tooltip: t('admin.editor.embed'),
            onAction: () => {
                embedLink.value = '';
                embedError.value = undefined;
                embedOpen.value = true;
            },
        });
    },
}));

/** Re-created when the theme or language changes (TinyMCE reads them once). */
const editorKey = computed(() => `${theme.value}-${locale.value}-${props.variant}`);

function insertMedia(assets: MediaAsset[]): void {
    const kind = picking.value;
    picking.value = null;

    for (const asset of assets) {
        editor.value?.insertContent(kind === 'video' ? videoHtml(asset, asset.cover?.thumb ?? null) : imageHtml(asset));
    }

    editor.value?.focus();
}

function insertEmbed(): void {
    const src = embedUrl(embedLink.value);

    if (src === null) {
        embedError.value = t('admin.editor.embedInvalid');

        return;
    }

    editor.value?.insertContent(embedHtml(src, t('admin.editor.embedTitle')));
    embedOpen.value = false;
    editor.value?.focus();
}

function onInit(): void {
    const tidied = editor.value?.getContent() ?? html.value;

    if (tidied !== html.value) {
        html.value = tidied;
    }

    // The model updates on the next render; report after it.
    void nextTick(() => emit('normalized'));
}

const charCount = computed(() => (props.maxChars === undefined ? 0 : htmlToText(html.value).length));
</script>

<template>
    <div class="flex flex-col gap-2">
        <span :id="`${id}-label`" class="text-sm font-semibold text-fg">
            {{ label }}
            <span v-if="optional" class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
        </span>
        <div
            :class="['overflow-hidden rounded-xl border', error ? 'border-2 border-danger' : 'border-line-strong']"
            :aria-labelledby="`${id}-label`"
            role="group"
        >
            <Editor :key="editorKey" v-model="html" license-key="gpl" :init="init" @init="onInit" />
        </div>
        <div class="flex items-start justify-between gap-3">
            <p v-if="error" class="text-[13px] text-danger">{{ error }}</p>
            <p v-else-if="hint" class="text-[13px] leading-relaxed text-fg-muted">{{ hint }}</p>
            <span
                v-if="maxChars !== undefined"
                :class="[
                    'ms-auto shrink-0 text-xs tabular-nums',
                    charCount > maxChars ? 'text-danger' : 'text-fg-muted',
                ]"
                >{{ formatNumber(charCount) }} / {{ formatNumber(maxChars) }}</span
            >
        </div>

        <MediaPickerDialog
            v-if="full"
            :open="picking !== null"
            :title="picking === 'video' ? t('admin.editor.libraryVideo') : t('admin.editor.libraryImage')"
            :type="picking ?? 'image'"
            multiple
            @close="picking = null"
            @choose="insertMedia"
        />

        <AppDialog :open="embedOpen" :title="t('admin.editor.embed')" icon="video" @close="embedOpen = false">
            <form :id="`${id}-embed`" class="flex flex-col gap-2" @submit.prevent="insertEmbed">
                <TextField
                    v-model="embedLink"
                    :label="t('admin.editor.embedLink')"
                    :hint="t('admin.editor.embedHint')"
                    :error="embedError"
                    dir="ltr"
                    autofocus
                />
            </form>
            <template #footer>
                <AppButton type="submit" :form="`${id}-embed`" size="md">{{ t('admin.editor.embedInsert') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="embedOpen = false">{{ t('common.cancel') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
