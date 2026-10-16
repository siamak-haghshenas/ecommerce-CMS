// The comment moderation page's actions and row selection (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    pageSelection,
    statusesFor,
    toggleId,
    togglePage,
} from '../../resources/js/admin/modules/comments/comments.ts';

describe('statusesFor', () => {
    it('offers every moderated status a pending comment can move to', () => {
        assert.deepEqual(statusesFor('pending'), ['approved', 'rejected', 'spam']);
    });

    it('offers the other two for a moderated comment, never pending', () => {
        assert.deepEqual(statusesFor('approved'), ['rejected', 'spam']);
        assert.deepEqual(statusesFor('spam'), ['approved', 'rejected']);
        assert.ok(!statusesFor('rejected').includes('pending'));
    });
});

describe('selection', () => {
    it('tells whether none, some or all of the page is selected', () => {
        assert.equal(pageSelection([1, 2], new Set()), 'none');
        assert.equal(pageSelection([1, 2], new Set([2, 9])), 'some');
        assert.equal(pageSelection([1, 2], new Set([1, 2])), 'all');
    });

    it('toggles one row without changing the original set', () => {
        const selected = new Set([1]);

        assert.deepEqual([...toggleId(selected, 2)], [1, 2]);
        assert.deepEqual([...toggleId(selected, 1)], []);
        assert.deepEqual([...selected], [1]);
    });

    it('selects the whole page, or clears it when it is all selected', () => {
        assert.deepEqual([...togglePage(new Set([1]), [1, 2, 3])].sort(), [1, 2, 3]);
        assert.deepEqual([...togglePage(new Set([1, 2, 3, 9]), [1, 2, 3])], [9]);
    });
});
