<?php

declare(strict_types=1);

namespace App\Data\Categories;

/**
 * One category as held in the cached CategoryTree (a plain value, so it can be cached as an array).
 */
final readonly class CategoryNode
{
    /**
     * @param  array{original: string, thumb: string, medium: string}|null  $image
     */
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $name,
        public string $slug,
        public int $position,
        public bool $isActive,
        public ?array $image,
        public ?string $focusKeyphrase,
        public ?int $seoScore,
        public ?int $readabilityScore,
    ) {}

    /**
     * @param  array{id: int, parent_id: int|null, name: string, slug: string, position: int, is_active: bool, image: array{original: string, thumb: string, medium: string}|null, focus_keyphrase: string|null, seo_score: int|null, readability_score: int|null}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: $row['id'],
            parentId: $row['parent_id'],
            name: $row['name'],
            slug: $row['slug'],
            position: $row['position'],
            isActive: $row['is_active'],
            image: $row['image'],
            focusKeyphrase: $row['focus_keyphrase'],
            seoScore: $row['seo_score'],
            readabilityScore: $row['readability_score'],
        );
    }

    /**
     * @return array{id: int, parent_id: int|null, name: string, slug: string, position: int, is_active: bool, image: array{original: string, thumb: string, medium: string}|null, focus_keyphrase: string|null, seo_score: int|null, readability_score: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'slug' => $this->slug,
            'position' => $this->position,
            'is_active' => $this->isActive,
            'image' => $this->image,
            'focus_keyphrase' => $this->focusKeyphrase,
            'seo_score' => $this->seoScore,
            'readability_score' => $this->readabilityScore,
        ];
    }
}
