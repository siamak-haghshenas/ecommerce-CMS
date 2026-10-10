import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import { saleEventsApi } from './api';

/**
 * Opens the storefront's preview of a sale event's saved landing page, whatever its state.
 * Without a storefront preview address, the token is copied instead.
 */
export function useLandingPreview() {
    const { t } = useI18n();
    const toast = useToast();
    const previewing = ref(false);

    async function openPreview(id: number): Promise<void> {
        // Opened right away (inside the click), so browsers don't block it as a pop-up.
        const tab = window.open('', '_blank');
        previewing.value = true;

        try {
            const preview = await saleEventsApi.preview(id);

            if (preview.url && tab) {
                tab.opener = null;
                tab.location.href = preview.url;
            } else {
                tab?.close();
                await navigator.clipboard?.writeText(preview.token).catch(() => undefined);
                toast.show(t(preview.url ? 'admin.pages.preview.blocked' : 'admin.pages.preview.copied'));
            }
        } catch (caught) {
            tab?.close();
            toast.show(toApiError(caught).message, 'danger');
        } finally {
            previewing.value = false;
        }
    }

    return { previewing, openPreview };
}
