<script setup lang="ts">
import { nextTick } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useCategoryTree } from '../context';
import type { CategoryNode } from '../types';
import CategoryRow from './CategoryRow.vue';

/**
 * One level of the category tree, rendering itself for each expanded child.
 * Every level shares the "categories" drag group, so a category can be dropped into any list.
 */
const props = defineProps<{ parentId: number | null; depth: number }>();
const nodes = defineModel<CategoryNode[]>('nodes', { required: true });

const tree = useCategoryTree();

/** Called on a reorder within this list and when a category is dropped in from another list. */
async function save(): Promise<void> {
    // Let the list model catch up with the drop before reading the new order.
    await nextTick();
    tree.reorder(
        props.parentId,
        nodes.value.map((node) => node.id),
    );
}
</script>

<template>
    <VueDraggable
        v-model="nodes"
        tag="ul"
        group="categories"
        handle=".drag-handle"
        ghost-class="category-drop-ghost"
        :animation="150"
        :disabled="!tree.canManage.value"
        :class="['flex flex-col', tree.dragging.value && nodes.length === 0 && 'min-h-8 rounded-lg']"
        @start="tree.dragging.value = true"
        @end="tree.dragging.value = false"
        @update="save"
        @add="save"
    >
        <li v-for="(node, index) in nodes" :key="node.id" :data-id="node.id">
            <CategoryRow :node="node" :index="index" :sibling-count="nodes.length" draggable />
            <!-- A start border guides the eye down each branch. -->
            <div v-if="!tree.isCollapsed(node.id)" class="ms-6 border-s border-line ps-2 sm:ms-7">
                <CategoryTreeList v-model:nodes="node.children" :parent-id="node.id" :depth="depth + 1" />
            </div>
        </li>
    </VueDraggable>
</template>

<style scoped>
.category-drop-ghost {
    opacity: 0.4;
}
</style>
