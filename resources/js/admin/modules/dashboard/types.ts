import type { Customer } from '../customers/types';

export interface DashboardSummary {
    stats: {
        customers_total: number;
        customers_new_this_week: number;
        customers_new_previous_week: number;
        pending_registrations: number;
        staff_total: number;
    };
    recent_customers: Customer[];
}
