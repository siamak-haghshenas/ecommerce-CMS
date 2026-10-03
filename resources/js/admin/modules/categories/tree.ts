import { slugify } from '../seo/analysis';
import type { CategoryNode } from './types';

/** A category with its depth and ancestor names, for flat lists (search results, parent pickers). */
export interface FlatCategory {
    node: CategoryNode;
    depth: number;
    path: string[];
}

/** Every category in display order (depth-first). */
export function flatten(nodes: CategoryNode[], depth = 0, path: string[] = []): FlatCategory[] {
    return nodes.flatMap((node) => [{ node, depth, path }, ...flatten(node.children, depth + 1, [...path, node.name])]);
}

export function findNode(nodes: CategoryNode[], id: number): CategoryNode | null {
    for (const node of nodes) {
        if (node.id === id) {
            return node;
        }

        const found = findNode(node.children, id);

        if (found) {
            return found;
        }
    }

    return null;
}

/** The children of a parent (null = top level). */
export function childrenOf(nodes: CategoryNode[], parentId: number | null): CategoryNode[] {
    return parentId === null ? nodes : (findNode(nodes, parentId)?.children ?? []);
}

/** Ids of the category and everything below it: none of them can become its parent. */
export function branchIds(node: CategoryNode): Set<number> {
    return new Set([node.id, ...flatten(node.children).map((item) => item.node.id)]);
}

/** A slug suggestion from the name; Persian names are transliterated, since slugs are Latin only. */
export const suggestSlug = slugify;

/** Same rule as the API: lowercase Latin letters and digits separated by single hyphens. */
export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
