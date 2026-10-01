/** Laravel API Resource wrapper: every success response is `{ data: ... }`. */
export interface Resource<T> {
    data: T;
}

/** A Laravel paginated Resource collection. */
export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}

/** The unified error shape every API error uses. */
export interface ApiErrorBody {
    message: string;
    code: string;
    errors?: Record<string, string[]>;
    retry_after?: number;
}

/** Returned whenever a verification code is (or may have been) sent. */
export interface OtpChallenge {
    phone_number: string;
    code_length: number;
    expires_in: number;
    resend_available_in: number;
}

export type OtpPurpose = 'register' | 'login';
