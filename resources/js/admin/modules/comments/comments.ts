// The moderation page's logic: which actions a comment offers and the row selection. No Vue, so
// `tests/js/comments.test.mjs` runs it with Node.
import type { CommentStatus, ModeratedStatus } from './types';

/** CommentStatus on the server, in tab order. */
export const STATUSES: CommentStatus[] = ['pending', 'approved', 'rejected', 'spam'];

/** PostComment::BULK_MAX */
export const BULK_MAX = 100;

/** PostComment::REASON_MAX and BODY_MAX */
export const REASON_MAX = 500;
export const REPLY_MAX = 2000;

/** The statuses a comment can be moved to: the other moderated ones (nothing goes back to pending). */
export function statusesFor(status: CommentStatus): ModeratedStatus[] {
    return (['approved', 'rejected', 'spam'] as const).filter((next) => next !== status);
}

/** The selected rows of this page: none, some or all. */
export function pageSelection(pageIds: number[], selected: ReadonlySet<number>): 'none' | 'some' | 'all' {
    const chosen = pageIds.filter((id) => selected.has(id)).length;

    return chosen === 0 ? 'none' : chosen === pageIds.length ? 'all' : 'some';
}

/** Ticks or unticks one row. */
export function toggleId(selected: ReadonlySet<number>, id: number): Set<number> {
    const next = new Set(selected);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    return next;
}

/** "Select page": ticks every row of the page, or unticks them all when they are all ticked already. */
export function togglePage(selected: ReadonlySet<number>, pageIds: number[]): Set<number> {
    const next = new Set(selected);
    const all = pageSelection(pageIds, selected) === 'all';

    pageIds.forEach((id) => (all ? next.delete(id) : next.add(id)));

    return next;
}
