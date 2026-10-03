import { reactive, ref } from 'vue';
import { ApiError, toApiError } from '../http/errors';

/** Error codes shown under a field rather than in the alert above the form. */
const INLINE_ERROR_CODES = new Set([
    'validation_failed',
    'otp_invalid',
    'otp_expired',
    'otp_too_many_attempts',
    'category_invalid_parent',
]);

/**
 * Tracks submission state and maps API errors onto a form:
 * field errors appear under their field, anything else in a general alert.
 */
export function useApiForm<Field extends string>(fields: readonly Field[]) {
    // Typed without Reactive<> wrapping: TypeScript cannot index a reactive object by a generic key.
    const errors: Partial<Record<Field, string>> = reactive({});
    const generalError = ref<string | null>(null);
    const lastError = ref<ApiError | null>(null);
    const processing = ref(false);

    function clearErrors(): void {
        for (const field of fields) {
            delete errors[field];
        }
        generalError.value = null;
        lastError.value = null;
    }

    function showError(error: ApiError): void {
        lastError.value = error;

        const inlineFields = INLINE_ERROR_CODES.has(error.code)
            ? fields.filter((field) => error.fieldErrors[field]?.[0] !== undefined)
            : [];

        for (const field of inlineFields) {
            errors[field] = error.fieldErrors[field]?.[0];
        }

        if (inlineFields.length === 0) {
            generalError.value = error.message;
        }
    }

    /** Run the request; resolves to its result, or undefined when it failed (errors are then shown). */
    async function submit<T>(request: () => Promise<T>): Promise<T | undefined> {
        if (processing.value) {
            return undefined;
        }

        clearErrors();
        processing.value = true;

        try {
            return await request();
        } catch (error) {
            showError(toApiError(error));

            return undefined;
        } finally {
            processing.value = false;
        }
    }

    return { errors, generalError, lastError, processing, submit, clearErrors, showError };
}
