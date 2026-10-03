<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Data\Seo\SeoSuggestions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SeoSuggestions $resource
 */
class SeoSuggestionsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{keyphrases: list<string>, titles: list<string>, meta_descriptions: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'keyphrases' => $this->resource->keyphrases,
            'titles' => $this->resource->titles,
            'meta_descriptions' => $this->resource->metaDescriptions,
        ];
    }
}
