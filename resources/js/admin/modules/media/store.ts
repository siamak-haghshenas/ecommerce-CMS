import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { toApiError } from '@/shared/http/errors';
import { mediaApi } from './api';
import { contentTypeOf, nextChunk } from './files';
import type { MediaAsset } from './types';

export type UploadStatus = 'queued' | 'uploading' | 'done' | 'error';

export interface UploadItem {
    key: number;
    file: File;
    name: string;
    size: number;
    /** Bytes the server has. */
    sent: number;
    status: UploadStatus;
    error: string | null;
    folderId: number | null;
    /** The server's upload id, kept to resume after a failure. */
    sessionId: string | null;
    asset: MediaAsset | null;
}

const PARALLEL = 2;
const RETRIES = 3;

/**
 * The upload queue. It lives outside the Media page, so uploads keep going while staff move around
 * the panel. Files are sent in chunks, two files at a time; a failed chunk is retried, a chunk out
 * of order resumes from what the server has, and "Retry" continues an upload where it stopped.
 */
export const useMediaUploadStore = defineStore('admin-media-uploads', () => {
    const items = ref<UploadItem[]>([]);
    /** Goes up whenever a file is finished, so pages can reload. */
    const finished = ref(0);
    const controllers = new Map<number, AbortController>();
    let nextKey = 1;

    const active = computed(() =>
        items.value.filter((item) => item.status === 'queued' || item.status === 'uploading'),
    );
    const progress = computed(() => {
        const total = items.value.reduce((sum, item) => sum + item.size, 0);

        return total === 0 ? 0 : items.value.reduce((sum, item) => sum + item.sent, 0) / total;
    });

    function add(files: File[], folderId: number | null): void {
        for (const file of files) {
            items.value.push({
                key: nextKey++,
                file,
                name: file.name,
                size: file.size,
                sent: 0,
                status: 'queued',
                error: null,
                folderId,
                sessionId: null,
                asset: null,
            });
        }

        pump();
    }

    function pump(): void {
        const running = items.value.filter((item) => item.status === 'uploading').length;

        for (const item of items.value.filter((entry) => entry.status === 'queued').slice(0, PARALLEL - running)) {
            void run(item.key);
        }
    }

    const find = (key: number): UploadItem | undefined => items.value.find((item) => item.key === key);
    const wait = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

    async function run(key: number): Promise<void> {
        const item = find(key);

        if (!item) {
            return;
        }

        const controller = new AbortController();
        controllers.set(key, controller);
        item.status = 'uploading';
        item.error = null;

        try {
            let chunkSize = 1024 * 1024;

            if (item.sessionId === null) {
                const session = await mediaApi.startUpload({
                    name: item.name,
                    mime_type: contentTypeOf(item.name, item.file.type),
                    size: item.size,
                    folder_id: item.folderId,
                });
                item.sessionId = session.id;
                item.sent = session.received;
                chunkSize = session.chunk_size;
            }

            let range = nextChunk(item.size, item.sent, chunkSize);
            let failures = 0;

            while (range && !controller.signal.aborted) {
                try {
                    const session = await mediaApi.sendChunk(
                        item.sessionId,
                        item.file.slice(range[0], range[1]),
                        range[0],
                        controller.signal,
                    );
                    item.sent = session.received;
                    chunkSize = session.chunk_size;
                    failures = 0;
                } catch (caught) {
                    const error = toApiError(caught);

                    if (controller.signal.aborted) {
                        return;
                    }

                    if (error.code === 'media_chunk_out_of_order' && typeof error.body.received === 'number') {
                        item.sent = error.body.received;
                    } else if (error.status === 404) {
                        // The upload expired: start over.
                        item.sessionId = null;
                        item.sent = 0;
                        throw error;
                    } else if (error.status === 0 || error.status >= 500) {
                        if (++failures > RETRIES) {
                            throw error;
                        }
                        await wait(1000 * 2 ** (failures - 1));
                    } else {
                        throw error;
                    }
                }

                range = nextChunk(item.size, item.sent, chunkSize);
            }

            if (controller.signal.aborted) {
                return;
            }

            item.asset = await mediaApi.completeUpload(item.sessionId);
            item.status = 'done';
            item.sessionId = null;
            finished.value++;
        } catch (caught) {
            const error = toApiError(caught);
            item.status = 'error';
            item.error = error.fieldErrors.file?.[0] ?? error.message;

            // A refused file can't be resumed.
            if (error.status === 422) {
                item.sessionId = null;
                item.sent = 0;
            }
        } finally {
            controllers.delete(key);
            pump();
        }
    }

    function retry(key: number): void {
        const item = find(key);

        if (item?.status === 'error') {
            item.status = 'queued';
            item.error = null;
            pump();
        }
    }

    /** Stops an upload and drops what the server received. */
    function cancel(key: number): void {
        const item = find(key);
        controllers.get(key)?.abort();

        if (item?.sessionId) {
            void mediaApi.cancelUpload(item.sessionId).catch(() => undefined);
        }

        items.value = items.value.filter((entry) => entry.key !== key);
        pump();
    }

    /** Removes finished and failed uploads from the list. */
    function clearFinished(): void {
        items.value = items.value.filter((item) => item.status === 'queued' || item.status === 'uploading');
    }

    return { items, finished, active, progress, add, retry, cancel, clearFinished };
});
