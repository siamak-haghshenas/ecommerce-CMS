import { isAxiosError } from 'axios';
import { i18n } from '../i18n';
import type { ApiErrorBody } from '../types/api';

/** Every failed API call is turned into this, matching the backend's error shape. */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
        message: string,
        public readonly fieldErrors: Record<string, string[]> = {},
        public readonly retryAfter: number | null = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function isApiErrorBody(value: unknown): value is ApiErrorBody {
    return typeof value === 'object' && value !== null && 'code' in value && 'message' in value;
}

export function toApiError(error: unknown): ApiError {
    if (error instanceof ApiError) {
        return error;
    }

    if (isAxiosError(error) && error.response) {
        const body: unknown = error.response.data;

        if (isApiErrorBody(body)) {
            return new ApiError(
                error.response.status,
                body.code,
                body.message,
                body.errors ?? {},
                body.retry_after ?? null,
            );
        }

        return new ApiError(error.response.status, 'http_error', error.message);
    }

    return new ApiError(0, 'network_error', i18n.global.t('common.networkError'));
}
