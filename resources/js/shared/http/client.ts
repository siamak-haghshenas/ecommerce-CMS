import axios, { type InternalAxiosRequestConfig } from 'axios';
import { i18n } from '../i18n';
import { toApiError } from './errors';

type RetriableConfig = InternalAxiosRequestConfig & { csrfRetried?: boolean };

const SAFE_METHODS = ['get', 'head', 'options'];

/**
 * JSON client for /api/v1 using Sanctum's cookie-based SPA authentication:
 * the XSRF-TOKEN cookie is sent back as the X-XSRF-TOKEN header automatically.
 */
export const http = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let csrfCookie: Promise<void> | null = null;

function ensureCsrfCookie(): Promise<void> {
    csrfCookie ??= axios
        .get('/sanctum/csrf-cookie', { withCredentials: true })
        .then(() => undefined)
        .catch((error: unknown) => {
            csrfCookie = null;
            throw error;
        });

    return csrfCookie;
}

http.interceptors.request.use(async (config) => {
    config.headers.set('Accept-Language', i18n.global.locale.value);

    if (!SAFE_METHODS.includes((config.method ?? 'get').toLowerCase())) {
        await ensureCsrfCookie();
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    async (error: unknown) => {
        const config = axios.isAxiosError(error) ? (error.config as RetriableConfig | undefined) : undefined;

        // An expired CSRF token (419): fetch a fresh cookie and retry once.
        if (axios.isAxiosError(error) && error.response?.status === 419 && config && !config.csrfRetried) {
            config.csrfRetried = true;
            csrfCookie = null;
            await ensureCsrfCookie();

            return http.request(config);
        }

        throw toApiError(error);
    },
);
