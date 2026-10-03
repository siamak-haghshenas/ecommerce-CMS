<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Seo\SuggestSeoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SuggestSeoRequest;
use App\Http\Resources\V1\Admin\SeoSuggestionsResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class SeoSuggestionController extends Controller
{
    /**
     * AI suggestions for the SEO fields of an item being edited; nothing is saved.
     */
    public function __invoke(SuggestSeoRequest $request, SuggestSeoAction $suggest, #[CurrentUser] User $actor): SeoSuggestionsResource
    {
        return new SeoSuggestionsResource($suggest->handle($actor, $request->toContext()));
    }
}
