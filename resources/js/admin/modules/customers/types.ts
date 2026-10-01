export interface Customer {
    id: number;
    /** A translated "anonymised user" label once anonymised. */
    name: string;
    phone_number: string | null;
    email: string | null;
    locale: 'fa' | 'en';
    is_active: boolean;
    is_anonymised: boolean;
    registration_completed: boolean;
    phone_verified_at: string | null;
    last_login_at: string | null;
    anonymised_at: string | null;
    created_at: string | null;
}

export interface CustomerFilters {
    q?: string;
    status?: 'active' | 'inactive' | 'anonymised';
    registration?: 'completed' | 'pending';
    page?: number;
    per_page?: number;
}

/** The phone number is not editable by staff: changing it requires verifying the new number. */
export interface UpdateCustomerPayload {
    name: string;
    email: string | null;
    locale: 'fa' | 'en';
}
