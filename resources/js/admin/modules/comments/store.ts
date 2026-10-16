import { defineStore } from 'pinia';
import { ref } from 'vue';
import { commentsApi } from './api';
import type { CommentableType } from './types';

/** How often the menu badges check for new comments while the tab is open. */
const POLL_MS = 60_000;

/** Visits closer together than this reuse the last counts. */
const FRESH_MS = 10_000;

/**
 * How many comments wait for moderation per kind, for the menu badges. Kept fresh: on each page
 * change (`refresh()`), every minute while the tab is visible, when the tab comes back, and right
 * after a moderation (`refresh(true)`).
 */
export const useCommentsStore = defineStore('admin-comments', () => {
    const pending = ref<Record<CommentableType, number> | null>(null);
    let loadedAt = 0;
    let watching = false;

    async function refresh(force = false): Promise<void> {
        if (!force && Date.now() - loadedAt < FRESH_MS) {
            return;
        }

        loadedAt = Date.now();

        try {
            pending.value = await commentsApi.pendingCount();
        } catch {
            // The badge is a convenience: the page itself reports errors.
            loadedAt = 0;
        }
    }

    /** Starts the background checks once, however many menus ask. */
    function watch(): void {
        if (watching) {
            return;
        }

        watching = true;
        void refresh(true);

        window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                void refresh(true);
            }
        }, POLL_MS);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                void refresh();
            }
        });
    }

    return { pending, refresh, watch };
});
