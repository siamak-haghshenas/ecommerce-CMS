import type { Paginated } from '@/shared/types/api';

/** New comments are pending; only approved ones are public. Nothing goes back to pending. */
export type CommentStatus = 'pending' | 'approved' | 'rejected' | 'spam';

/** The statuses staff can set. */
export type ModeratedStatus = Exclude<CommentStatus, 'pending'>;

/** What customers comment on (CommentableType). */
export type CommentableType = 'post' | 'product';

export interface StaffRef {
    id: number;
    name: string;
}

/** A comment on a blog post or a product, as moderation sees it. The text is plain. */
export interface Comment {
    id: number;
    status: CommentStatus;
    display_name: string;
    body: string;
    /** The customer account (its page is under Customers). */
    customer_id: number;
    /** The post or product (a deleted one stays, marked). */
    item: { type: CommentableType; id: number; title: string; slug: string; is_deleted: boolean } | null;
    moderated_by: StaffRef | null;
    moderated_at: string | null;
    /** Staff only; set while rejected. */
    rejection_reason: string | null;
    /** The store's answer, shown under the comment once approved. */
    reply: string | null;
    replied_by: StaffRef | null;
    replied_at: string | null;
    created_at: string | null;
}

export interface CommentFilters {
    q?: string;
    /** Default `pending`. */
    status?: CommentStatus | 'all';
    /** Posts or products only. */
    type?: CommentableType;
    /** One post or product (with `type`). */
    item_id?: number;
    page?: number;
    per_page?: number;
}

export interface CommentList {
    data: Comment[];
    meta: Paginated<Comment>['meta'] & {
        /** Per status, the search and item filters applied. */
        status_counts: Record<CommentStatus | 'all', number>;
    };
}
