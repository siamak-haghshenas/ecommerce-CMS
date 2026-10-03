import { inject, type InjectionKey, type Ref } from 'vue';
import type { CategoryNode } from './types';

export type CategoryAction = 'edit' | 'addChild' | 'moveUp' | 'moveDown' | 'moveTo' | 'toggleVisibility' | 'delete';

/** Shared by the recursive tree components, provided by CategoriesPage. */
export interface CategoryTreeContext {
    canManage: Readonly<Ref<boolean>>;
    /** True while a category is being dragged: empty child lists grow into drop zones. */
    dragging: Ref<boolean>;
    isCollapsed: (id: number) => boolean;
    toggleCollapsed: (id: number) => void;
    act: (action: CategoryAction, node: CategoryNode) => void;
    /** Saves the full child order of a parent after a drop. */
    reorder: (parentId: number | null, ids: number[]) => void;
}

export const categoryTreeKey: InjectionKey<CategoryTreeContext> = Symbol('categoryTree');

export function useCategoryTree(): CategoryTreeContext {
    const context = inject(categoryTreeKey);

    if (!context) {
        throw new Error('useCategoryTree() must be used inside CategoriesPage.');
    }

    return context;
}
