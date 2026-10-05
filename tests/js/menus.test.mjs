// The menu editor's tree helpers (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { indented, moved, outdented, toPayload } from '../../resources/js/admin/modules/menus/tree.ts';

const row = (key, children = []) => ({
    key,
    id: null,
    type: 'url',
    label: key,
    pageId: null,
    categoryId: null,
    url: `/${key}`,
    openInNewTab: false,
    targetName: '',
    targetDetail: '',
    hiddenReason: null,
    children,
});
const keys = (rows) => rows.map((item) => (item.children.length ? `${item.key}[${keys(item.children)}]` : item.key)).join(',');

describe('menu tree', () => {
    it('moves an item within its list, and not past the ends', () => {
        const rows = [row('a'), row('b'), row('c')];

        assert.equal(keys(moved(rows, 0, 1)), 'b,a,c');
        assert.equal(keys(moved(rows, 2, -2)), 'c,a,b');
        assert.equal(moved(rows, 0, -1), rows);
    });

    it('indents an item under the one above, but never a first item or one with sub-items', () => {
        const rows = [row('a'), row('b'), row('c', [row('d')])];

        assert.equal(keys(indented(rows, 1)), 'a[b],c[d]');
        assert.equal(indented(rows, 0), rows);
        assert.equal(indented(rows, 2), rows);
    });

    it('outdents a sub-item to just after its parent', () => {
        const rows = [row('a', [row('b'), row('c')]), row('d')];

        assert.equal(keys(outdented(rows, 0, 0)), 'a[c],b,d');
    });

    it('sends only the fields of the item type, and no deeper than two levels', () => {
        const child = { ...row('b', [row('c')]), type: 'page', pageId: 7, label: ' ' };
        const [payload] = toPayload([{ ...row('a', [child]), label: ' Shop ' }]);

        assert.equal(payload.label, 'Shop');
        assert.deepEqual(payload.children, [
            { id: null, type: 'page', label: null, page_id: 7, category_id: null, url: null, open_in_new_tab: false },
        ]);
    });
});
