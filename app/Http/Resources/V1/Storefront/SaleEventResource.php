<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Models\SaleEvent;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A sale event's landing page. `status` (`upcoming`, `running`, `ended`) and the dates drive the
 * storefront's countdown; its products come from `GET /products?sale_event={slug}`. Never the
 * priority, the variants' discounts, the focus keyphrase or scores.
 *
 * @mixin SaleEvent
 */
class SaleEventResource extends JsonResource
{
    /** Whether to include the description and SEO fields (the page rather than the list). */
    public bool $detailed = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $banner = $this->bannerAsset()?->picture();
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->landing_title ?? $this->name,
            'status' => $this->landingStatus(),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'banner' => $banner,
            'robots_noindex' => $this->robots_noindex,
        ];

        if (! $this->detailed) {
            return $data;
        }

        $ogImage = $this->ogImageAsset()?->picture();
        $summary = Str::limit(RichText::toPlainText((string) $this->landing_description), 160, '…', preserveWords: true) ?: null;
        $title = $this->landing_title ?? $this->name;

        return [
            ...$data,
            'description' => $this->landing_description,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $ogImage,
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Ready to use, with the fallbacks applied (the fields above are as the admin typed them).
            'seo' => [
                'title' => $this->meta_title ?? $title,
                'description' => $this->meta_description ?? $summary,
                'canonical_url' => $this->canonical_url,
                'robots_noindex' => $this->robots_noindex,
                'og' => [
                    'title' => $this->og_title ?? $this->meta_title ?? $title,
                    'description' => $this->og_description ?? $this->meta_description ?? $summary,
                    'image' => $ogImage ?? $banner,
                ],
            ],
        ];
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }
}
