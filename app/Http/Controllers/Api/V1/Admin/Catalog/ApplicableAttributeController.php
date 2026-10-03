<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AttributeResource;
use App\Support\CategoryAttributes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApplicableAttributeController extends Controller
{
    /**
     * The specifications a product in these categories can have (own + inherited, combined),
     * with their values: the product editor shows these fields.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate(['category_ids' => ['array', 'max:20'], 'category_ids.*' => ['integer']]);

        return AttributeResource::collection(
            CategoryAttributes::applicable(array_values(array_map(intval(...), (array) $request->input('category_ids', [])))),
        );
    }
}
