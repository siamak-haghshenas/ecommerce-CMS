<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A category change refused by a tree rule, rendered with a machine-readable code.
 */
final class CategoryException extends Exception implements ShouldntReport
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
        public readonly ?string $field = null,
    ) {
        parent::__construct($errorCode);
    }

    /**
     * Sub-categories must be moved or deleted by the admin first; nothing moves automatically.
     */
    public static function hasChildren(): self
    {
        return new self('category_has_children');
    }

    /**
     * Products (including deleted ones, which orders refer to) are still in the category.
     */
    public static function hasProducts(): self
    {
        return new self('category_has_products');
    }

    /**
     * Blog posts (including ones in the trash) are still in the category.
     */
    public static function hasPosts(): self
    {
        return new self('category_has_posts');
    }

    /**
     * A header or footer menu links to the category; the admin removes the menu item first.
     */
    public static function inMenu(): self
    {
        return new self('category_in_menu');
    }

    /**
     * The new parent is the category itself or one of its descendants.
     */
    public static function invalidParent(): self
    {
        return new self('category_invalid_parent', Response::HTTP_UNPROCESSABLE_ENTITY, 'parent_id');
    }

    /**
     * A reorder request did not list every current child, so the admin's view of the tree is outdated.
     */
    public static function treeChanged(): self
    {
        return new self('category_tree_changed');
    }

    public function render(): JsonResponse
    {
        $message = __("admin.errors.{$this->errorCode}");

        return response()->json(array_filter([
            'message' => $message,
            'code' => $this->errorCode,
            'errors' => $this->field === null ? null : [$this->field => [$message]],
        ], fn (mixed $value): bool => $value !== null), $this->status);
    }
}
