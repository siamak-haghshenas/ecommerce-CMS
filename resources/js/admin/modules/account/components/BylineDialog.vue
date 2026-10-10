<script setup lang="ts">
import { reactive, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import TextField from '@/shared/ui/TextField.vue';
import MediaImageField from '../../media/components/MediaImageField.vue';
import type { MediaPicture } from '../../media/types';
import { slugify } from '../../seo/analysis';
import { accountApi } from '../api';

/**
 * The signed-in staff member's public author byline: blog posts show it as their author.
 * Without a name, posts show no author (the account name is never published).
 */
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const toast = useToast();
const id = useId();
const form = useApiForm(['author_name', 'slug', 'author_bio', 'avatar_id'] as const);

const fields = reactive({ name: '', slug: '', bio: '' });
const avatar = ref<MediaPicture | null>(null);
const slugEdited = ref(false);
const loading = ref(false);
const loadError = ref<string | null>(null);

watch(
    () => props.open,
    async (open) => {
        if (!open) {
            return;
        }

        loading.value = true;
        loadError.value = null;
        form.clearErrors();

        try {
            const byline = await accountApi.byline();
            fields.name = byline.author_name ?? '';
            fields.slug = byline.slug ?? '';
            fields.bio = byline.author_bio ?? '';
            avatar.value = byline.avatar;
            slugEdited.value = byline.slug !== null;
        } catch (caught) {
            loadError.value = toApiError(caught).message;
        } finally {
            loading.value = false;
        }
    },
);

watch(
    () => fields.name,
    (name) => {
        if (!slugEdited.value) {
            fields.slug = slugify(name);
        }
    },
);

function onSlugInput(value: string): void {
    fields.slug = value;
    slugEdited.value = true;
}

async function save(): Promise<void> {
    const name = fields.name.trim();
    const saved = await form.submit(() =>
        accountApi.saveByline({
            author_name: name === '' ? null : name,
            slug: name === '' ? null : fields.slug,
            author_bio: name === '' || fields.bio.trim() === '' ? null : fields.bio.trim(),
            avatar_id: name === '' ? null : (avatar.value?.id ?? null),
        }),
    );

    if (saved) {
        toast.show(t(saved.author_name ? 'admin.byline.saved' : 'admin.byline.removed'));
        emit('close');
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.byline.title')"
        icon="pen"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <div v-if="loading" class="flex justify-center py-8"><AppSpinner /></div>
        <FormAlert v-else-if="loadError" :message="loadError" />
        <form v-else :id="`${id}-form`" class="flex flex-col gap-4.5" novalidate @submit.prevent="save">
            <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.byline.intro') }}</p>
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
            <TextField
                v-model="fields.name"
                :label="t('admin.byline.name')"
                :hint="t('admin.byline.nameHint')"
                optional
                autocomplete="off"
                :error="form.errors.author_name"
            />
            <template v-if="fields.name.trim() !== ''">
                <TextField
                    :model-value="fields.slug"
                    :label="t('admin.byline.slug')"
                    :hint="t('admin.byline.slugHint')"
                    dir="ltr"
                    autocomplete="off"
                    :error="form.errors.slug"
                    @update:model-value="onSlugInput"
                />
                <TextAreaField
                    v-model="fields.bio"
                    :label="t('admin.byline.bio')"
                    optional
                    :rows="3"
                    :maxlength="1000"
                    :error="form.errors.author_bio"
                />
                <MediaImageField
                    v-model="avatar"
                    :label="t('admin.byline.avatar')"
                    :hint="t('admin.byline.avatarHint')"
                    :error="form.errors.avatar_id"
                />
            </template>
        </form>

        <template #footer>
            <AppButton
                type="submit"
                :form="`${id}-form`"
                size="md"
                :loading="form.processing.value"
                :disabled="loading || !!loadError"
                >{{ t('admin.byline.save') }}</AppButton
            >
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">{{
                t('common.cancel')
            }}</AppButton>
        </template>
    </AppDialog>
</template>
